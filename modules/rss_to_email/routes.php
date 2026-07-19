<?php
declare(strict_types=1);

// Bootstrap RSS feeds database table
try {
    $db = Database::getConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS mod_rss_feeds (
            id INT AUTO_INCREMENT PRIMARY KEY,
            feed_url VARCHAR(255) NOT NULL,
            list_id INT NOT NULL,
            frequency VARCHAR(50) DEFAULT 'weekly',
            last_checked_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (PDOException $e) {
    error_log("RSS-to-Email database bootstrap error: " . $e->getMessage());
}

// Route: /rss (Admin dashboard page)
if ($routePath === '/rss') {
    $db = Database::getConnection();
    $action = $_GET['action'] ?? '';
    $id = (int)($_GET['id'] ?? 0);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::checkCsrf()) {
            $_SESSION['flash_error'] = 'CSRF validation failed.';
            header('Location: ' . getSetting('app_url') . '/rss');
            exit;
        }
        if ($action === 'create') {
            $feedUrl = trim($_POST['feed_url'] ?? '');
            $listId = (int)($_POST['list_id'] ?? 0);
            $freq = trim($_POST['frequency'] ?? 'weekly');

            if (filter_var($feedUrl, FILTER_VALIDATE_URL) && $listId > 0) {
                $st = $db->prepare("INSERT INTO mod_rss_feeds (feed_url, list_id, frequency) VALUES (?, ?, ?)");
                $st->execute([$feedUrl, $listId, $freq]);
                $_SESSION['flash_success'] = 'RSS Feed subscription successfully registered.';
            } else {
                $_SESSION['flash_error'] = 'Invalid parameters. Please specify a valid Feed URL and target list.';
            }
            header('Location: ' . getSetting('app_url') . '/rss');
            exit;
        }

        if ($action === 'delete' && $id > 0) {
            $db->prepare("DELETE FROM mod_rss_feeds WHERE id = ?")->execute([$id]);
            $_SESSION['flash_success'] = 'RSS Feed subscription removed.';
            header('Location: ' . getSetting('app_url') . '/rss');
            exit;
        }
    }

    // Fetch lists
    $lists = $db->query("SELECT id, name FROM lists ORDER BY name ASC")->fetchAll();

    // Fetch RSS feeds
    $feeds = $db->query("
        SELECT f.*, l.name as list_name 
        FROM mod_rss_feeds f 
        JOIN lists l ON f.list_id = l.id 
        ORDER BY f.id DESC
    ")->fetchAll();

    $title = 'Automated RSS-to-Email';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

// Route: /rss/run (Trigger RSS pull and campaign auto-generation)
if ($routePath === '/rss/run') {
    header('Content-Type: application/json');
    
    $provided = $_GET['secret'] ?? '';
    $secret = getSetting('cron_secret');
    if ($secret !== '' && !hash_equals($secret, $provided)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid cron secret.']);
        exit;
    }

    $db = Database::getConnection();
    $summary = [];

    try {
        $feeds = $db->query("SELECT * FROM mod_rss_feeds")->fetchAll();

        foreach ($feeds as $feed) {
            $feedId = (int)$feed['id'];
            $feedUrl = $feed['feed_url'];
            $listId = (int)$feed['list_id'];

            $content = @file_get_contents($feedUrl);
            if (!$content) {
                $summary[] = "Feed {$feedUrl}: Failed to retrieve.";
                continue;
            }

            $xml = @simplexml_load_string($content);
            if (!$xml) {
                $summary[] = "Feed {$feedUrl}: XML parse error.";
                continue;
            }

            $item = $xml->channel->item[0] ?? null;
            if ($item) {
                $title = trim((string)$item->title);
                $link = trim((string)$item->link);
                $desc = trim(strip_tags((string)$item->description));
                
                $campaignName = "RSS Auto: " . $title;
                $subject = "Latest Post: " . $title;

                // Check if campaign already exists for this post
                $stCheck = $db->prepare("SELECT COUNT(*) FROM campaigns WHERE subject = ?");
                $stCheck->execute([$subject]);
                $exists = ((int)$stCheck->fetchColumn()) > 0;

                if (!$exists) {
                    $html = "<h2>" . e($title) . "</h2>";
                    if ($desc !== '') {
                        $html .= "<p>" . e($desc) . "</p>";
                    }
                    $html .= "<br><a href='" . e($link) . "' style='display:inline-block; padding:10px 20px; background:#635bff; color:#ffffff; text-decoration:none; border-radius:6px; font-weight:600;'>Read Full Post →</a>";

                    $db->beginTransaction();

                    // Create campaign
                    $stInsert = $db->prepare("
                        INSERT INTO campaigns (name, subject, body_html, body_text, status, list_id, include_unsubscribe, max_per_hour, created_at) 
                        VALUES (?, ?, ?, ?, 'sending', ?, 1, 0, NOW())
                    ");
                    $stInsert->execute([$campaignName, $subject, $html, $desc, $listId]);
                    $campaignId = (int)$db->lastInsertId();

                    // Queue emails instantly to list members
                    $db->prepare("
                        INSERT IGNORE INTO email_queue (campaign_id, subscriber_id, send_at)
                        SELECT ?, s.id, NOW()
                        FROM subscribers s
                        JOIN subscriber_lists sl ON s.id = sl.subscriber_id
                        WHERE sl.list_id = ? AND s.status = 'active' AND sl.status = 'confirmed'
                    ")->execute([$campaignId, $listId]);

                    $db->commit();

                    $summary[] = "Feed {$feedUrl}: Generated Campaign #{$campaignId} for '{$title}'";
                } else {
                    $summary[] = "Feed {$feedUrl}: No new posts (already processed '{$title}')";
                }
            }

            $db->prepare("UPDATE mod_rss_feeds SET last_checked_at = NOW() WHERE id = ?")->execute([$feedId]);
        }

        echo json_encode(['success' => true, 'summary' => $summary]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
