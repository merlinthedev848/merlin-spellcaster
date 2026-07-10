<?php
/**
 * setup/test_db.php — AJAX endpoint for setup wizard connection tests
 * PHP 8.5+ compatible
 */
declare(strict_types=1);
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Allow calls both as standalone and when included
if (!function_exists('testDbConnection')) {
    function testDbConnection(string $host, int $port, string $name, string $user, string $pass): array
    {
        try {
            $pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $tableCount = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")->fetchColumn();
            return ['success' => true, 'message' => "Connected! Database has {$tableCount} table(s).", 'tables' => $tableCount];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

// Only produce output if called directly (not included)
if (basename($_SERVER['PHP_SELF']) === 'test_db.php') {
    $action = $_GET['action'] ?? 'db';

    if ($action === 'db') {
        $host = trim($_POST['host'] ?? 'localhost');
        $port = (int)($_POST['port'] ?? 3306);
        $name = trim($_POST['name'] ?? '');
        $user = trim($_POST['user'] ?? '');
        $pass = $_POST['pass'] ?? '';

        if (!$name || !$user) {
            echo json_encode(['success' => false, 'message' => 'Database name and username are required.']);
            exit;
        }

        echo json_encode(testDbConnection($host, $port, $name, $user, $pass));
        exit;
    }

    if ($action === 'smtp') {
        $host = trim($_POST['host'] ?? 'localhost');
        $port = (int)($_POST['port'] ?? 587);
        $enc  = strtolower(trim($_POST['encryption'] ?? 'tls'));
        $user = trim($_POST['smtp_user'] ?? '');
        $pass = $_POST['smtp_pass'] ?? '';

        if (!$host) {
            echo json_encode(['success' => false, 'message' => 'SMTP host is required.']);
            exit;
        }

        try {
            $prefix  = ($enc === 'ssl') ? 'ssl://' : 'tcp://';
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                    'allow_self_signed'=> true,
                ]
            ]);

            $socket = @stream_socket_client(
                $prefix . $host . ':' . $port,
                $errno,
                $errstr,
                8,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                throw new RuntimeException("Cannot connect to {$host}:{$port} — {$errstr} (#{$errno})");
            }

            stream_set_timeout($socket, 8);

            $read  = static function () use ($socket): string {
                $r = '';
                while (!feof($socket)) {
                    $line = fgets($socket, 515);
                    if ($line === false) break;
                    $r .= $line;
                    if (strlen($line) >= 4 && $line[3] === ' ') break;
                }
                return $r;
            };

            $write = static function (string $d) use ($socket): void {
                fwrite($socket, $d . "\r\n");
            };

            $banner = $read();
            if (!str_starts_with($banner, '2')) {
                fclose($socket);
                echo json_encode(['success' => false, 'message' => "SMTP banner error: {$banner}"]);
                exit;
            }

            $write('EHLO test.merlin');
            $read();

            if ($enc === 'tls') {
                $write('STARTTLS');
                $stResp = $read();
                if (!str_starts_with($stResp, '220')) {
                    fclose($socket);
                    echo json_encode(['success' => false, 'message' => "STARTTLS rejected: {$stResp}"]);
                    exit;
                }
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $write('EHLO test.merlin');
                $read();
            }

            $authed = false;
            if ($user !== '') {
                $write('AUTH LOGIN');
                $read();
                $write(base64_encode($user));
                $read();
                $write(base64_encode($pass));
                $authResp = $read();
                $authed   = str_starts_with($authResp, '235');
                if (!$authed) {
                    $write('QUIT');
                    fclose($socket);
                    echo json_encode(['success' => false, 'message' => 'Authentication failed: ' . trim($authResp)]);
                    exit;
                }
            }

            $write('QUIT');
            fclose($socket);

            $msg = 'SMTP connection successful';
            if ($authed) $msg .= ' and authenticated ✓';
            echo json_encode(['success' => true, 'message' => $msg]);

        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
