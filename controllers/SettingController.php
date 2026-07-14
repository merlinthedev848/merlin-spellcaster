<?php
declare(strict_types=1);

/**
 * Controller for Application Settings, System Diagnostics, Cron processing, and Mailgun Webhooks
 */
class SettingController {
    public function index(): void {
        $db = Database::getConnection();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach ($_POST as $key => $value) {
                if (str_starts_with($key, 'setting_')) {
                    $settingKey = substr($key, 8);
                    setSetting($settingKey, trim((string)$value));
                }
            }
            $_SESSION['flash_success'] = 'Settings saved successfully!';
            header('Location: ' . getSetting('app_url') . '/settings');
            exit;
        }

        $title = 'Settings';
        $viewPath = dirname(__DIR__) . '/views/settings.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    public function diagnostics(): void {
        $db = Database::getConnection();
        
        $checks = [];
        
        // 1. PHP Version Check
        $phpVersion = PHP_VERSION;
        $checks['php'] = [
            'name' => 'PHP Version',
            'status' => version_compare($phpVersion, '8.5.0', '>=') ? 'pass' : 'warn',
            'message' => "Running PHP {$phpVersion}. Recommended: PHP 8.5+ for optimal speed."
        ];

        // 2. Database Connection
        try {
            $db->query("SELECT 1");
            $checks['db'] = [
                'name' => 'Database Connection',
                'status' => 'pass',
                'message' => 'Connected successfully to MySQL.'
            ];
        } catch (Throwable $e) {
            $checks['db'] = [
                'name' => 'Database Connection',
                'status' => 'fail',
                'message' => 'Failed connecting: ' . $e->getMessage()
            ];
        }

        // 3. Write Permissions for Config
        $configFile = dirname(__DIR__) . '/config.local.php';
        $checks['config_write'] = [
            'name' => 'Config Write Access',
            'status' => is_writable($configFile) ? 'pass' : 'warn',
            'message' => is_writable($configFile) ? 'config.local.php is writable.' : 'config.local.php is read-only (Secure for production, but blocks setup edits).'
        ];

