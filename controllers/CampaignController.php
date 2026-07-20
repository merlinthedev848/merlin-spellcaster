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
            if ($action === 'toggle_status' && $id > 0) {
                $newStatus = trim($_POST['status'] ?? 'active');
                if (!in_array($newStatus, ['active', 'inactive', 'draft'], true)) {
                    $newStatus = 'active';
                }
                $db->prepare("UPDATE campaigns SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
                if ($newStatus === 'active') {
                    self::syncActiveCampaigns($db, $id);
                    $_SESSION['flash_success'] = 'Campaign activated! Autopilot is now constantly monitoring for new contacts.';
                } else {
                    $_SESSION['flash_success'] = 'Campaign deactivated.';
                }
                header('Location: ' . getSetting('app_url') . '/campaigns');
                exit;
            }
            if ($action === 'pause' && $id > 0) {
                $db->prepare("UPDATE campaigns SET status = 'inactive' WHERE id = ?")->execute([$id]);
                $_SESSION['flash_success'] = 'Campaign deactivated.';
                header('Location: ' . getSetting('app_url') . '/campaigns');
                exit;
            }
            if ($action === 'resume' && $id > 0) {
                $db->prepare("UPDATE campaigns SET status = 'active' WHERE id = ?")->execute([$id]);
                self::syncActiveCampaigns($db, $id);
                $_SESSION['flash_success'] = 'Campaign activated and Autopilot sync triggered!';
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
                    $initialStatus = $saveDraft ? 'draft' : 'active';

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

                    $hookData = ['campaign_id' => $campaignId, 'post_data' => $_POST];
                    Hook::fire('campaign_saved', $hookData);
                    $db->commit();

                    if ($scheduleSend) {
                        self::syncActiveCampaigns($db, $campaignId);
                    }

                    $_SESSION['flash_success'] = $scheduleSend ? 'Campaign saved and Autopilot sending activated!' : 'Campaign saved as draft.';
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

            if ($name === '' || empty($bodyHtml)) {
                $error = 'Campaign name and HTML content are required.';
            } else {
                try {
                    $db->beginTransaction();

                    $finalSubject = $subject !== '' ? $subject : 'Campaign Announcement';
                    $finalText = $bodyText ?: strip_tags($bodyHtml);
                    $sqlSched = !empty($scheduledAt) ? date('Y-m-d H:i:s', strtotime($scheduledAt)) : null;

                    $newStatus = $saveDraft ? 'draft' : ($scheduleSend ? 'active' : $campaign['status']);

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

                    $hookData = ['campaign_id' => $id, 'post_data' => $_POST];
                    Hook::fire('campaign_saved', $hookData);
                    $db->commit();

                    if ($scheduleSend || $newStatus === 'active') {
                        self::syncActiveCampaigns($db, $id);
                    }

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
     * Autopilot Sync Engine: Scans active campaigns, identifies subscribers matching 
     * list/tag segment targeting who have not yet been targeted for this campaign, 
     * and queues them into the backend automation workflow engine.
     */
    public static function syncActiveCampaigns(PDO $db, int $targetCampaignId = 0): int {
        $queuedTotal = 0;
        
        try {
            $sql = "SELECT * FROM campaigns WHERE status IN ('active', 'queued', 'sending') AND (scheduled_at IS NULL OR scheduled_at <= NOW())";
            $params = [];
            if ($targetCampaignId > 0) {
                $sql .= " AND id = ?";
                $params[] = $targetCampaignId;
            }
            
            $st = $db->prepare($sql);
            $st->execute($params);
            $activeCampaigns = $st->fetchAll();
            
            foreach ($activeCampaigns as $camp) {
                $campId = (int)$camp['id'];
                $listId = (int)$camp['list_id'];
                $sendAtVal = !empty($camp['scheduled_at']) ? $camp['scheduled_at'] : date('Y-m-d H:i:s');
                
                // Fetch target tag IDs
                $stTags = $db->prepare("SELECT tag_id FROM campaign_tags WHERE campaign_id = ?");
                $stTags->execute([$campId]);
                $targetTagIds = $stTags->fetchAll(PDO::FETCH_COLUMN);
                
                // Build query for active subscribers
                $query = "SELECT DISTINCT s.id FROM subscribers s";
                $joins = [];
                $wheres = ["s.status = 'active'"];
                $queryParams = [];
                
                if ($listId > 0) {
                    $joins[] = "JOIN subscriber_lists sl ON sl.subscriber_id = s.id";
                    $wheres[] = "sl.list_id = ?";
                    $queryParams[] = $listId;
                }
                
                if (!empty($targetTagIds)) {
                    $joins[] = "JOIN subscriber_tags stg ON stg.subscriber_id = s.id";
                    $placeholders = implode(',', array_fill(0, count($targetTagIds), '?'));
                    $wheres[] = "stg.tag_id IN ($placeholders)";
                    $queryParams = array_merge($queryParams, array_map('intval', $targetTagIds));
                }
                
                // EXCLUDE subscribers already queued/sent for this campaign
                $wheres[] = "s.id NOT IN (
                    SELECT aq.subscriber_id FROM automation_queue aq 
                    JOIN automation_steps ast ON ast.id = aq.step_id 
                    WHERE ast.step_type = 'send_email' AND ast.step_value = ?
                )";
                $queryParams[] = (string)$campId;
                
                $wheres[] = "s.id NOT IN (
                    SELECT eq.subscriber_id FROM email_queue eq WHERE eq.campaign_id = ?
                )";
                $queryParams[] = $campId;

                if (!empty($joins)) {
                    $query .= " " . implode(" ", $joins);
                }
                $query .= " WHERE " . implode(" AND ", $wheres);
                
                $stSubs = $db->prepare($query);
                $stSubs->execute($queryParams);
                $unprocessedSubs = $stSubs->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($unprocessedSubs)) {
                    continue;
                }
                
                // Find or create backend automation brain
                $stAuto = $db->prepare("SELECT id FROM automations WHERE trigger_event = 'system_broadcast' AND name LIKE ? LIMIT 1");
                $stAuto->execute(["System: Campaign Broadcast #{$campId}%"]);
                $autoId = (int)$stAuto->fetchColumn();
                
                if (!$autoId) {
                    $autoName = "System: Campaign Broadcast #" . $campId;
                    $db->prepare("INSERT INTO automations (name, trigger_event, status) VALUES (?, 'system_broadcast', 'active')")
                       ->execute([$autoName]);
                    $autoId = (int)$db->lastInsertId();
                    
                    $db->prepare("INSERT INTO automation_steps (automation_id, step_type, step_value, order_num) VALUES (?, 'send_email', ?, 1)")
                       ->execute([$autoId, (string)$campId]);
                    $stepId = (int)$db->lastInsertId();
                } else {
                    $stStep = $db->prepare("SELECT id FROM automation_steps WHERE automation_id = ? AND step_type = 'send_email' LIMIT 1");
                    $stStep->execute([$autoId]);
                    $stepId = (int)$stStep->fetchColumn();
                }
                
                // Queue the new subscribers
                $stQueue = $db->prepare("
                    INSERT IGNORE INTO automation_queue (automation_id, subscriber_id, step_id, status, execute_at) 
                    VALUES (?, ?, ?, 'pending', ?)
                ");
                
                foreach ($unprocessedSubs as $subId) {
                    $stQueue->execute([$autoId, (int)$subId, $stepId, $sendAtVal]);
                    $queuedTotal++;
                }
                
                // Ensure campaign status stays 'active'
                $db->prepare("UPDATE campaigns SET status = 'active' WHERE id = ?")->execute([$campId]);
            }
        } catch (Throwable $e) {
            error_log("syncActiveCampaigns Error: " . $e->getMessage());
        }
        
        return $queuedTotal;
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
        $action = trim($_GET['action'] ?? '');

        $target = $url ?: '/';
        if (!filter_var($target, FILTER_VALIDATE_URL)) {
            $target = '/';
        } else {
            $parsedUrl = parse_url($target);
            if (!in_array(strtolower($parsedUrl['scheme'] ?? ''), ['http', 'https'], true)) {
                $target = '/';
            }
        }

        // 1. Process JS-initiated logging (POST action=log)
        if ($action === 'log') {
            header('Content-Type: application/json');
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $this->logClickInDatabase($db, $c, $s, $t, $url, 'js');
            echo json_encode(['success' => true]);
            exit;
        }

        // 2. Process non-JS fallback logging (noscript)
        if ($action === 'noscript_log') {
            $this->logClickInDatabase($db, $c, $s, $t, $url, 'noscript');
            header('Location: ' . $target);
            exit;
        }

        // 3. User-Agent filtering for known link scanner bots
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isBot = false;
        $botKeywords = [
            'bot', 'crawl', 'spider', 'slurp', 'yahoo', 'google', 'facebook', 'baidu', 'bing', 
            'outlook', 'safelinks', 'barracuda', 'proofpoint', 'zscaler', 'trendmicro', 
            'mcafee', 'fortinet', 'fireeye', 'cisco', 'sophos', 'kaspersky', 'avast', 
            'symantec', 'defender', 'microsoft', 'crawler', 'scan', 'wget', 'curl', 
            'python', 'http', 'go-http', 'java', 'okhttp', 'node', 'php'
        ];
        foreach ($botKeywords as $keyword) {
            if (stripos($userAgent, $keyword) !== false) {
                $isBot = true;
                break;
            }
        }

        if ($isBot) {
            // Known bot/scanner: redirect immediately without logging
            header('Location: ' . $target);
            exit;
        }

        // 4. Render transition page for real humans
        $appUrl = getSetting('app_url', (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]");
        $appUrl = rtrim($appUrl, '/');
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Redirecting...</title>
            <noscript>
                <meta http-equiv="refresh" content="0;url=<?= e($appUrl . '/r?action=noscript_log&c=' . $c . '&s=' . $s . '&t=' . $t . '&url=' . urlencode($url)) ?>">
            </noscript>
            <style>
                body {
                    background-color: #0b0f19;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    height: 100vh;
                    margin: 0;
                    font-family: system-ui, -apple-system, sans-serif;
                    color: #ffffff;
                }
                .container {
                    text-align: center;
                    padding: 30px;
                }
                .spinner {
                    width: 40px;
                    height: 40px;
                    border: 3px solid rgba(99, 91, 255, 0.15);
                    border-top: 3px solid #635bff;
                    border-radius: 50%;
                    display: inline-block;
                    animation: spin 0.8s linear infinite;
                    margin-bottom: 20px;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                h2 {
                    font-size: 15px;
                    font-weight: 600;
                    margin: 0 0 8px 0;
                    color: #ffffff;
                }
                p {
                    font-size: 12px;
                    color: #adbdcc;
                    margin: 0;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="spinner"></div>
                <h2>Taking you to your destination</h2>
                <p>Verifying secure link connection...</p>
            </div>

            <script>
            (function() {
                const target = <?= json_encode($target) ?>;
                const logUrl = <?= json_encode($appUrl . '/r?action=log&c=' . $c . '&s=' . $s . '&t=' . $t . '&url=' . urlencode($url)) ?>;
                let redirected = false;

                function go() {
                    if (!redirected) {
                        redirected = true;
                        window.location.replace(target);
                    }
                }

                fetch(logUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({}),
                    keepalive: true
                })
                .then(go)
                .catch(go);

                setTimeout(go, 250);
            })();
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    private function logClickInDatabase(PDO $db, int $c, int $s, string $t, string $url, string $clickType): void {
        if (!$c || !$s || !$t || !$url) return;
        if (getSetting('tracking_enabled', '1') !== '1') return;

        try {
            $stSub = $db->prepare("SELECT email FROM subscribers WHERE id = ?");
            $stSub->execute([$s]);
            $email = $stSub->fetchColumn();
            if (!$email) return;

            $expected = generateToken((string)$email, $c, $s);
            if (!hash_equals($expected, $t)) return;

            $stUnique = $db->prepare("SELECT COUNT(*) FROM campaign_clicks WHERE campaign_id = ? AND subscriber_id = ? AND url = ?");
            $stUnique->execute([$c, $s, $url]);
            $isUnique = ((int)$stUnique->fetchColumn()) === 0;

            $ipAddr = $_SERVER['REMOTE_ADDR'] ?? '';
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
            $referrer = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 1024);

            $db->prepare("
                INSERT INTO campaign_clicks (campaign_id, subscriber_id, url, ip_address, user_agent, referrer, click_type, clicked_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([$c, $s, $url, $ipAddr, $userAgent, $referrer, $clickType]);

            if ($ipAddr !== '') {
                sc_update_subscriber_geoip($s, $ipAddr);
            }

            if ($isUnique) {
                $db->prepare("UPDATE campaigns SET click_count = click_count + 1 WHERE id = ?")->execute([$c]);
                logActivity($s, 'click', "Clicked link in Campaign #{$c}: {$url} (Unique)");
                
                // Fire workflow automations for link click
                Automation::trigger("link_click:{$c}", $s);
                
                $hookData = ['campaign_id' => $c, 'subscriber_id' => $s, 'url' => $url];
                Hook::fire('link_clicked', $hookData);
            } else {
                logActivity($s, 'click', "Clicked link in Campaign #{$c}: {$url} (Repeat)");
            }
        } catch (Throwable $e) {
            error_log("logClickInDatabase error: " . $e->getMessage());
        }
    }
}
