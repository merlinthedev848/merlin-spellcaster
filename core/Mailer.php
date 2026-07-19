<?php
declare(strict_types=1);

/**
 * Modern Socket-based SMTP Mailer for Merlin V2.
 * Zero external dependencies. Works perfectly on shared hosting (Enhance/cPanel).
 */
class Mailer {
    private string $lastError = '';

    public function getLastError(): string {
        return $this->lastError;
    }

    public function send(
        string $to,
        string $subject,
        string $bodyHtml,
        string $bodyText = '',
        string $fromName = '',
        string $fromEmail = '',
        ?string $replyTo = null,
        array $extraHeaders = []
    ): bool {
        $db = Database::getConnection();
        $serverId = 0;
        
        if (ModuleManager::isEnabled('multi_smtp')) {
            $st = $db->query("
                SELECT * FROM smtp_servers 
                WHERE status = 1 
                AND (daily_limit = 0 OR sent_today < daily_limit)
                ORDER BY last_used ASC 
                LIMIT 1
            ");
            $server = $st->fetch(PDO::FETCH_ASSOC);
            if ($server) {
                $serverId = (int)$server['id'];
                $host = $server['host'];
                $port = (int)$server['port'];
                $encryption = strtolower($server['encryption']);
                $user = $server['username'];
                $pass = $server['password'];
                $fromEmail = $fromEmail ?: ($server['from_email'] ?: getSetting('smtp_from_email', 'noreply@localhost'));
                $fromName = $fromName ?: ($server['from_name'] ?: getSetting('smtp_from_name', 'Merlin'));
                
                $db->prepare("UPDATE smtp_servers SET last_used = NOW() WHERE id = ?")->execute([$serverId]);
            }
        }

        if ($serverId === 0) {
            $host = getSetting('smtp_host', 'localhost');
            $port = (int)getSetting('smtp_port', '587');
            $encryption = strtolower(getSetting('smtp_encryption', 'tls'));
            $user = getSetting('smtp_user', '');
            $pass = getSetting('smtp_pass', '');

            $fromEmail = $fromEmail ?: getSetting('smtp_from_email', 'noreply@localhost');
            $fromName = $fromName ?: getSetting('smtp_from_name', 'Merlin');
        }

        try {
            $prefix = ($encryption === 'ssl') ? 'ssl://' : 'tcp://';
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ]
            ]);

            $socket = @stream_socket_client(
                $prefix . $host . ':' . $port,
                $errno,
                $errstr,
                10,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                throw new RuntimeException("Cannot connect to SMTP server {$host}:{$port} — {$errstr} (#{$errno})");
            }

            stream_set_timeout($socket, 10);

            $read = static function () use ($socket): string {
                $r = '';
                while (!feof($socket)) {
                    $line = fgets($socket, 515);
                    if ($line === false) break;
                    $r .= $line;
                    
                    // SMTP RFC indicates the last line of response has a space or newline at the 4th position (not a hyphen)
                    if (strlen($line) >= 3) {
                        $code = substr($line, 0, 3);
                        if (is_numeric($code)) {
                            if (strlen($line) < 4 || $line[3] !== '-') {
                                break;
                            }
                        }
                    }
                }
                return $r;
            };

            $write = static function (string $d) use ($socket): void {
                fwrite($socket, $d . "\r\n");
            };

            $expect = function (string $code, string $msg) use ($read, $write, $socket): void {
                $resp = $read();
                if (!str_starts_with(trim($resp), $code)) {
                    $write('QUIT');
                    fclose($socket);
                    throw new RuntimeException("{$msg}: {$resp}");
                }
            };

            // Read banner
            $expect('220', 'SMTP Hello banner error');

            // Send EHLO
            $write('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            $expect('250', 'EHLO greeting failed');

            // Handle STARTTLS
            if ($encryption === 'tls') {
                $write('STARTTLS');
                $expect('220', 'STARTTLS negotiation failed');

                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    fclose($socket);
                    throw new RuntimeException('STARTTLS encryption upgrade failed');
                }

                $write('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
                $expect('250', 'EHLO greeting after TLS failed');
            }

            // Authentication
            if ($user !== '') {
                $write('AUTH LOGIN');
                $expect('334', 'AUTH LOGIN failed');
                
                $write(base64_encode($user));
                $expect('334', 'SMTP Username rejected');
                
                $write(base64_encode($pass));
                $expect('235', 'SMTP Authentication failed');
            }

            // MAIL FROM
            $write("MAIL FROM:<{$fromEmail}>");
            $expect('250', 'MAIL FROM command failed');

            // RCPT TO
            $write("RCPT TO:<{$to}>");
            $expect('250', 'RCPT TO command failed');

            // DATA
            $write('DATA');
            $expect('354', 'DATA command failed');

            // Email Headers & Body Construction
            $boundary = '----=' . bin2hex(random_bytes(16));
            $headers = [];
            $headers[] = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>";
            $headers[] = "To: <{$to}>";
            $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
            $headers[] = "Date: " . date('r');
            $headers[] = "Message-ID: <" . bin2hex(random_bytes(16)) . "@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . ">";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
            if ($replyTo) {
                $headers[] = "Reply-To: <{$replyTo}>";
            }
            foreach ($extraHeaders as $hName => $hVal) {
                $headers[] = "{$hName}: {$hVal}";
            }

            $message = implode("\r\n", $headers) . "\r\n\r\n";

            // Plain text part
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode($bodyText ?: strip_tags($bodyHtml))) . "\r\n";

            // HTML part
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode($bodyHtml)) . "\r\n";

            // End boundary
            $message .= "--{$boundary}--\r\n";

            // Ensure lines starting with dots are escaped (dot-stuffing)
            $message = preg_replace('/^\./m', '..', $message);
            $message .= '.';

            $write($message);
            $expect('250', 'Message body sending failed');

            $write('QUIT');
            fclose($socket);
            if (isset($serverId) && $serverId > 0) {
                $db->prepare("UPDATE smtp_servers SET sent_today = sent_today + 1 WHERE id = ?")->execute([$serverId]);
            }
            return true;

        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            if (isset($serverId) && $serverId > 0) {
                $db->prepare("UPDATE smtp_servers SET error_count = error_count + 1 WHERE id = ?")->execute([$serverId]);
                $db->prepare("UPDATE smtp_servers SET status = 0 WHERE id = ? AND error_count >= 5")->execute([$serverId]);
            }
            return false;
        }
    }
}
