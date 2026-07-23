<?php
declare(strict_types=1);

/**
 * Controller for CRM Contacts (Subscribers), Lists, CSV imports, Tag management, and Unsubscribes
 */
class ContactController {
    public function index(): void {
        $db = Database::getConnection();
        
        $action = $_GET['action'] ?? '';
        
        // 1. Process Actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf()) {
                $_SESSION['flash_error'] = 'CSRF validation failed.';
                header('Location: ' . getSetting('app_url') . '/contacts');
                exit;
            }
            if ($action === 'create_list') {
                $name = trim($_POST['name'] ?? '');
                $desc = trim($_POST['description'] ?? '');
                if ($name !== '') {
                    $st = $db->prepare("INSERT INTO lists (name, description) VALUES (?, ?)");
                    $st->execute([$name, $desc]);
                    $_SESSION['flash_success'] = 'List created successfully!';
                } else {
                    $_SESSION['flash_error'] = 'List name is required.';
                }
                header('Location: ' . getSetting('app_url') . '/contacts');
                exit;
            }

            if ($action === 'create_tag') {
                $name = trim($_POST['name'] ?? '');
                if ($name !== '') {
                    // Predefined theme-style pastel colors
                    $colors = ['#635bff', '#00d4b2', '#8b5cf6', '#ff5b60', '#ffba52', '#3b82f6', '#ec4899', '#10b981'];
                    $color = $colors[array_rand($colors)];
                    
                    try {
                        $st = $db->prepare("INSERT INTO tags (name, color, created_at) VALUES (?, ?, NOW())");
                        $st->execute([$name, $color]);
                        $_SESSION['flash_success'] = 'Tag created successfully!';
                    } catch (Throwable $e) {
                        $_SESSION['flash_error'] = 'Tag already exists or failed to save.';
                    }
                } else {
                    $_SESSION['flash_error'] = 'Tag name is required.';
                }
                header('Location: ' . getSetting('app_url') . '/contacts');
                exit;
            }

            if ($action === 'delete_tag') {
                $tagId = (int)($_GET['tag_id'] ?? 0);
                if ($tagId > 0) {
                    $db->prepare("DELETE FROM tags WHERE id = ?")->execute([$tagId]);
                    $_SESSION['flash_success'] = 'Tag deleted successfully.';
                }
                header('Location: ' . getSetting('app_url') . '/contacts');
                exit;
            }

            if ($action === 'delete_contact') {
                $subId = (int)($_GET['id'] ?? 0);
                if ($subId > 0) {
                    $db->prepare("DELETE FROM subscribers WHERE id = ?")->execute([$subId]);
                    $_SESSION['flash_success'] = 'Contact deleted successfully.';
                }
                header('Location: ' . getSetting('app_url') . '/contacts');
                exit;
            }

            if ($action === 'mass_delete') {
                $selected = array_map('intval', $_POST['selected_contacts'] ?? $_POST['subscriber_ids'] ?? []);
                if (!empty($selected)) {
                    $placeholders = implode(',', array_fill(0, count($selected), '?'));
                    $db->beginTransaction();
                    try {
                        $db->prepare("DELETE FROM subscriber_tags WHERE subscriber_id IN ({$placeholders})")->execute($selected);
                        $db->prepare("DELETE FROM subscriber_lists WHERE subscriber_id IN ({$placeholders})")->execute($selected);
                        $db->prepare("DELETE FROM email_queue WHERE subscriber_id IN ({$placeholders})")->execute($selected);
                        $db->prepare("DELETE FROM automation_queue WHERE subscriber_id IN ({$placeholders})")->execute($selected);
                        $db->prepare("DELETE FROM subscribers WHERE id IN ({$placeholders})")->execute($selected);
                        $db->commit();
                        $_SESSION['flash_success'] = count($selected) . ' contact(s) deleted successfully.';
                    } catch (Throwable $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        $_SESSION['flash_error'] = 'Failed to execute mass deletion: ' . $e->getMessage();
                    }
                }
                header('Location: ' . getSetting('app_url') . '/contacts');
                exit;
            }

