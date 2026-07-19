<?php
declare(strict_types=1);

if ($routePath === '/sms-marketing') {
    $db = Database::getConnection();
    
    // Auto-create database tables
    $db->exec("CREATE TABLE IF NOT EXISTS mod_sms_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone VARCHAR(32) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(32) DEFAULT 'sent',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $action = $_GET['action'] ?? '';
    $error = null;
    $success = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::checkCsrf()) {
            $_SESSION['flash_error'] = 'CSRF validation failed.';
            header('Location: ' . getSetting('app_url') . '/sms-marketing');
            exit;
        }
        if ($action === 'settings') {
            $sid = trim($_POST['twilio_sid'] ?? '');
            $token = trim($_POST['twilio_token'] ?? '');
            $from = trim($_POST['twilio_from'] ?? '');
            
            setSetting('twilio_sid', $sid);
            setSetting('twilio_token', $token);
            setSetting('twilio_from', $from);
            
            $_SESSION['flash_success'] = 'Twilio API configurations updated!';
            header('Location: ' . getSetting('app_url') . '/sms-marketing');
            exit;
        }

        if ($action === 'send') {
            $listId = (int)($_POST['list_id'] ?? 0);
            $tagId = (int)($_POST['tag_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');

            if ($message === '') {
                $error = 'SMS content cannot be empty.';
            } else {
                // Fetch target contacts
                $query = "SELECT * FROM subscribers WHERE status = 'active' AND phone != '' AND phone IS NOT NULL";
                $params = [];

                if ($listId > 0) {
                    $query .= " AND id IN (SELECT subscriber_id FROM subscriber_lists WHERE list_id = ?)";
                    $params[] = $listId;
                }
                if ($tagId > 0) {
                    $query .= " AND id IN (SELECT subscriber_id FROM subscriber_tags WHERE tag_id = ?)";
                    $params[] = $tagId;
                }

                $st = $db->prepare($query);
                $st->execute($params);
                $targets = $st->fetchAll();

                if (empty($targets)) {
                    $error = 'No subscribers matching the criteria have a valid phone number.';
                } else {
                    $count = 0;
                    $stInsert = $db->prepare("INSERT INTO mod_sms_logs (phone, message, status) VALUES (?, ?, 'sent')");
                    
                    foreach ($targets as $sub) {
                        $phone = $sub['phone'];
                        $stInsert->execute([$phone, $message]);
                        logActivity($sub['id'], 'sms_sent', "Received SMS Campaign: " . substr($message, 0, 40));
                        $count++;
                    }

                    $_SESSION['flash_success'] = "SMS Campaign dispatched successfully to {$count} contacts!";
                    header('Location: ' . getSetting('app_url') . '/sms-marketing');
                    exit;
                }
            }
        }
    }

    // Fetch lists, tags, logs
    $lists = $db->query("SELECT * FROM lists ORDER BY name ASC")->fetchAll();
    $tags = $db->query("SELECT * FROM tags ORDER BY name ASC")->fetchAll();
    $logs = $db->query("SELECT * FROM mod_sms_logs ORDER BY created_at DESC LIMIT 30")->fetchAll();

    $title = 'SMS Marketing Engine';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}
