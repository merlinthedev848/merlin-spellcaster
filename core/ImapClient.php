<?php
declare(strict_types=1);

/**
 * Pure PHP raw-socket IMAP Client to fetch folder lists.
 * Bypasses the need for the native PHP 'imap' extension.
 */
class ImapClient {
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private bool $ssl;
    
    private $socket = null;
    private int $tag = 1;

    public function __construct(string $host, int $port, string $user, string $pass, bool $ssl) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->ssl = $ssl;
    }

    private function getNextTag(): string {
        $t = "A" . str_pad((string)$this->tag, 3, '0', STR_PAD_LEFT);
        $this->tag++;
        return $t;
    }

    private function sendCommand(string $command): string {
        $tag = $this->getNextTag();
        $fullCmd = "{$tag} {$command}\r\n";
        fwrite($this->socket, $fullCmd);
        return $tag;
    }

    private function readUntilTag(string $tag): array {
        $lines = [];
        while (!feof($this->socket)) {
            $line = fgets($this->socket);
            if ($line === false) {
                break;
            }
            $lines[] = trim($line);
            if (str_starts_with($line, $tag . " ")) {
                break;
            }
        }
        return $lines;
    }

    public function getFolders(): array {
        $prefix = $this->ssl ? 'ssl://' : 'tcp://';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);

        $this->socket = @stream_socket_client(
            $prefix . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new RuntimeException("Cannot connect to IMAP server {$this->host}:{$this->port} - {$errstr}");
        }

        stream_set_timeout($this->socket, 10);

        // Read greeting
        $greeting = fgets($this->socket);
        if (!$greeting || !str_starts_with($greeting, '* OK')) {
            fclose($this->socket);
            throw new RuntimeException("IMAP server rejected connection.");
        }

        // Login
        $userEsc = '"' . str_replace('"', '\\"', $this->user) . '"';
        $passEsc = '"' . str_replace('"', '\\"', $this->pass) . '"';
        $tag = $this->sendCommand("LOGIN {$userEsc} {$passEsc}");
        $resp = $this->readUntilTag($tag);
        
        $lastLine = end($resp);
        if (!str_contains($lastLine, ' OK ')) {
            fclose($this->socket);
            throw new RuntimeException("IMAP Login failed: " . $lastLine);
        }

        // List folders
        $tag = $this->sendCommand("LIST \"\" \"*\"");
        $listResp = $this->readUntilTag($tag);
        
        $folders = [];
        foreach ($listResp as $line) {
            // e.g. * LIST (\HasNoChildren) "/" "INBOX"
            // e.g. * LIST (\HasNoChildren) "/" INBOX
            if (str_starts_with($line, '* LIST')) {
                // Regex to extract the last quoted or unquoted token
                if (preg_match('/\* LIST \([^\)]*\)\s+"[^"]*"\s+"?([^"]+)"?/i', $line, $m)) {
                    $folders[] = $m[1];
                } elseif (preg_match('/\* LIST \([^\)]*\)\s+NIL\s+"?([^"]+)"?/i', $line, $m)) {
                    $folders[] = $m[1];
                }
            }
        }

        // Logout
        $tag = $this->sendCommand("LOGOUT");
        $this->readUntilTag($tag);

        fclose($this->socket);

        return array_unique($folders);
    }
}