            if ($action === 'mass_tag_add') {
                $selected = $_POST['selected_contacts'] ?? [];
                $tagId = (int)($_POST['bulk_tag_id'] ?? 0);
                if (!empty($selected) && $tagId > 0) {
                    $db->beginTransaction();
                    try {
                        $st = $db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)");
                        foreach ($selected as $subId) {
                            $st->execute([(int)$subId, $tagId]);
                            Automation::trigger("tag_added:{$tagId}", (int)$subId);
                        }
                        $db->commit();
                        $_SESSION['flash_success'] = 'Tag assigned to ' . count($selected) . ' contacts.';
                    } catch (Throwable $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        $_SESSION['flash_error'] = 'Failed to assign tags: ' . $e->getMessage();
                    }
                } else {
                    $_SESSION['flash_error'] = 'No contacts or invalid tag selected.';
                }
                header('Location: ' . getSetting('app_url') . '/contacts');
                exit;
            }

            if ($action === 'mass_tag_remove') {
                $selected = $_POST['selected_contacts'] ?? [];
                $tagId = (int)($_POST['bulk_tag_id'] ?? 0);
                if (!empty($selected) && $tagId > 0) {
                    $db->beginTransaction();
                    try {
                        $placeholders = implode(',', array_fill(0, count($selected), '?'));
                        $params = array_merge([$tagId], array_map('intval', $selected));
                        $st = $db->prepare("DELETE FROM subscriber_tags WHERE tag_id = ? AND subscriber_id IN ($placeholders)");
                        $st->execute($params);
                        $db->commit();
                        $_SESSION['flash_success'] = 'Tag removed from ' . count($selected) . ' contacts.';
                    } catch (Throwable $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        $_SESSION['flash_error'] = 'Failed to remove tags: ' . $e->getMessage();
                    }
                } else {
                    $_SESSION['flash_error'] = 'No contacts or invalid tag selected.';
                }
                header('Location: ' . getSetting('app_url') . '/contacts');
                exit;
            }
            
            if ($action === 'add_contact') {
                $email = strtolower(trim($_POST['email'] ?? ''));
                $first = trim($_POST['first_name'] ?? '');
                $last = trim($_POST['last_name'] ?? '');
                $listId = (int)($_POST['list_id'] ?? 0);
                $selectedTags = $_POST['tags'] ?? []; // array of tag IDs
                
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    // Fire email verifier dynamic hook checks
                    $hookData = ['email' => $email, 'valid' => true, 'error' => ''];
                    Hook::fire('before_add_contact', $hookData);
                    if (!$hookData['valid']) {
                        $_SESSION['flash_error'] = 'Email Verification Blocked: ' . $hookData['error'];
                        header('Location: ' . getSetting('app_url') . '/contacts');
                        exit;
                    }

                    $db->beginTransaction();
                    try {
                        // Insert contact
                        $st = $db->prepare("
                            INSERT INTO subscribers (email, first_name, last_name, status) 
                            VALUES (?, ?, ?, 'active') 
                            ON DUPLICATE KEY UPDATE first_name = ?, last_name = ?, status = 'active'
                        ");
                        $st->execute([$email, $first, $last, $first, $last]);
                        
                        // Get ID
                        $stGet = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
                        $stGet->execute([$email]);
                        $subId = (int)$stGet->fetchColumn();
                        
                        // Assign to list if selected
                        if ($listId > 0) {
                            $stList = $db->prepare("INSERT IGNORE INTO subscriber_lists (subscriber_id, list_id) VALUES (?, ?)");
                            $stList->execute([$subId, $listId]);
                        }

                        // Process Tag assignments (additive tagging)
                        if (!empty($selectedTags)) {
                            $stTag = $db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)");
                            foreach ($selectedTags as $tagId) {
                                $stTag->execute([$subId, (int)$tagId]);
                                Automation::trigger("tag_added:{$tagId}", $subId);
                            }
                        }
                        
                        logActivity($subId, 'subscribe', "Subscribed via Quick Add to list #{$listId}");
                        
                        $db->commit();
                        
                        // Trigger automations and Autopilot campaign sync
                        Automation::trigger('subscribe', $subId);
                        CampaignController::syncActiveCampaigns($db);
                        $hookDataAfter = ['subscriber_id' => $subId];
                        Hook::fire('contact_added', $hookDataAfter);
                        
                        $_SESSION['flash_success'] = "Contact {$email} added successfully!";
                    } catch (Throwable $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        $_SESSION['flash_error'] = 'Failed to add contact: ' . $e->getMessage();
                    }
                } else {
                    $_SESSION['flash_error'] = 'Invalid email address.';
                }
                header('Location: ' . getSetting('app_url') . '/contacts');
                exit;
            }
            
            if ($action === 'import_csv') {
                $listId = (int)($_POST['list_id'] ?? 0);
                $selectedTags = $_POST['tags'] ?? []; // assign these tags to all imported users
                
                if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['csv_file']['tmp_name'];
                    $handle = fopen($file, 'r');
                    if ($handle !== false) {
                        $headers = fgetcsv($handle);
                        $emailIdx = -1;
                        $firstIdx = -1;
                        $lastIdx = -1;
                        
                        foreach ($headers as $i => $h) {
                            $hClean = strtolower(trim($h));
                            if ($hClean === 'email') $emailIdx = $i;
                            if ($hClean === 'first_name' || $hClean === 'firstname' || $hClean === 'first') $firstIdx = $i;
                            if ($hClean === 'last_name' || $hClean === 'lastname' || $hClean === 'last') $lastIdx = $i;
                        }
                        
                        if ($emailIdx === -1) {
                            $_SESSION['flash_error'] = 'CSV must contain an "email" column.';
                            fclose($handle);
                            header('Location: ' . getSetting('app_url') . '/contacts');
                            exit;
                        }
                        
                        $imported = 0;
                        $skipped = 0;
                        
                        $db->beginTransaction();
                        try {
                            while (($row = fgetcsv($handle)) !== false) {
                                $email = strtolower(trim($row[$emailIdx] ?? ''));
                                $first = $firstIdx !== -1 ? trim($row[$firstIdx] ?? '') : '';
                                $last = $lastIdx !== -1 ? trim($row[$lastIdx] ?? '') : '';
                                
                                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    // Fire email verifier dynamic hook checks
                                    $hookData = ['email' => $email, 'valid' => true, 'error' => ''];
                                    Hook::fire('before_add_contact', $hookData);
                                    if (!$hookData['valid']) {
                                        $skipped++;
                                        continue;
                                    }

                                    $st = $db->prepare("
                                        INSERT INTO subscribers (email, first_name, last_name, status) 
                                        VALUES (?, ?, ?, 'active') 
                                        ON DUPLICATE KEY UPDATE first_name = ?, last_name = ?, status = 'active'
                                    ");
                                    $st->execute([$email, $first, $last, $first, $last]);
                                    
                                    $stGet = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
                                    $stGet->execute([$email]);
                                    $subId = (int)$stGet->fetchColumn();
                                    
                                    if ($listId > 0) {
                                        $stList = $db->prepare("INSERT IGNORE INTO subscriber_lists (subscriber_id, list_id) VALUES (?, ?)");
                                        $stList->execute([$subId, $listId]);
                                    }

                                    // Add tags
                                    if (!empty($selectedTags)) {
                                        $stTag = $db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)");
                                        foreach ($selectedTags as $tagId) {
                                            $stTag->execute([$subId, (int)$tagId]);
                                            Automation::trigger("tag_added:{$tagId}", $subId);
                                        }
                                    }
                                    
                                    logActivity($subId, 'subscribe', "Subscribed via CSV import to list #{$listId}");
                                    Automation::trigger('subscribe', $subId);
                                    $hookDataAfter = ['subscriber_id' => $subId];
                                    Hook::fire('contact_added', $hookDataAfter);
                                    
                                    $imported++;
                                } else {
                                    $skipped++;
                                }
                            }
                            $db->commit();
                            CampaignController::syncActiveCampaigns($db);
                            $_SESSION['flash_success'] = "Imported {$imported} contacts successfully. Skipped {$skipped} invalid rows.";
                        } catch (Throwable $e) {
                            if ($db->inTransaction()) {
                                $db->rollBack();
                            }
                            $_SESSION['flash_error'] = 'CSV Import failed: ' . $e->getMessage();
                        }
                        fclose($handle);
                    } else {
                        $_SESSION['flash_error'] = 'Failed to open CSV file.';
                    }
                } else {
                    $_SESSION['flash_error'] = 'CSV file upload failed.';
                }
                header('Location: ' . getSetting('app_url') . '/contacts');
                exit;
            }

            if ($action === 'bulk_action') {
                $ids = array_map('intval', $_POST['subscriber_ids'] ?? []);
                $bulkType = $_POST['bulk_type'] ?? '';

                if (empty($ids)) {
                    $_SESSION['flash_error'] = 'No contacts selected for bulk action.';
                    header('Location: ' . getSetting('app_url') . '/contacts');
                    exit;
                }

                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                if ($bulkType === 'delete') {
                    $db->prepare("DELETE FROM subscriber_tags WHERE subscriber_id IN ({$placeholders})")->execute($ids);
                    $db->prepare("DELETE FROM subscriber_lists WHERE subscriber_id IN ({$placeholders})")->execute($ids);
                    $db->prepare("DELETE FROM email_queue WHERE subscriber_id IN ({$placeholders})")->execute($ids);
                    $db->prepare("DELETE FROM automation_queue WHERE subscriber_id IN ({$placeholders})")->execute($ids);
                    $db->prepare("DELETE FROM subscribers WHERE id IN ({$placeholders})")->execute($ids);
                    $_SESSION['flash_success'] = "Deleted " . count($ids) . " contact(s).";
                } elseif ($bulkType === 'add_tag' && isset($_POST['bulk_tag_id'])) {
                    $tagId = (int)$_POST['bulk_tag_id'];
                    if ($tagId > 0) {
                        $stT = $db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)");
                        foreach ($ids as $sId) {
                            $stT->execute([$sId, $tagId]);
                            Automation::trigger("tag_added:{$tagId}", $sId);
                        }
                        $_SESSION['flash_success'] = "Applied tag to " . count($ids) . " contact(s).";
                    }
                } elseif ($bulkType === 'add_list' && isset($_POST['bulk_list_id'])) {
                    $listId = (int)$_POST['bulk_list_id'];
                    if ($listId > 0) {
                        $stL = $db->prepare("INSERT IGNORE INTO subscriber_lists (subscriber_id, list_id) VALUES (?, ?)");
                        foreach ($ids as $sId) {
                            $stL->execute([$sId, $listId]);
                        }
                        $_SESSION['flash_success'] = "Added " . count($ids) . " contact(s) to target list.";
                    }
                } elseif ($bulkType === 'change_status' && isset($_POST['bulk_status_val'])) {
                    $statusVal = $_POST['bulk_status_val'];
                    if (in_array($statusVal, ['active', 'unsubscribed', 'bounced'], true)) {
                        $params = array_merge([$statusVal], $ids);
                        $db->prepare("UPDATE subscribers SET status = ? WHERE id IN ({$placeholders})")->execute($params);
                        $_SESSION['flash_success'] = "Updated status of " . count($ids) . " contact(s) to {$statusVal}.";
                    }
                }
                header('Location: ' . getSetting('app_url') . '/contacts');
                exit;
            }
        }
        
        // 2. Fetch Lists with count
        $stLists = $db->query("
            SELECT l.*, COUNT(sl.subscriber_id) as subscriber_count 
            FROM lists l
            LEFT JOIN subscriber_lists sl ON sl.list_id = l.id
            GROUP BY l.id
            ORDER BY l.name ASC
        ");
        $lists = $stLists->fetchAll();

        // 3. Fetch Tags list
        $tags = $db->query("SELECT * FROM tags ORDER BY name ASC")->fetchAll();
        
        // 4. Fetch Subscribers with their associated tags
        $search = trim($_GET['q'] ?? $_GET['search'] ?? '');
        $filterList = (int)($_GET['list_id'] ?? 0);
        $filterTag = (int)($_GET['tag_id'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limitParam = isset($_GET['limit']) ? (int)$_GET['limit'] : (int)($_SESSION['contacts_per_page'] ?? 50);
        if (in_array($limitParam, [25, 50, 100, 250, 500, 1000, 5000], true)) {
            $limit = $limitParam;
        } else {
            $limit = 50;
        }
        $_SESSION['contacts_per_page'] = $limit;
        
        $sort = $_GET['sort'] ?? 'created_at';
        $order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $validSorts = ['email', 'name', 'status', 'created_at', 'tag'];
        if (!in_array($sort, $validSorts)) {
            $sort = 'created_at';
        }
        
        $orderField = "s.created_at";
        if ($sort === 'email') $orderField = "s.email";
        if ($sort === 'name') $orderField = "s.first_name";
        if ($sort === 'status') $orderField = "s.status";
        if ($sort === 'tag') $orderField = "first_tag";

        $joins = [];
        $conditions = [];
        $params = [];
        
        if ($filterList > 0) {
            $joins[] = "JOIN subscriber_lists sl ON sl.subscriber_id = s.id";
            $conditions[] = "sl.list_id = ?";
            $params[] = $filterList;
        }

        if ($filterTag > 0) {
            $joins[] = "JOIN subscriber_tags stg ON stg.subscriber_id = s.id";
            $conditions[] = "stg.tag_id = ?";
            $params[] = $filterTag;
        }

        $segment = trim($_GET['segment'] ?? '');
        if ($segment === 'active_openers') {
            $conditions[] = "s.id IN (SELECT DISTINCT subscriber_id FROM campaign_opens WHERE opened_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))";
        } elseif ($segment === 'high_intent') {
            $conditions[] = "s.lead_score >= 50";
        } elseif ($segment === 'cold_contacts') {
            $conditions[] = "s.id NOT IN (SELECT DISTINCT subscriber_id FROM campaign_opens) AND s.created_at <= DATE_SUB(NOW(), INTERVAL 14 DAY)";
        }
        
        if ($search !== '') {
            $conditions[] = "(s.email LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.attributes LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        // Count total contacts matching the current segment criteria
        $countQuery = "SELECT COUNT(DISTINCT s.id) FROM subscribers s";
        if (!empty($joins)) {
            $countQuery .= " " . implode(" ", $joins);
        }
        if (!empty($conditions)) {
            $countQuery .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $stCount = $db->prepare($countQuery);
        $stCount->execute($params);
        $totalContacts = (int)$stCount->fetchColumn();
        $totalPages = max(1, (int)ceil($totalContacts / $limit));
        $page = max(1, min($page, $totalPages));
        $offset = max(0, ($page - 1) * $limit);
        
        // Fetch pages contacts matching the criteria
        $query = "SELECT DISTINCT s.*, (SELECT MIN(t2.name) FROM tags t2 JOIN subscriber_tags st2 ON st2.tag_id = t2.id WHERE st2.subscriber_id = s.id) as first_tag FROM subscribers s";
        if (!empty($joins)) {
            $query .= " " . implode(" ", $joins);
        }
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        $query .= " ORDER BY {$orderField} {$order} LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $stSubs = $db->prepare($query);
        $stSubs->execute($params);
        $contacts = $stSubs->fetchAll();

        // Populate contact tags for display mapping in one query to fix N+1
        if (!empty($contacts)) {
            $subIds = array_column($contacts, 'id');
            $placeholders = implode(',', array_fill(0, count($subIds), '?'));
            $stAllTags = $db->prepare("
                SELECT st.subscriber_id, t.id, t.name, t.color 
                FROM tags t 
                JOIN subscriber_tags st ON st.tag_id = t.id 
                WHERE st.subscriber_id IN ({$placeholders})
            ");
            $stAllTags->execute($subIds);
            $allTags = $stAllTags->fetchAll();
            
            $tagsBySub = [];
            foreach ($allTags as $row) {
                $tagsBySub[$row['subscriber_id']][] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'color' => $row['color']
                ];
            }
            foreach ($contacts as &$c) {
                $c['tags'] = $tagsBySub[$c['id']] ?? [];
            }
            unset($c);
        }

        $title = 'Contacts (CRM)';
        $viewPath = dirname(__DIR__) . '/views/contacts.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    public function view(): void {
        $db = Database::getConnection();
        $id = (int)($_GET['id'] ?? 0);
        $action = $_GET['action'] ?? '';

        if ($action === 'export_contact' && $id > 0) {
            $st = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
            $st->execute([$id]);
            $contact = $st->fetch();
            if ($contact) {
                $stLists = $db->prepare("SELECT l.name FROM lists l JOIN subscriber_lists sl ON sl.list_id = l.id WHERE sl.subscriber_id = ?");
                $stLists->execute([$id]);
                $listsList = $stLists->fetchAll(PDO::FETCH_COLUMN);

                $stTags = $db->prepare("SELECT t.name FROM tags t JOIN subscriber_tags st ON st.tag_id = t.id WHERE st.subscriber_id = ?");
                $stTags->execute([$id]);
                $tagsList = $stTags->fetchAll(PDO::FETCH_COLUMN);

                $stLogs = $db->prepare("SELECT activity_type, description, created_at FROM activity_log WHERE subscriber_id = ? ORDER BY created_at DESC");
                $stLogs->execute([$id]);
                $activitiesList = $stLogs->fetchAll(PDO::FETCH_ASSOC);

                $exportData = [
                    'profile' => [
                        'id' => $contact['id'],
                        'email' => $contact['email'],
                        'first_name' => $contact['first_name'],
                        'last_name' => $contact['last_name'],
                        'status' => $contact['status'],
                        'created_at' => $contact['created_at'],
                        'updated_at' => $contact['updated_at']
                    ],
                    'lists' => $listsList,
                    'tags' => $tagsList,
                    'activities' => $activitiesList
                ];

                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="gdpr-export-' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $contact['email']) . '.json"');
                echo json_encode($exportData, JSON_PRETTY_PRINT);
                exit;
            }
        }
        
        $st = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
        $st->execute([$id]);
        $contact = $st->fetch();
        
        if (!$contact) {
            $_SESSION['flash_error'] = 'Contact not found.';
            header('Location: ' . getSetting('app_url') . '/contacts');
            exit;
        }

        $stLists = $db->prepare("
            SELECT l.name 
            FROM lists l
            JOIN subscriber_lists sl ON sl.list_id = l.id
            WHERE sl.subscriber_id = ?
        ");
        $stLists->execute([$id]);
        $lists = $stLists->fetchAll();

        // Fetch assigned tags
        $stTags = $db->prepare("
            SELECT t.name, t.color 
            FROM tags t
            JOIN subscriber_tags st ON st.tag_id = t.id
            WHERE st.subscriber_id = ?
        ");
        $stTags->execute([$id]);
        $contactTags = $stTags->fetchAll();

        // Fetch web visits
        $stVisits = $db->prepare("SELECT url, COUNT(id) as visit_count FROM contact_visits WHERE subscriber_id = ? GROUP BY url ORDER BY visit_count DESC LIMIT 5");
        $stVisits->execute([$id]);
        $topPages = $stVisits->fetchAll();

        // Calculate Heat Score
        // Opens (1 pt), Clicks (2 pts), Visits (5 pts)
        $stOpens = $db->prepare("SELECT COUNT(*) FROM campaign_opens WHERE subscriber_id = ?"); $stOpens->execute([$id]); $cOpens = (int)$stOpens->fetchColumn();
        $stClicks = $db->prepare("SELECT COUNT(*) FROM campaign_clicks WHERE subscriber_id = ?"); $stClicks->execute([$id]); $cClicks = (int)$stClicks->fetchColumn();
        $stVisitsTotal = $db->prepare("SELECT COUNT(*) FROM contact_visits WHERE subscriber_id = ?"); $stVisitsTotal->execute([$id]); $cVisits = (int)$stVisitsTotal->fetchColumn();
        $heatScore = ($cOpens * 1) + ($cClicks * 2) + ($cVisits * 5);

        $stLogs = $db->prepare("SELECT * FROM activity_log WHERE subscriber_id = ? ORDER BY created_at DESC");
        $stLogs->execute([$id]);
        $activityLogs = $stLogs->fetchAll();

        $stScoreLogs = $db->prepare("SELECT * FROM lead_score_logs WHERE subscriber_id = ? ORDER BY id DESC LIMIT 50");
        $stScoreLogs->execute([$id]);
        $scoreLogs = $stScoreLogs->fetchAll();

        // Heat Score Chart Data (Last 30 Days)
        $stChart = $db->prepare("
            SELECT DATE(created_at) as date, activity_type as action, COUNT(*) as count 
            FROM activity_log 
            WHERE subscriber_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY date, activity_type
            ORDER BY date ASC
        ");
        $stChart->execute([$id]);
        $chartRaw = $stChart->fetchAll();
        
        $chartData = [];
        foreach ($chartRaw as $row) {
            $date = $row['date'];
            if (!isset($chartData[$date])) {
                $chartData[$date] = ['open' => 0, 'click' => 0, 'visit' => 0];
            }
            if (isset($chartData[$date][$row['action']])) {
                $chartData[$date][$row['action']] = (int)$row['count'];
            }
        }
        
        $dates = array_keys($chartData);
        $openSeries = array_column($chartData, 'open');
        $clickSeries = array_column($chartData, 'click');
        $visitSeries = array_column($chartData, 'visit');

        $title = 'Contact Profile';
        $viewPath = dirname(__DIR__) . '/views/contact_view.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    /**
     * Unsubscribe flow (public endpoint)
     */
    public function unsubscribe(): void {
        $db = Database::getConnection();
        $appName = getSetting('app_name', 'Merlin Spellcaster');
        
        $token = trim($_GET['t'] ?? '');
        $campaignId = (int)($_GET['c'] ?? 0);
        $subscriberId = (int)($_GET['s'] ?? 0);
        
        $error = null;
        $success = false;
        $email = '';
        
        if ($token && $campaignId && $subscriberId) {
            $stSub = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
            $stSub->execute([$subscriberId]);
            $subscriber = $stSub->fetch();
            
            if ($subscriber) {
                $email = $subscriber['email'];
                $expected = generateToken($email, $campaignId, $subscriberId);
                
                if (hash_equals($expected, $token)) {
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $db->prepare("UPDATE subscribers SET status = 'unsubscribed', updated_at = NOW() WHERE id = ?")->execute([$subscriberId]);
                        $db->prepare("UPDATE campaigns SET unsub_count = unsub_count + 1 WHERE id = ?")->execute([$campaignId]);
                        $this->assignDncTagAndClean($db, $subscriberId);
                        logActivity($subscriberId, 'unsub', "Unsubscribed via Campaign #{$campaignId}");
                        $success = true;
                    }
                } else {
                    $error = 'Invalid security token or expired link.';
                }
            } else {
                $error = 'Subscriber record not found.';
            }
        } else {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $email = strtolower(trim($_POST['email'] ?? ''));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $stSub = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
                    $stSub->execute([$email]);
                    $sub = $stSub->fetch();
                    
                    if ($sub) {
                        $subId = (int)$sub['id'];
                        $db->prepare("UPDATE subscribers SET status = 'unsubscribed', updated_at = NOW() WHERE id = ?")->execute([$subId]);
                        $this->assignDncTagAndClean($db, $subId);
                        logActivity($subId, 'unsub', "Unsubscribed via generic form");
                        $success = true;
                    } else {
                        $error = 'Email address not found in our directory.';
                    }
                } else {
                    $error = 'Invalid email address.';
                }
            }
        }

        $viewPath = dirname(__DIR__) . '/views/unsubscribe.php';
        include $viewPath;
    }

    /**
     * Set DO NOT CONTACT category, strip other tags and flush queue
     */
    private function assignDncTagAndClean(PDO $db, int $subId): void {
        $stCheck = $db->prepare("SELECT id FROM tags WHERE name = ?");
        $stCheck->execute(['DO NOT CONTACT']);
        $tagId = $stCheck->fetchColumn();
        
        if (!$tagId) {
            $stInsert = $db->prepare("INSERT INTO tags (name, color) VALUES (?, ?)");
            $stInsert->execute(['DO NOT CONTACT', '#d69e2e']);
            $tagId = (int)$db->lastInsertId();
        } else {
            $tagId = (int)$tagId;
        }
        
        // Strip previous tags
        $db->prepare("DELETE FROM subscriber_tags WHERE subscriber_id = ?")->execute([$subId]);
        
        // Assign DNC tag
        $db->prepare("INSERT INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)")->execute([$subId, $tagId]);
        
        // Flush queue
        $db->prepare("DELETE FROM email_queue WHERE subscriber_id = ? AND status = 'pending'")->execute([$subId]);
        $db->prepare("DELETE FROM automation_queue WHERE subscriber_id = ? AND status = 'pending'")->execute([$subId]);
    }

    public function checkEmail(): void {
        header('Content-Type: application/json');
        $email = strtolower(trim($_GET['email'] ?? ''));
        if ($email === '') {
            echo json_encode(['exists' => false, 'matches' => []]);
            exit;
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT id FROM subscribers WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $exists = $stmt->fetchColumn() !== false;

            // Fetch autocomplete suggestions
            $searchPattern = '%' . $email . '%';
            $stmtSuggest = $db->prepare("SELECT id, email, first_name, last_name FROM subscribers WHERE email LIKE ? OR first_name LIKE ? OR last_name LIKE ? LIMIT 5");
            $stmtSuggest->execute([$searchPattern, $searchPattern, $searchPattern]);
            $matches = $stmtSuggest->fetchAll();

            echo json_encode([
                'exists' => $exists,
                'matches' => $matches
            ]);
        } catch (Throwable $e) {
            echo json_encode(['exists' => false, 'matches' => [], 'error' => $e->getMessage()]);
        }
        exit;
    }
}
