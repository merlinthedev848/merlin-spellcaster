<?php
declare(strict_types=1);

// Initialize webhook endpoints database table
try {
    $db = Database::getConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS webhook_endpoints (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            url VARCHAR(512) NOT NULL,
            event_type VARCHAR(64) NOT NULL,
            active TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (PDOException $e) {
    error_log("Webhooks migration error: " . $e->getMessage());
}

// Helper Class to Send JSON Webhooks
class WebhookSender {
    public static function send(string $event, array $data): void {
        try {
            $db = Database::getConnection();
            $st = $db->prepare("SELECT url FROM webhook_endpoints WHERE event_type = ? AND active = 1");
            $st->execute([$event]);
            $urls = $st->fetchAll(PDO::FETCH_COLUMN);
            if (empty($urls)) return;

            // Fetch subscriber details
            $subId = (int)($data['subscriber_id'] ?? 0);
            if ($subId > 0) {
                $stSub = $db->prepare("SELECT email, first_name, last_name, status, created_at FROM subscribers WHERE id = ?");
                $stSub->execute([$subId]);
                $sub = $stSub->fetch();
                if ($sub) {
                    $data['subscriber'] = $sub;
                }
            }

            $payload = json_encode([
                'event' => $event,
                'timestamp' => date('Y-m-d H:i:s'),
                'data' => $data
            ]);

            foreach ($urls as $url) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Do not block request long
                curl_exec($ch);
            }
        } catch (Throwable $e) {
            error_log("Webhook dispatch error: " . $e->getMessage());
        }
    }
}

// Hook registrations
Hook::register('contact_added', function($data) {
    WebhookSender::send('contact_added', $data);
});
Hook::register('email_opened', function($data) {
    WebhookSender::send('email_opened', $data);
});
Hook::register('link_clicked', function($data) {
    WebhookSender::send('link_clicked', $data);
});

// Routing
if ($routePath === '/webhooks') {
    $db = Database::getConnection();
    $action = $_GET['action'] ?? '';
    $id = (int)($_GET['id'] ?? 0);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $event = trim($_POST['event_type'] ?? '');
            
            if ($name !== '' && filter_var($url, FILTER_VALIDATE_URL) && $event !== '') {
                $st = $db->prepare("INSERT INTO webhook_endpoints (name, url, event_type) VALUES (?, ?, ?)");
                $st->execute([$name, $url, $event]);
                $_SESSION['flash_success'] = 'Outbound webhook endpoint created.';
            } else {
                $_SESSION['flash_error'] = 'Invalid parameters. Please provide a valid URL.';
            }
            header('Location: ' . getSetting('app_url') . '/webhooks');
            exit;
        }

        if ($action === 'delete' && $id > 0) {
            $db->prepare("DELETE FROM webhook_endpoints WHERE id = ?")->execute([$id]);
            $_SESSION['flash_success'] = 'Webhook endpoint deleted.';
            header('Location: ' . getSetting('app_url') . '/webhooks');
            exit;
        }
    }

    // Fetch lists for incoming webhook helper
    $lists = $db->query("SELECT id, name FROM lists ORDER BY name ASC")->fetchAll();
    
    // Fetch outbound webhooks
    $endpoints = $db->query("SELECT * FROM webhook_endpoints ORDER BY created_at DESC")->fetchAll();

    $title = 'Webhook Integrations';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

if ($routePath === '/webhooks/incoming') {
    header('Content-Type: application/json');
    $db = Database::getConnection();

    // Accept raw JSON or POST values
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $email = strtolower(trim($data['email'] ?? ''));
    $listId = (int)($data['list_id'] ?? 0);
    $firstName = trim($data['first_name'] ?? '');
    $lastName = trim($data['last_name'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $listId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid email and list_id parameters are required.']);
        exit;
    }

    // Fire verification checks (syntax, disposable, MX checks)
    $hookData = ['email' => $email, 'valid' => true, 'error' => ''];
    Hook::fire('before_add_contact', $hookData);

    if (!$hookData['valid']) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Deliverability check failed: ' . $hookData['error']]);
        exit;
    }

    try {
        $db->beginTransaction();
        
        $st = $db->prepare("
            INSERT INTO subscribers (email, first_name, last_name, status, created_at) 
            VALUES (?, ?, ?, 'active', NOW()) 
            ON DUPLICATE KEY UPDATE 
                first_name = IF(first_name = '', VALUES(first_name), first_name),
                last_name = IF(last_name = '', VALUES(last_name), last_name),
                status = 'active'
        ");
        $st->execute([$email, $firstName, $lastName]);

        $stGet = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
        $stGet->execute([$email]);
        $subId = (int)$stGet->fetchColumn();

        // Assign to list
        $stList = $db->prepare("INSERT IGNORE INTO subscriber_lists (subscriber_id, list_id, status) VALUES (?, ?, 'confirmed')");
        $stList->execute([$subId, $listId]);

        logActivity($subId, 'subscribe', "Subscribed via Inbound API Webhook");
        $db->commit();

        // Trigger contact added hook
        Hook::fire('contact_added', ['subscriber_id' => $subId]);

        echo json_encode(['success' => true, 'subscriber_id' => $subId]);
    } catch (Throwable $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
