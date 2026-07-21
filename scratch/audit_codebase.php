<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/config.php';

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);

$phpFiles = [];
foreach ($files as $file) {
    if ($file->getExtension() === 'php' && strpos($file->getPathname(), '.git') === false && strpos($file->getPathname(), 'vendor') === false) {
        $phpFiles[] = $file->getPathname();
    }
}

echo "Found " . count($phpFiles) . " PHP files to audit.\n\n";

$issues = [];

foreach ($phpFiles as $filePath) {
    $relPath = str_replace($root . DIRECTORY_SEPARATOR, '', $filePath);
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);

    foreach ($lines as $i => $line) {
        $lineNum = $i + 1;

        // Check 1: Potential SQL syntax issues with LIMIT/OFFSET string interpolation without cast or intval
        if (preg_match('/LIMIT\s+\$([a-zA-Z0-9_]+)/i', $line, $m)) {
            $var = $m[1];
            if (!preg_match('/\(int\)|\bintval\b/', $line) && strpos($line, 'bindValue') === false) {
                $issues[] = "[{$relPath}:{$lineNum}] Potential un-cast LIMIT variable: \${$var}";
            }
        }

        // Check 2: header('Location: ...') without immediate exit;
        if (preg_match('/header\s*\(\s*[\'"]Location:/i', $line)) {
            $nextLines = array_slice($lines, $i, 3);
            $hasExit = false;
            foreach ($nextLines as $nl) {
                if (preg_match('/exit\s*;|die\s*;/i', $nl)) {
                    $hasExit = true;
                    break;
                }
            }
            if (!$hasExit) {
                $issues[] = "[{$relPath}:{$lineNum}] Header redirect without immediate exit/die";
            }
        }

        // Check 3: undefined variable patterns in SQL prepare statements
        if (preg_match('/\$db->prepare\s*\(\s*["\'](.*?)["\']\s*\)/i', $line, $m)) {
            $sql = $m[1];
            if (strpos($sql, 'WHERE') !== false && strpos($sql, '?') === false && strpos($sql, ':') === false && preg_match('/\$[a-zA-Z0-9_]+/', $sql)) {
                $issues[] = "[{$relPath}:{$lineNum}] Variable interpolation inside SQL prepare query string: {$sql}";
            }
        }
    }
}

if (empty($issues)) {
    echo "SUCCESS: No static flaws detected.\n";
} else {
    echo "WARNING: Found " . count($issues) . " potential issue(s):\n";
    foreach ($issues as $iss) {
        echo "- {$iss}\n";
    }
}
