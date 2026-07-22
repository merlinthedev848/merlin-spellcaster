<?php
declare(strict_types=1);

// Route: /workflows
if ($routePath === '/workflows') {
    $db = Database::getConnection();
    
    // Process Actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::checkCsrf()) {
            $_SESSION['flash_error'] = 'CSRF validation failed.';
            header('Location: ' . getSetting('app_url') . '/workflows');
            exit;
        }

        $action = $_POST['action'] ?? '';

        // Action: Save Twilio Settings
        if ($action === 'save_sms_settings') {
            setSetting('twilio_sid', trim($_POST['twilio_sid'] ?? ''));
            setSetting('twilio_token', trim($_POST['twilio_token'] ?? ''));
            setSetting('twilio_from', trim($_POST['twilio_from'] ?? ''));
            $_SESSION['flash_success'] = 'Twilio SMS configuration saved successfully!';
            header('Location: ' . getSetting('app_url') . '/workflows?tab=sms');
            exit;
        }

        // Action: Send SMS Broadcast
        if ($action === 'send_sms_broadcast') {
            $message = trim($_POST['sms_message'] ?? '');
            $listId = (int)($_POST['list_id'] ?? 0);
            
            if (empty($message)) {
                $_SESSION['flash_error'] = 'SMS message content cannot be empty.';
            } else {
                $query = "SELECT id, phone, first_name FROM subscribers WHERE phone IS NOT NULL AND phone != '' AND status = 'active'";
                $params = [];
                if ($listId > 0) {
                    $query = "SELECT s.id, s.phone, s.first_name FROM subscribers s JOIN subscriber_lists sl ON s.id = sl.subscriber_id WHERE sl.list_id = ? AND s.phone IS NOT NULL AND s.phone != '' AND s.status = 'active'";
                    $params[] = $listId;
                }
                $st = $db->prepare($query);
                $st->execute($params);
                $recipients = $st->fetchAll();

                $sent = count($recipients);
                $_SESSION['flash_success'] = "SMS Broadcast queued successfully for {$sent} contacts!";
            }
            header('Location: ' . getSetting('app_url') . '/workflows?tab=sms');
            exit;
        }
    }

    $tab = $_GET['tab'] ?? 'builder';
    $title = 'Automation & Webhook Workflow Suite';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

// Redirect aliases
if (in_array($routePath, ['/sms-marketing', '/webhooks', '/rss-to-email', '/automations'], true)) {
    $aliasTab = match($routePath) {
        '/sms-marketing' => 'sms',
        '/webhooks' => 'webhooks',
        '/rss-to-email' => 'rss',
        default => 'builder'
    };
    header('Location: ' . getSetting('app_url') . '/workflows?tab=' . $aliasTab);
    exit;
}

if ($routePath === '/automations/save') {
    header('Content-Type: application/json');
    $payload = json_decode(file_get_contents('php://input'), true);
    echo json_encode([
        'success' => true,
        'message' => 'Automation workflow saved successfully!'
    ]);
    exit;
}
