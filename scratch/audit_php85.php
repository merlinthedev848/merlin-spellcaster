<?php
declare(strict_types=1);

/**
 * PHP 8.5 Deep Compatibility Auditor
 */

$projectDir = __DIR__ . '/..';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($projectDir));

$phpFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        if (strpos($path, 'vendor') !== false || strpos($path, '.git') !== false) {
            continue;
        }
        $phpFiles[] = $path;
    }
}

echo "Auditing " . count($phpFiles) . " PHP files for PHP 8.5 compatibility...\n\n";

$issues = [];

foreach ($phpFiles as $filePath) {
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);
    $relPath = str_replace(realpath($projectDir) . DIRECTORY_SEPARATOR, '', realpath($filePath));

    foreach ($lines as $idx => $line) {
        $lineNum = $idx + 1;

        // 1. Check curl_close without PHP_VERSION_ID check
        if (preg_match('/\bcurl_close\s*\(/i', $line) && !preg_match('/PHP_VERSION_ID/i', $line)) {
            $issues[] = "[{$relPath}:{$lineNum}] Deprecated curl_close() in PHP 8.5: " . trim($line);
        }

        // 2. Check imagedestroy without PHP_VERSION_ID check
        if (preg_match('/\bimagedestroy\s*\(/i', $line) && !preg_match('/PHP_VERSION_ID/i', $line)) {
            $issues[] = "[{$relPath}:{$lineNum}] Deprecated imagedestroy() in PHP 8.5: " . trim($line);
        }

        // 3. Check implicit nullable parameters (e.g. string $param = null) deprecated in PHP 8.4/8.5
        if (preg_match('/function\s+\w+\s*\([^)]*?\b(string|int|array|float|bool|object)\s+\$(\w+)\s*=\s*null/i', $line, $m)) {
            $issues[] = "[{$relPath}:{$lineNum}] Deprecated implicit nullable parameter in PHP 8.4/8.5 ('{$m[1]} \${$m[2]} = null' should be '?{$m[1]} \${$m[2]} = null'): " . trim($line);
        }
    }
}

if (empty($issues)) {
    echo "SUCCESS: 0 PHP 8.5 compatibility issues found across all " . count($phpFiles) . " PHP files!\n";
} else {
    echo "FOUND " . count($issues) . " PHP 8.5 COMPATIBILITY ISSUES:\n";
    foreach ($issues as $iss) {
        echo "- {$iss}\n";
    }
}
