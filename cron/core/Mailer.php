<?php
/**
 * core/Mailer.php — Standalone SMTP email sender
 * PHP 7.4+ compatible. No Composer or external dependencies required.
 * Supports: SSL (port 465), STARTTLS (port 587), plain (port 25)
 * Falls back to PHP mail() when no SMTP credentials are configured.
 */

class Mailer
{
    /** Send a single email. Returns ['success'=>bool, 'error'=>string] */
    public static function send(
        string  $toEmail,
        string  $toName,
        string  $subject,
        string  $htmlBody,
        string  $textBody  = '',
        ?string $fromName  = null,
        ?string $fromEmail = null,
        ?string $replyTo   = null
    ): array {
        $fromName  = $fromName  ?? getSetting('smtp_from_name',  'Merlin Spellcaster');
        $fromEmail = $fromEmail ?? getSetting('smtp_from_email', 'noreply@example.com');
        $replyTo   = $replyTo   ?? $fromEmail;
        $textBody  = $textBody  !== '' ? $textBody : self::htmlToText($htmlBody);

        // Build MIME message
        $boundary  = '=_Part_' . md5(uniqid('merlin', true));
        $appUrl    = getSetting('app_url', 'http://localhost');
        $host      = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';
        $messageId = '<' . time() . '.' . mt_rand(100000, 999999) . '@' . $host . '>';

        $rawHeaders  = 'MIME-Version: 1.0' . "\r\n";
        $rawHeaders .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";
        $rawHeaders .= 'Message-ID: ' . $messageId . "\r\n";
        $rawHeaders .= 'Date: ' . date('r') . "\r\n";
        $rawHeaders .= 'From: ' . self::encodeHeader($fromName) . ' <' . $fromEmail . '>' . "\r\n";
        $rawHeaders .= 'To: '   . self::encodeHeader($toName)   . ' <' . $toEmail   . '>' . "\r\n";
        $rawHeaders .= 'Subject: ' . self::encodeHeader($subject) . "\r\n";
        $rawHeaders .= 'Reply-To: <' . $replyTo . '>' . "\r\n";
        $rawHeaders .= 'X-Mailer: Merlin-Spellcaster/' . APP_VERSION . "\r\n";
        $rawHeaders .= 'List-Unsubscribe: <' . $appUrl . '/unsubscribe.php>' . "\r\n";

        $body  = '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($textBody) . "\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($htmlBody) . "\r\n";
        $body .= '--' . $boundary . "--\r\n";

        $raw = $rawHeaders . "\r\n" . $body;

        // Decide transport
        $smtpUser = getSetting('smtp_user');
        $smtpHost = getSetting('smtp_host', 'localhost');

        if (empty($smtpUser) && in_array($smtpHost, ['localhost', '127.0.0.1'], true)) {
            return self::sendViaMail($toEmail, $subject, $body, $rawHeaders, $fromEmail);
        }

        return self::sendViaSmtp(
            $toEmail, $fromEmail, $raw,
            $smtpHost,
            (int)getSetting('smtp_port', '587'),
            $smtpUser,
            getSetting('smtp_pass'),
            getSetting('smtp_encryption', 'tls')
        );
    }

    /** SMTP transport — socket-based, no external deps */
    private static function sendViaSmtp(
        string $to,
        string $from,
        string $rawMessage,
        string $host,
        int    $port,
        string $user,
        string $pass,
        string $encryption
    ): array {
        $socket = null;
        try {
            $prefix  = ($encryption === 'ssl') ? 'ssl://' : 'tcp://';
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]);

            $socket = @stream_socket_client(
                $prefix . $host . ':' . $port,
                $errno, $errstr, 15,
                STREAM_CLIENT_CONNECT, $context
            );

            if (!$socket) {
                throw new RuntimeException("SMTP connect failed to {$host}:{$port} — {$errstr} ({$errno})");
            }

            stream_set_timeout($socket, 15);
            self::smtpExpect($socket, '220');

            self::smtpWrite($socket, 'EHLO ' . gethostname());
            self::smtpRead($socket); // consume EHLO response

            // Upgrade to TLS if requested
            if ($encryption === 'tls') {
                self::smtpWrite($socket, 'STARTTLS');
                self::smtpExpect($socket, '220');
                stream_socket_enable_crypto($socket, true,
                    STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                    | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                    | STREAM_CRYPTO_METHOD_TLS_CLIENT
                );
                self::smtpWrite($socket, 'EHLO ' . gethostname());
                self::smtpRead($socket);
            }