        // 4. Upload Directory Permissions
        $uploadDir = dirname(__DIR__) . '/uploads';
        if (!file_exists($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $checks['upload_write'] = [
            'name' => 'Uploads Directory',
            'status' => is_writable($uploadDir) ? 'pass' : 'fail',
            'message' => is_writable($uploadDir) ? 'uploads/ folder is writable.' : 'uploads/ folder is not writable. Check file permissions.'
        ];

        // 5. Test SMTP connection handshake (no email sent, just connection checks)
        $smtpHost = getSetting('smtp_host', 'localhost');
        $smtpPort = (int)getSetting('smtp_port', '587');
        $smtpEnc = strtolower(getSetting('smtp_encryption', 'tls'));
        
        try {
            $prefix = ($smtpEnc === 'ssl') ? 'ssl://' : 'tcp://';
            $context = stream_context_create([
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed'=> true]
            ]);
            $socket = @stream_socket_client($prefix . $smtpHost . ':' . $smtpPort, $errno, $errstr, 4, STREAM_CLIENT_CONNECT, $context);
            if ($socket) {
                fclose($socket);
                $checks['smtp'] = [
                    'name' => 'SMTP Handshake',
                    'status' => 'pass',
                    'message' => "Socket opened successfully to {$smtpHost}:{$smtpPort} ✓"
                ];
            } else {
                throw new RuntimeException("{$errstr} (#{$errno})");
            }
        } catch (Throwable $e) {
            $checks['smtp'] = [
                'name' => 'SMTP Handshake',
                'status' => 'fail',
                'message' => "Could not connect to {$smtpHost}:{$smtpPort} — " . $e->getMessage()
            ];
        }

        // 6. Queue Metrics
        $queueMetrics = $db->query("
            SELECT 
                COUNT(IF(status='pending', 1, NULL)) as pending,
                COUNT(IF(status='sending', 1, NULL)) as sending,
                COUNT(IF(status='sent', 1, NULL)) as sent,
                COUNT(IF(status='failed', 1, NULL)) as failed
            FROM email_queue
        ")->fetch();

        // 7. Automation Queue Metrics
        $autoMetrics = $db->query("
            SELECT 
                COUNT(IF(status='pending', 1, NULL)) as pending,
                COUNT(IF(status='completed', 1, NULL)) as completed
            FROM automation_queue
        ")->fetch();

        // 8. Fetch Latest Failed Queue Items
        $failedItems = $db->query("
            SELECT eq.*, s.email, c.name as campaign_name 
            FROM email_queue eq
            JOIN subscribers s ON s.id = eq.subscriber_id
            JOIN campaigns c ON c.id = eq.campaign_id
            WHERE eq.status = 'failed' 
            ORDER BY eq.send_at DESC 
            LIMIT 10
        ")->fetchAll();

        $title = 'Diagnostics';
        $viewPath = dirname(__DIR__) . '/views/diagnostics.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    /**
     * Cron endpoint processing queue and automations
     */
    public function runCron(): void {
        header('Content-Type: text/plain; charset=utf-8');
        
        $secret = $_GET['secret'] ?? $_SERVER['argv'][1] ?? '';
        $cronSecret = getSetting('cron_secret');

        if ($cronSecret !== '' && $secret !== $cronSecret && php_sapi_name() !== 'cli') {
            http_response_code(403);
            die("Unauthorized Cron Request.\n");
        }

        echo "--- Merlin Spellcaster Cron Job Run Started ---\n";
        echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

        // 1. Process automations
        echo "Processing Automations due...\n";
        $autoCount = Automation::process();
        echo "Completed {$autoCount} automation workflow step(s).\n\n";

        // 2. Process email queue
        echo "Processing Email Queue batch...\n";
        $batchSize = (int)getSetting('cron_batch_size', '50');
        $queueResults = Queue::process($batchSize);
        echo $queueResults['message'] . "\n\n";

        // 3. Process inbox monitor (Bounces & Replies)
        echo "Processing Inbox Monitor (Bounces & Replies)...\n";
        require_once __DIR__ . '/../core/InboxMonitor.php';
        $inboxRes = InboxMonitor::process();
        if ($inboxRes['success']) {
            echo "Inbox Monitor: Processed {$inboxRes['bounces_count']} bounces and {$inboxRes['replies_count']} replies.\n";
            if (!empty($inboxRes['bounces'])) {
                echo "Bounced Recipients: " . implode(', ', $inboxRes['bounces']) . "\n";
            }
            if (!empty($inboxRes['replies'])) {
                echo "Replied Recipients: " . implode(', ', $inboxRes['replies']) . "\n";
            }
        } else {
            echo "Inbox Monitor: " . $inboxRes['message'] . "\n";
        }
        echo "\n";

        // Save last run time
        $db = Database::getConnection();
        $st = $db->prepare("INSERT INTO settings (`key`, value) VALUES ('last_cron_run', ?) ON DUPLICATE KEY UPDATE value = ?");
        $st->execute([date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);

        echo "--- Cron Execution Finished ---\n";
        exit;
    }

    /**
     * Endpoint to fetch IMAP folders dynamically via pure PHP sockets
     */
    public function fetchImapFolders(): void {
        header('Content-Type: application/json');
        
        $host = trim($_POST['host'] ?? '');
        $port = (int)($_POST['port'] ?? 993);
        $user = trim($_POST['user'] ?? '');
        $pass = trim($_POST['pass'] ?? '');
        $ssl = (int)($_POST['ssl'] ?? 1) === 1;

        if ($host === '' || $user === '' || $pass === '') {
            echo json_encode(['success' => false, 'error' => 'Missing IMAP credentials (host, user, pass).']);
            return;
        }

        require_once dirname(__DIR__) . '/core/ImapClient.php';
        
        try {
            $client = new ImapClient($host, $port, $user, $pass, $ssl);
            $folders = $client->getFolders();
            echo json_encode(['success' => true, 'folders' => $folders]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Webhook Endpoint for Mailgun to process bounces/clicks/opens
     */
    public function webhook(): void {
        header('Content-Type: application/json');
        
        $db = Database::getConnection();
        $payload = file_get_contents('php://input');
        
        // Log Webhook Payload
        $postData = json_decode($payload, true) ?: $_POST;
        $provider = 'mailgun';
        
        // Simple Mailgun event parsing (JSON post from Mailgun v3 API)
        $eventData = $postData['event-data'] ?? [];
        $event = $eventData['event'] ?? $postData['event'] ?? '';
        $recipient = strtolower(trim($eventData['recipient'] ?? $postData['recipient'] ?? ''));

        if ($event !== '' && $recipient !== '') {
            try {
                // Log webhook event
                $stLog = $db->prepare("INSERT INTO webhook_logs (provider, event_type, payload) VALUES (?, ?, ?)");
                $stLog->execute([$provider, $event, json_encode($postData)]);

                // Retrieve subscriber
                $stSub = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
                $stSub->execute([$recipient]);
                $subId = (int)$stSub->fetchColumn();

                if ($subId > 0) {
                    if ($event === 'failed' || $event === 'bounced') {
                        // Mark as bounced
                        $db->prepare("UPDATE subscribers SET status = 'bounced' WHERE id = ?")->execute([$subId]);
                        logActivity($subId, 'bounce', "Mailgun reported email bounce: " . ($eventData['delivery-status']['description'] ?? 'Permanent bounce'));
                    } elseif ($event === 'complained') {
                        // Mark as complained (spam report)
                        $db->prepare("UPDATE subscribers SET status = 'complained' WHERE id = ?")->execute([$subId]);
                        logActivity($subId, 'bounce', "Mailgun reported subscriber spam complaint.");
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Webhook parsed and subscriber updated.']);
            } catch (Throwable $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid webhook event format.']);
        }
        exit;
    }
}
