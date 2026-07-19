<?php
declare(strict_types=1);

/**
 * Controller for Campaigns, email templating, scheduling, open tracking, and click redirects
 */
class CampaignController {
    public function index(): void {
        $db = Database::getConnection();
        
        $action = $_GET['action'] ?? '';
        $id = (int)($_GET['id'] ?? 0);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf()) {
                $_SESSION['flash_error'] = 'CSRF validation failed.';
                header('Location: ' . getSetting('app_url') . '/campaigns');
                exit;
            }
            if ($action === 'delete' && $id > 0) {
                $db->prepare("DELETE FROM campaigns WHERE id = ?")->execute([$id]);
                $_SESSION['flash_success'] = 'Campaign deleted successfully.';
                header('Location: ' . getSetting('app_url') . '/campaigns');
                exit;
            }
            if ($action === 'pause' && $id > 0) {
                $db->prepare("UPDATE campaigns SET status = 'paused' WHERE id = ? AND status IN ('sending', 'queued')")->execute([$id]);
                $_SESSION['flash_success'] = 'Campaign paused.';
                header('Location: ' . getSetting('app_url') . '/campaigns');
                exit;
            }
            if ($action === 'resume' && $id > 0) {
                $db->prepare("UPDATE campaigns SET status = 'sending' WHERE id = ? AND status = 'paused'")->execute([$id]);
                $_SESSION['flash_success'] = 'Campaign sending resumed.';
                header('Location: ' . getSetting('app_url') . '/campaigns');
                exit;
            }
        }
        
        // Fetch all campaigns
        $st = $db->query("
            SELECT c.*, 
                   ROUND(c.open_count / NULLIF(c.send_count, 0) * 100, 1) as open_rate,
                   ROUND(c.click_count / NULLIF(c.send_count, 0) * 100, 1) as click_rate,
                   (SELECT COUNT(*) FROM email_queue eq WHERE eq.campaign_id = c.id AND eq.status = 'pending') as pending_count
            FROM campaigns c
            ORDER BY c.created_at DESC
        ");
        $campaigns = $st->fetchAll();
        
        $title = 'Campaigns';
        $viewPath = dirname(__DIR__) . '/views/campaigns.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    public function create(): void {
        $db = Database::getConnection();
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf()) {
                $error = 'CSRF validation failed.';
            } else {
                $name = trim($_POST['name'] ?? '');
                $subject = trim($_POST['subject'] ?? '');
                $listId = (int)($_POST['list_id'] ?? 0);
                $selectedTags = $_POST['tags'] ?? []; // array of tag IDs
            $scheduledAt = trim($_POST['scheduled_at'] ?? '');
            $bodyHtml = $_POST['body_html'] ?? '';
            $bodyText = $_POST['body_text'] ?? '';
            $includeUnsub = isset($_POST['include_unsubscribe']) ? 1 : 0;
            $maxPerHour = (int)($_POST['max_per_hour'] ?? 0);
            
            $saveDraft = isset($_POST['save_draft']);
            $scheduleSend = isset($_POST['send_now']);

            if ($name === '' || empty($bodyHtml)) {
                $error = 'Campaign name and HTML email content are required.';
            } else {
                try {
                    $db->beginTransaction();
                    
                    $finalSubject = $subject !== '' ? $subject : 'Campaign Announcement';
                    $finalText = $bodyText ?: strip_tags($bodyHtml);
                    $sqlSched = !empty($scheduledAt) ? date('Y-m-d H:i:s', strtotime($scheduledAt)) : null;
                    $initialStatus = $saveDraft ? 'draft' : 'queued';

                    // 1. Create campaign
                    $st = $db->prepare("
                        INSERT INTO campaigns (name, subject, body_html, body_text, status, scheduled_at, list_id, include_unsubscribe, max_per_hour, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $st->execute([$name, $finalSubject, $bodyHtml, $finalText, $initialStatus, $sqlSched, $listId, $includeUnsub, $maxPerHour]);
                    $campaignId = (int)$db->lastInsertId();

                    // Save campaign tags
                    if (!empty($selectedTags)) {
                        $stCampTag = $db->prepare("INSERT INTO campaign_tags (campaign_id, tag_id) VALUES (?, ?)");
                        foreach ($selectedTags as $tagId) {
                            $stCampTag->execute([$campaignId, (int)$tagId]);
                        }
                    }

                    // 2. Queue emails if schedule/send clicked
                    if ($scheduleSend) {
                        $this->queueCampaignEmails($db, $campaignId, $listId, $selectedTags, $sqlSched);
                    }

                    $hookData = ['campaign_id' => $campaignId, 'post_data' => $_POST];
                    Hook::fire('campaign_saved', $hookData);
                    $db->commit();
                    $_SESSION['flash_success'] = $scheduleSend ? 'Campaign saved and queued successfully!' : 'Campaign saved as draft.';
                    header('Location: ' . getSetting('app_url') . '/campaigns');
                    exit;

                } catch (Throwable $e) {
                    $db->rollBack();
                    $error = 'Failed to create campaign: ' . $e->getMessage();
                }
            }
            }
        }

        // Fetch assets
        $templates = $db->query("SELECT id, name, subject, body_html, body_text FROM templates ORDER BY name ASC")->fetchAll();
        $lists = $db->query("SELECT * FROM lists ORDER BY name ASC")->fetchAll();
        $tags = $db->query("SELECT * FROM tags ORDER BY name ASC")->fetchAll();

        $title = 'Create Campaign';
        $isEdit = false;
        $viewPath = dirname(__DIR__) . '/views/campaign_create.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    public function edit(): void {
        $db = Database::getConnection();
        $id = (int)($_GET['id'] ?? 0);
        $error = null;

        // Fetch campaign
        $st = $db->prepare("SELECT * FROM campaigns WHERE id = ?");
        $st->execute([$id]);
        $campaign = $st->fetch();

        if (!$campaign) {
            $_SESSION['flash_error'] = 'Campaign not found.';
            header('Location: ' . getSetting('app_url') . '/campaigns');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf()) {
                $error = 'CSRF validation failed.';
            } else {
                $name = trim($_POST['name'] ?? '');
                $subject = trim($_POST['subject'] ?? '');
                $listId = (int)($_POST['list_id'] ?? 0);
                $selectedTags = $_POST['tags'] ?? [];
                $scheduledAt = trim($_POST['scheduled_at'] ?? '');
            $bodyHtml = $_POST['body_html'] ?? '';
            $bodyText = $_POST['body_text'] ?? '';
            $includeUnsub = isset($_POST['include_unsubscribe']) ? 1 : 0;
            $maxPerHour = (int)($_POST['max_per_hour'] ?? 0);
            
            $saveDraft = isset($_POST['save_draft']);
            $scheduleSend = isset($_POST['send_now']);
            $saveCampaign = isset($_POST['save_campaign']);

            if ($name === '' || empty($bodyHtml)) {
                $error = 'Campaign name and HTML content are required.';
            } else {
                try {
                    $db->beginTransaction();

                    $finalSubject = $subject !== '' ? $subject : 'Campaign Announcement';
                    $finalText = $bodyText ?: strip_tags($bodyHtml);
                    $sqlSched = !empty($scheduledAt) ? date('Y-m-d H:i:s', strtotime($scheduledAt)) : null;

                    $newStatus = $saveDraft ? 'draft' : ($scheduleSend ? 'queued' : $campaign['status']);

                    // Update campaign
                    $stUpdate = $db->prepare("
                        UPDATE campaigns 
                        SET name = ?, subject = ?, body_html = ?, body_text = ?, status = ?, scheduled_at = ?, list_id = ?, include_unsubscribe = ?, max_per_hour = ?
                        WHERE id = ?
                    ");
                    $stUpdate->execute([$name, $finalSubject, $bodyHtml, $finalText, $newStatus, $sqlSched, $listId, $includeUnsub, $maxPerHour, $id]);

                    // Update tags
                    $db->prepare("DELETE FROM campaign_tags WHERE campaign_id = ?")->execute([$id]);
                    if (!empty($selectedTags)) {
                        $stCampTag = $db->prepare("INSERT INTO campaign_tags (campaign_id, tag_id) VALUES (?, ?)");
                        foreach ($selectedTags as $tagId) {
                            $stCampTag->execute([$id, (int)$tagId]);
                        }
                    }

                    // Flush old pending queue if explicitly rescheduled/re-queued (Save Draft / Send Now)
                    if ($saveDraft || $scheduleSend) {
                        $db->prepare("DELETE FROM email_queue WHERE campaign_id = ? AND status = 'pending'")->execute([$id]);
                    }

                    if ($scheduleSend) {
                        $this->queueCampaignEmails($db, $id, $listId, $selectedTags, $sqlSched);
                    }

                    $hookData = ['campaign_id' => $id, 'post_data' => $_POST];
                    Hook::fire('campaign_saved', $hookData);
                    $db->commit();
                    $_SESSION['flash_success'] = 'Campaign updated successfully.';
                    header('Location: ' . getSetting('app_url') . '/campaigns');
                    exit;

                } catch (Throwable $e) {
                    $db->rollBack();
                    $error = 'Failed to update campaign: ' . $e->getMessage();
                }
            }
        }
        }

        // Fetch prefilled tags
        $stCampTags = $db->prepare("SELECT tag_id FROM campaign_tags WHERE campaign_id = ?");
        $stCampTags->execute([$id]);
        $campaignTagIds = $stCampTags->fetchAll(PDO::FETCH_COLUMN);

        $templates = $db->query("SELECT id, name, subject, body_html, body_text FROM templates ORDER BY name ASC")->fetchAll();
        
        // Find matching template ID by HTML body content (if any, otherwise 0)
        $matchedTemplateId = 0;
        foreach ($templates as $temp) {
            if ($temp['body_html'] === $campaign['body_html']) {
                $matchedTemplateId = (int)$temp['id'];
                break;
            }
        }

        $lists = $db->query("SELECT * FROM lists ORDER BY name ASC")->fetchAll();
        $tags = $db->query("SELECT * FROM tags ORDER BY name ASC")->fetchAll();

        $title = 'Edit Campaign';
        $isEdit = true;
        $viewPath = dirname(__DIR__) . '/views/campaign_create.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    /**
     * Build target recipient queue
     */
    private function queueCampaignEmails(PDO $db, int $campaignId, int $listId, array $targetTagIds, ?string $sendAt): void {
        $query = "SELECT DISTINCT s.id FROM subscribers s";
        $joins = [];
        $wheres = ["s.status = 'active'"];
        $params = [];

        if ($listId > 0) {
            $joins[] = "JOIN subscriber_lists sl ON sl.subscriber_id = s.id";
            $wheres[] = "sl.list_id = ?";
            $params[] = $listId;
        }

        if (!empty($targetTagIds)) {
            $joins[] = "JOIN subscriber_tags stg ON stg.subscriber_id = s.id";
            $placeholders = implode(',', array_fill(0, count($targetTagIds), '?'));
            $wheres[] = "stg.tag_id IN ($placeholders)";
            $params = array_merge($params, array_map('intval', $targetTagIds));
        }

        if (!empty($joins)) {
            $query .= " " . implode(" ", $joins);
        }
        
        $query .= " WHERE " . implode(" AND ", $wheres);
        
        $stSubs = $db->prepare($query);
        $stSubs->execute($params);
        $subs = $stSubs->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($subs)) {
            $sendAtVal = $sendAt ?: date('Y-m-d H:i:s');
            
            // Generate a backend automation to act as the "brain" of the campaign broadcast
            $autoName = "System: Campaign Broadcast #" . $campaignId . " (" . date('M j') . ")";
            $db->prepare("INSERT INTO automations (name, trigger_event, status) VALUES (?, 'system_broadcast', 'active')")
               ->execute([$autoName]);
            $autoId = (int)$db->lastInsertId();

            $db->prepare("INSERT INTO automation_steps (automation_id, step_type, step_value, order_num) VALUES (?, 'send_email', ?, 1)")
               ->execute([$autoId, $campaignId]);
            $stepId = (int)$db->lastInsertId();

            $stQueue = $db->prepare("
                INSERT INTO automation_queue (automation_id, subscriber_id, step_id, status, execute_at) 
                VALUES (?, ?, ?, 'pending', ?)
            ");
            foreach ($subs as $subId) {
                $stQueue->execute([$autoId, (int)$subId, $stepId, $sendAtVal]);
            }
        } else {
            // Keep draft if empty targets
            $db->prepare("UPDATE campaigns SET status = 'draft' WHERE id = ?")->execute([$campaignId]);
            throw new RuntimeException("Target segment (List and Tags) has no active subscribers. Saved as draft.");
        }
    }

    /**
     * Serves 1x1 transparent open-tracking GIF
     */
    public function trackOpen(): void {
        header('Content-Type: image/gif');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (ob_get_level() > 0) ob_end_flush();
            flush();
        }

        $c = (int)($_GET['c'] ?? 0);
        $s = (int)($_GET['s'] ?? 0);
        $t = $_GET['t'] ?? '';

        if (!$c || !$s || !$t) exit;

        if (getSetting('tracking_enabled', '1') !== '1') exit;

        try {
            $db = Database::getConnection();

            $stSub = $db->prepare("SELECT email FROM subscribers WHERE id = ?");
            $stSub->execute([$s]);
            $email = $stSub->fetchColumn();
            if (!$email) exit;

            $expected = generateToken((string)$email, $c, $s);
            if (!hash_equals($expected, $t)) exit;

            $stUnique = $db->prepare("SELECT COUNT(*) FROM campaign_opens WHERE campaign_id = ? AND subscriber_id = ?");
            $stUnique->execute([$c, $s]);
            $isUnique = ((int)$stUnique->fetchColumn()) === 0;

            $ipAddr = $_SERVER['REMOTE_ADDR'] ?? '';
            $db->prepare("
                INSERT INTO campaign_opens (campaign_id, subscriber_id, ip_address, user_agent, opened_at) 
                VALUES (?, ?, ?, ?, NOW())
            ")->execute([$c, $s, $ipAddr, $_SERVER['HTTP_USER_AGENT'] ?? '']);

            if ($ipAddr !== '') {
                sc_update_subscriber_geoip($s, $ipAddr);
            }

            if ($isUnique) {
                $db->prepare("UPDATE campaigns SET open_count = open_count + 1 WHERE id = ?")->execute([$c]);
                logActivity($s, 'open', "Opened Campaign #{$c} (Unique)");
                
                // Fire  workflow automations for email open
                Automation::trigger("email_open:{$c}", $s);
                
                $hookData = ['campaign_id' => $c, 'subscriber_id' => $s];
                Hook::fire('email_opened', $hookData);
            } else {
                logActivity($s, 'open', "Opened Campaign #{$c} (Repeat)");
            }

        } catch (Throwable $e) {
            error_log("trackOpen error: " . $e->getMessage());
        }
        exit;
    }

    /**
     * Tracks link clicks and redirects user
     */
    public function trackClick(): void {
        $db = Database::getConnection();
        
        $c = (int)($_GET['c'] ?? 0);
        $s = (int)($_GET['s'] ?? 0);
        $t = trim($_GET['t'] ?? '');
        $url = trim($_GET['url'] ?? '');

        $target = $url ?: '/';
        if (!filter_var($target, FILTER_VALIDATE_URL)) {
            $target = '/';
        } else {
            $parsedUrl = parse_url($target);
            if (!in_array(strtolower($parsedUrl['scheme'] ?? ''), ['http', 'https'], true)) {
                $target = '/';
            }
        }

        header('Location: ' . $target);
        header('Cache-Control: no-cache');

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (ob_get_level() > 0) ob_end_flush();
            flush();
        }

        if (!$c || !$s || !$t || !$url) exit;

        if (getSetting('tracking_enabled', '1') !== '1') exit;

        try {
            $stSub = $db->prepare("SELECT email FROM subscribers WHERE id = ?");
            $stSub->execute([$s]);
            $email = $stSub->fetchColumn();
            if (!$email) exit;

            $expected = generateToken((string)$email, $c, $s);
            if (!hash_equals($expected, $t)) exit;

            $stUnique = $db->prepare("SELECT COUNT(*) FROM campaign_clicks WHERE campaign_id = ? AND subscriber_id = ? AND url = ?");
            $stUnique->execute([$c, $s, $url]);
            $isUnique = ((int)$stUnique->fetchColumn()) === 0;

            $ipAddr = $_SERVER['REMOTE_ADDR'] ?? '';
            $db->prepare("
                INSERT INTO campaign_clicks (campaign_id, subscriber_id, url, ip_address, clicked_at) 
                VALUES (?, ?, ?, ?, NOW())
            ")->execute([$c, $s, $url, $ipAddr]);

            if ($ipAddr !== '') {
                sc_update_subscriber_geoip($s, $ipAddr);
            }

            if ($isUnique) {
                $db->prepare("UPDATE campaigns SET click_count = click_count + 1 WHERE id = ?")->execute([$c]);
                logActivity($s, 'click', "Clicked link in Campaign #{$c}: {$url} (Unique)");
                
                // Fire  workflow automations for link click
                Automation::trigger("link_click:{$c}", $s);
                
                $hookData = ['campaign_id' => $c, 'subscriber_id' => $s, 'url' => $url];
                Hook::fire('link_clicked', $hookData);
            } else {
                logActivity($s, 'click', "Clicked link in Campaign #{$c}: {$url} (Repeat)");
            }

        } catch (Throwable $e) {
            error_log("trackClick error: " . $e->getMessage());
        }
        exit;
    }
}