            // Authenticate
            if (!empty($user)) {
                self::smtpWrite($socket, 'AUTH LOGIN');
                self::smtpExpect($socket, '334');
                self::smtpWrite($socket, base64_encode($user));
                self::smtpExpect($socket, '334');
                self::smtpWrite($socket, base64_encode($pass));
                self::smtpExpect($socket, '235');
            }

            // Envelope
            self::smtpWrite($socket, 'MAIL FROM:<' . $from . '>');
            self::smtpExpect($socket, '250');

            self::smtpWrite($socket, 'RCPT TO:<' . $to . '>');
            $rcptResp = self::smtpRead($socket);
            if (strpos($rcptResp, '250') !== 0 && strpos($rcptResp, '251') !== 0) {
                throw new RuntimeException('RCPT TO rejected: ' . trim($rcptResp));
            }

            self::smtpWrite($socket, 'DATA');
            self::smtpExpect($socket, '354');

            // Dot-stuffing
            $dotStuffed = preg_replace('/^\./', '..', $rawMessage);
            self::smtpWrite($socket, $dotStuffed . "\r\n.");
            self::smtpExpect($socket, '250');

            self::smtpWrite($socket, 'QUIT');
            fclose($socket);

            return ['success' => true, 'error' => ''];

        } catch (Throwable $e) {
            if (is_resource($socket)) @fclose($socket);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** Fallback: PHP mail() for hosts without outbound SMTP */
    private static function sendViaMail(
        string $to,
        string $subject,
        string $body,
        string $headers,
        string $from
    ): array {
        $ok = @mail($to, $subject, $body, $headers, '-f' . $from);
        return ['success' => $ok, 'error' => $ok ? '' : 'mail() returned false — check PHP mail configuration'];
    }

    /** Test SMTP connectivity (used in setup wizard + settings) */
    public static function testConnection(): array
    {
        $host       = getSetting('smtp_host', 'localhost');
        $port       = (int)getSetting('smtp_port', '587');
        $user       = getSetting('smtp_user');
        $pass       = getSetting('smtp_pass');
        $encryption = getSetting('smtp_encryption', 'tls');

        try {
            $prefix  = ($encryption === 'ssl') ? 'ssl://' : 'tcp://';
            $context = stream_context_create([
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
            ]);

            $socket = @stream_socket_client($prefix . $host . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
            if (!$socket) {
                return ['success' => false, 'message' => "Cannot connect to {$host}:{$port} — {$errstr}"];
            }

            stream_set_timeout($socket, 10);
            self::smtpRead($socket); // greeting

            self::smtpWrite($socket, 'EHLO test');
            self::smtpRead($socket);

            if ($encryption === 'tls') {
                self::smtpWrite($socket, 'STARTTLS');
                self::smtpRead($socket);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                self::smtpWrite($socket, 'EHLO test');
                self::smtpRead($socket);
            }

            if (!empty($user)) {
                self::smtpWrite($socket, 'AUTH LOGIN');
                self::smtpRead($socket);
                self::smtpWrite($socket, base64_encode($user));
                self::smtpRead($socket);
                self::smtpWrite($socket, base64_encode($pass));
                $auth = self::smtpRead($socket);
                if (strpos($auth, '235') !== 0) {
                    fclose($socket);
                    return ['success' => false, 'message' => 'Authentication failed — check username/password'];
                }
            }

            self::smtpWrite($socket, 'QUIT');
            fclose($socket);

            return [
                'success' => true,
                'message' => "✓ Connected to {$host}:{$port}" . (!empty($user) ? " and authenticated as {$user}" : " (no auth)"),
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── SMTP primitives ─────────────────────────────────────────────────────────

    private static function smtpWrite($socket, string $data): void
    {
        fwrite($socket, $data . "\r\n");
    }

    private static function smtpRead($socket): string
    {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) break;
            $response .= $line;
            // Multi-line responses: 4th char is '-'; single/last line has ' '
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $response;
    }

    private static function smtpExpect($socket, string $code): string
    {
        $resp = self::smtpRead($socket);
        if (strpos($resp, $code) !== 0) {
            throw new RuntimeException("Expected {$code}, got: " . trim($resp));
        }
        return $resp;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    private static function encodeHeader(string $str): string
    {
        if (preg_match('/[^\x20-\x7E]/', $str)) {
            return '=?UTF-8?B?' . base64_encode($str) . '?=';
        }
        return $str;
    }

    private static function htmlToText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?\s*>/i', "\n", $html);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        $text = preg_replace('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i', '$2 [$1]', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
