<?php
/**
 * core/Mailer.php — SMTP email sender
 * PHP 8.5+ — no Composer, uses native socket stream
 */
declare(strict_types=1);

class Mailer
{
    private string $lastError = '';

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function send(
        string  $to,
        string  $subject,
        string  $htmlBody,
        ?string $textBody  = null,
        ?string $fromName  = null,
        ?string $fromEmail = null,
        ?string $replyTo   = null
    ): bool {
        // Load SMTP settings from DB
        require_once dirname(__DIR__) . '/config.php';
        /** @var PDO $db */
        global $db;

        $host   = getSetting('smtp_host');
        $port   = (int)getSetting('smtp_port', '587');
        $enc    = getSetting('smtp_encryption', 'tls');
        $user   = getSetting('smtp_user');
        $pass   = getSetting('smtp_pass');
        $fn     = $fromName  ?? getSetting('smtp_from_name', 'Newsletter');
        $fe     = $fromEmail ?? getSetting('smtp_from_email', 'noreply@localhost');

        // Fallback to PHP mail()
        if (!$host) {
            return $this->sendViaMail($to, $subject, $htmlBody, $textBody, $fn, $fe, $replyTo);
        }

        try {
            return $this->sendViaSmtp($to, $subject, $htmlBody, $textBody, $fn, $fe, $replyTo, $host, $port, $enc, $user, $pass);
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            // Try PHP mail() as last resort
            return $this->sendViaMail($to, $subject, $htmlBody, $textBody, $fn, $fe, $replyTo);
        }
    }

    private function sendViaSmtp(
        string $to, string $subject, string $htmlBody, ?string $textBody,
        string $fromName, string $fromEmail, ?string $replyTo,
        string $host, int $port, string $enc, string $user, string $pass
    ): bool {
        $prefix  = ($enc === 'ssl') ? 'ssl://' : 'tcp://';
        $context = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
        ]);
        $socket = @stream_socket_client(
            $prefix . $host . ':' . $port,
            $errno, $errstr, 10,
            STREAM_CLIENT_CONNECT, $context
        );
        if (!$socket) {
            throw new RuntimeException("Cannot connect to SMTP {$host}:{$port} — {$errstr}");
        }
        stream_set_timeout($socket, 10);

        $read  = function () use ($socket): string {
            $resp = '';
            while (!feof($socket)) {
                $line = fgets($socket, 515);
                if ($line === false) break;
                $resp .= $line;
                if (strlen($line) >= 4 && $line[3] === ' ') break;
            }
            return $resp;
        };
        $write = function (string $data) use ($socket): void {
            fwrite($socket, $data . "\r\n");
        };
        $expect = function (string $code) use ($read, $socket): string {
            $resp = ($read)();
            if (!str_starts_with($resp, $code)) {
                throw new RuntimeException("SMTP expected {$code}, got: {$resp}");
            }
            return $resp;
        };

        ($expect)('220'); // greeting
        ($write)('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        ($expect)('250');

        if ($enc === 'tls') {
            ($write)('STARTTLS');
            ($expect)('220');
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            ($write)('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            ($expect)('250');
        }

        if ($user !== '') {
            ($write)('AUTH LOGIN');
            ($expect)('334');
            ($write)(base64_encode($user));
            ($expect)('334');
            ($write)(base64_encode($pass));
            ($expect)('235');
        }

        ($write)("MAIL FROM:<{$fromEmail}>");
        ($expect)('250');
        ($write)("RCPT TO:<{$to}>");
        ($expect)('250');
        ($write)('DATA');
        ($expect)('354');

        // Build MIME message
        ($write)($this->buildMime($to, $subject, $htmlBody, $textBody, $fromName, $fromEmail, $replyTo));
        ($write)('.');
        ($expect)('250');
        ($write)('QUIT');
        fclose($socket);

        $this->lastError = '';
        return true;
    }

    private function sendViaMail(
        string $to, string $subject, string $htmlBody, ?string $textBody,
        string $fromName, string $fromEmail, ?string $replyTo
    ): bool {
        $boundary = '=_Part_' . md5(uniqid());
        $headers  = implode("\r\n", array_filter([
            "From: {$fromName} <{$fromEmail}>",
            "Reply-To: " . ($replyTo ?: $fromEmail),
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "X-Mailer: Merlin-Spellcaster",
        ]));
        $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . ($textBody ?: strip_tags($htmlBody))
              . "\r\n--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $htmlBody
              . "\r\n--{$boundary}--";
        return mail($to, $subject, $body, $headers);
    }

    private function buildMime(
        string $to, string $subject, string $htmlBody, ?string $textBody,
        string $fromName, string $fromEmail, ?string $replyTo
    ): string {
        $boundary = '=_Part_' . md5(uniqid());
        $lines = [
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>",
            "To: {$to}",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "Date: " . date('r'),
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            $replyTo ? "Reply-To: {$replyTo}" : null,
            "X-Mailer: Merlin-Spellcaster",
            "",
            "--{$boundary}",
            "Content-Type: text/plain; charset=UTF-8",
            "Content-Transfer-Encoding: quoted-printable",
            "",
            quoted_printable_encode($textBody ?: strip_tags($htmlBody)),
            "",
            "--{$boundary}",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: quoted-printable",
            "",
            quoted_printable_encode($htmlBody),
            "",
            "--{$boundary}--",
        ];
        return implode("\r\n", array_filter($lines, fn($l) => $l !== null));
    }
}
