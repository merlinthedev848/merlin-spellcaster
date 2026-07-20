<?php
declare(strict_types=1);

/**
 * Controller for Application Settings, System Diagnostics, Cron processing, and Mailgun Webhooks
 */
class SettingController {
    public function index(): void {
        $db = Database::getConnection();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf()) {
                $_SESSION['flash_error'] = 'CSRF validation failed.';
                header('Location: ' . getSetting('app_url') . '/settings');
                exit;
            }
            foreach ($_POST as $key => $value) {
                if (str_starts_with($key, 'setting_')) {
                    $settingKey = substr($key, 8);
                    if (($settingKey === 'smtp_pass' || $settingKey === 'bounce_imap_pass') && trim((string)$value) === '') {
                        continue;
                    }
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

        // 6. IP Health (Spam Blacklist Check)
        $checks['ip_health'] = [
            'name' => 'IP Health (Spam Blacklist)',
            'status' => 'pass',
            'message' => 'Your sending IP is clean.'
        ];
        
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 2]]);
            $publicIp = @file_get_contents('https://api.ipify.org', false, $ctx);
            if ($publicIp && filter_var($publicIp, FILTER_VALIDATE_IP)) {
                $reverseIp = implode('.', array_reverse(explode('.', $publicIp)));
                $rbls = ['zen.spamhaus.org', 'b.barracudacentral.org'];
                $listedIn = [];
                
                foreach ($rbls as $rbl) {
                    if (checkdnsrr($reverseIp . '.' . $rbl, 'A')) {
                        $listedIn[] = $rbl;
                    }
                }
                
                if (!empty($listedIn)) {
                    $checks['ip_health']['status'] = 'fail';
                    $checks['ip_health']['message'] = "Your Server IP ($publicIp) is blacklisted by: " . implode(', ', $listedIn);
                } else {
                    $checks['ip_health']['message'] = "Server IP ($publicIp) is clean (checked Spamhaus, Barracuda).";
                }
            } else {
                $checks['ip_health']['status'] = 'warn';
                $checks['ip_health']['message'] = "Could not determine public IP to check against blacklists.";
            }
        } catch (Throwable $e) {
            $checks['ip_health']['status'] = 'warn';
            $checks['ip_health']['message'] = "IP Health check timed out or failed.";
        }

        // 7. Queue Metrics
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

        // 9. Fetch Campaign Queue Throttle Estimates
        $queueEstimates = $db->query("
            SELECT c.name, c.max_per_hour, COUNT(eq.id) as pending_count
            FROM email_queue eq
            JOIN campaigns c ON c.id = eq.campaign_id
            WHERE eq.status = 'pending'
            GROUP BY c.id, c.name, c.max_per_hour
        ")->fetchAll();

        $title = 'Diagnostics';
        $viewPath = dirname(__DIR__) . '/views/diagnostics.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    /**
     * Clear failed email delivery logs
     */
    public function clearLogs(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . getSetting('app_url') . '/diagnostics');
            exit;
        }
        if (!Auth::checkCsrf()) {
            $_SESSION['flash_error'] = 'Invalid security token.';
            header('Location: ' . getSetting('app_url') . '/diagnostics');
            exit;
        }

        $db = Database::getConnection();
        $db->exec("DELETE FROM email_queue WHERE status = 'failed'");
        
        $_SESSION['flash_success'] = 'Failed delivery logs cleared successfully.';
        header('Location: ' . getSetting('app_url') . '/diagnostics');
        exit;
    }

    /**
     * Cron endpoint processing queue and automations
     */
    public function runCron(): void {
        header('Content-Type: text/plain; charset=utf-8');
        
        $secret = $_GET['secret'] ?? $_SERVER['argv'][1] ?? '';
        $cronSecret = getSetting('cron_secret');

        if ($cronSecret !== '' && !hash_equals($cronSecret, $secret) && php_sapi_name() !== 'cli') {
            http_response_code(403);
            die("Unauthorized Cron Request.\n");
        }

        echo "--- Merlin Spellcaster Cron Job Run Started ---\n";
        echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

        $db = Database::getConnection();

        // 0. Sync Autopilot Campaigns for new contacts
        echo "Syncing Autopilot Campaigns for new contacts...\n";
        $syncedCount = CampaignController::syncActiveCampaigns($db);
        echo "Autopilot Sync: Queued {$syncedCount} new contact(s).\n\n";

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
        ob_start();
        $host = trim($_POST['host'] ?? '');
        $port = (int)($_POST['port'] ?? 993);
        $user = trim($_POST['user'] ?? '');
        $pass = trim($_POST['pass'] ?? '');
        $ssl = (int)($_POST['ssl'] ?? 1) === 1;

        if ($host === '' || $user === '' || $pass === '') {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Missing IMAP credentials (host, user, pass).']);
            return;
        }

        require_once dirname(__DIR__) . '/core/ImapClient.php';
        
        try {
            $client = new ImapClient($host, $port, $user, $pass, $ssl);
            $folders = $client->getFolders();
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'folders' => $folders]);
        } catch (Throwable $e) {
            ob_clean();
            header('Content-Type: application/json');
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
        
        // Webhook Authentication
        $secret = $_GET['secret'] ?? '';
        $expectedSecret = getSetting('cron_secret');
        $authorized = false;
        
        $signature = $postData['signature'] ?? [];
        $mailgunApiKey = getSetting('mailgun_api_key');
        
        if (!empty($mailgunApiKey) && !empty($signature)) {
            $timestamp = $signature['timestamp'] ?? '';
            $token = $signature['token'] ?? '';
            $sig = $signature['signature'] ?? '';
            $expected = hash_hmac('sha256', $timestamp . $token, $mailgunApiKey);
            if (hash_equals($expected, $sig)) {
                $authorized = true;
            }
        }
        
        if (!$authorized && !empty($expectedSecret) && hash_equals($expectedSecret, $secret)) {
            $authorized = true;
        }
        
        if (!$authorized && php_sapi_name() !== 'cli') {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized webhook signature or secret.']);
            exit;
        }
        
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

    /**
     * API endpoint to test SMTP mailer connection
     */
    public function testSmtp(): void {
        header('Content-Type: application/json');
        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }

        $host = trim($_POST['host'] ?? '');
        $port = (int)($_POST['port'] ?? 587);
        $encryption = strtolower(trim($_POST['encryption'] ?? 'tls'));
        $user = trim($_POST['user'] ?? '');
        $pass = $_POST['pass'] ?? '';
        $fromEmail = trim($_POST['from_email'] ?? '');
        $fromName = trim($_POST['from_name'] ?? 'Merlin Test');
        $recipientEmail = trim($_POST['recipient_email'] ?? '');

        if ($pass === '') {
            $pass = getSetting('smtp_pass', '');
        }

        if (empty($host) || empty($fromEmail) || empty($recipientEmail)) {
            echo json_encode(['success' => false, 'error' => 'SMTP Host, From Email, and Recipient Email are required for testing.']);
            exit;
        }

        $mailer = new Mailer();
        $appName = getSetting('app_name', 'Merlin Spellcaster');
        $subject = "Merlin SMTP Connection Test — " . date('H:i:s');
        $bodyHtml = '
            <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; padding: 24px; color: #1e293b; background-color: #f8fafc; border-radius: 8px;">
                <h2 style="color: #635bff; margin-top: 0;">SMTP Connection Successful! 🎉</h2>
                <p>This test email confirms that your <strong>' . htmlspecialchars($appName) . '</strong> SMTP mailer configuration is connected and actively delivering messages.</p>
                <div style="background-color: #ffffff; padding: 16px; border-radius: 6px; border: 1px solid #e2e8f0; margin: 16px 0; font-size: 13px; color: #475569;">
                    <strong style="display:block; margin-bottom: 8px; color: #0f172a;">Connection Diagnostics:</strong>
                    <strong>Host:</strong> ' . htmlspecialchars($host) . ':' . $port . '<br>
                    <strong>Encryption:</strong> ' . strtoupper($encryption) . '<br>
                    <strong>Username:</strong> ' . htmlspecialchars($user ?: '(None)') . '<br>
                    <strong>From:</strong> ' . htmlspecialchars($fromName) . ' &lt;' . htmlspecialchars($fromEmail) . '&gt;<br>
                    <strong>Timestamp:</strong> ' . date('Y-m-d H:i:s T') . '
                </p>
                </div>
            </div>
        ';

        $success = $mailer->sendCustom($host, $port, $encryption, $user, $pass, $fromEmail, $fromName, $recipientEmail, $subject, $bodyHtml);

        if ($success) {
            echo json_encode(['success' => true, 'message' => "Test email successfully delivered to {$recipientEmail}!"]);
        } else {
            echo json_encode(['success' => false, 'error' => $mailer->getLastError()]);
        }
        exit;
    }
}
