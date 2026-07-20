<?php
declare(strict_types=1);

/**
 * Core Logic class for validating syntax, checking disposable providers, and querying MX records.
 */
class EmailVerifier {
    /**
     * Scan an email for deliverability.
     */
    public static function verify(string $email): array {
        $email = strtolower(trim($email));

        // 1. Syntax Check
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'reason' => 'Syntax format error'];
        }

        // Get domain
        $parts = explode('@', $email);
        $domain = array_pop($parts);

        // 2. Blacklisted Disposable Email Providers (Expanded)
        $disposable = [
            'mailinator.com', 'yopmail.com', 'tempmail.com', '10minutemail.com', 
            'guerrillamail.com', 'trashmail.com', 'dispostable.com', 'sharklasers.com',
            'getairmail.com', 'maildrop.cc', 'tempmailaddress.com', 'temp-mail.org',
            'disposable.com', 'burnermail.io', 'getnada.com', 'tempmail.net',
            'maildrop.co', 'mailnesia.com', 'mailcatch.com', 'inboxkitten.com',
            'moakt.com', 'dispostable.com', 'dropmail.me', '10mail.org',
            'generator.email', 'temp-mail.to', 'temp-mail.co', 'throwawaymail.com',
            'tempmailo.com', 'mailto.plus', 'fakeinbox.com', 'mintemail.com',
            'emailsens.com', 'tempmailblock.com', 'mailticking.com', 'secmail.pro'
        ];

        if (in_array($domain, $disposable, true)) {
            return ['valid' => false, 'reason' => 'Disposable email provider blocked'];
        }

        // 3. MX Domain Record Lookup
        $mxHosts = [];
        if (getmxrr($domain, $mxHosts)) {
            $primaryMx = $mxHosts[0];
        } else {
            // Check fallback for A records
            if (checkdnsrr($domain, 'A')) {
                $primaryMx = $domain;
            } else {
                return ['valid' => false, 'reason' => 'No active mail exchanger (MX) or A records found'];
            }
        }

        // 4. SMTP Handshake Verification (Deep Validation)
        $smtpValid = self::verifySmtpInbox($primaryMx, $email);
        if ($smtpValid['status'] === 'invalid') {
            return ['valid' => false, 'reason' => $smtpValid['reason']];
        }

        return ['valid' => true, 'reason' => 'Deliverable'];
    }

    /**
     * Perform an SMTP handshake connection to verify if the recipient inbox exists.
     */
    private static function verifySmtpInbox(string $mxHost, string $email): array {
        $fromEmail = getSetting('from_email', 'verifier@merlin.com');
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = 'verifier@' . getSetting('app_url', 'merlin-spellcaster.com');
            // Clean up to look like a domain
            $fromEmail = preg_replace('/^https?:\/\//', '', $fromEmail);
            $fromEmail = explode('/', $fromEmail)[0];
            $fromEmail = 'verifier@' . $fromEmail;
        }

        $timeout = 4; // short timeout to keep web requests snappy
        $socket = @fsockopen($mxHost, 25, $errno, $errstr, $timeout);
        
        if (!$socket) {
            // If connection is blocked (e.g. port 25 blocked by host), we fallback to MX status
            return ['status' => 'unknown', 'reason' => 'SMTP connection timed out or port 25 blocked'];
        }

        stream_set_timeout($socket, $timeout);

        $read = function() use ($socket) {
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $response;
        };

        try {
            // 1. Read Server Greeting
            $greeting = $read();
            if (!str_starts_with($greeting, '220')) {
                fclose($socket);
                return ['status' => 'unknown', 'reason' => 'Invalid SMTP banner: ' . $greeting];
            }

            // 2. HELO / EHLO
            $senderDomain = explode('@', $fromEmail)[1] ?? 'localhost';
            fwrite($socket, "EHLO " . $senderDomain . "\r\n");
            $ehloResp = $read();
            if (!str_starts_with($ehloResp, '250')) {
                fwrite($socket, "HELO " . $senderDomain . "\r\n");
                $heloResp = $read();
                if (!str_starts_with($heloResp, '250')) {
                    fclose($socket);
                    return ['status' => 'unknown', 'reason' => 'HELO/EHLO rejected: ' . $ehloResp];
                }
            }

            // 3. MAIL FROM
            fwrite($socket, "MAIL FROM:<" . $fromEmail . ">\r\n");
            $mailResp = $read();
            if (!str_starts_with($mailResp, '250')) {
                fclose($socket);
                return ['status' => 'unknown', 'reason' => 'Sender rejected: ' . $mailResp];
            }

            // 4. RCPT TO
            fwrite($socket, "RCPT TO:<" . $email . ">\r\n");
            $rcptResp = $read();
            
            // 5. QUIT
            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            // Parse RCPT response
            if (preg_match('/^(550|551|552|554)/', $rcptResp)) {
                $lowerResp = strtolower($rcptResp);
                // Filter out PTR, SPF, blacklist, spam, or authentication blocks which don't indicate invalid inboxes
                if (str_contains($lowerResp, '5.7.1') || 
                    str_contains($lowerResp, 'ptr') || 
                    str_contains($lowerResp, 'rdns') || 
                    str_contains($lowerResp, 'blocked') || 
                    str_contains($lowerResp, 'spf') || 
                    str_contains($lowerResp, 'policy') || 
                    str_contains($lowerResp, 'spamcop') || 
                    str_contains($lowerResp, 'blacklist') ||
                    str_contains($lowerResp, 'unauthorized')) {
                    return ['status' => 'unknown', 'reason' => 'SMTP server blocked verification request (policy/rDNS): ' . $rcptResp];
                }
                return ['status' => 'invalid', 'reason' => 'Recipient inbox does not exist (SMTP 550)'];
            }
            
            if (str_starts_with($rcptResp, '250') || str_starts_with($rcptResp, '251')) {
                return ['status' => 'valid', 'reason' => 'Inbox exists'];
            }

            return ['status' => 'unknown', 'reason' => 'SMTP response: ' . $rcptResp];

        } catch (Throwable $e) {
            fclose($socket);
            return ['status' => 'unknown', 'reason' => 'Handshake exception: ' . $e->getMessage()];
        }
    }
}
