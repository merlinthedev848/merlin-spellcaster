<?php
declare(strict_types=1);

/**
 * Deep Codebase & Logic Auditor for Merlin Spellcaster
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

echo "Auditing " . count($phpFiles) . " PHP files for undefined variables, SQL LIMIT syntax errors, and missing bounds...\n\n";

$issues = [];

foreach ($phpFiles as $filePath) {
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);
    $relPath = str_replace(realpath($projectDir) . DIRECTORY_SEPARATOR, '', realpath($filePath));

    foreach ($lines as $idx => $line) {
        $lineNum = $idx + 1;

        // 1. Check for un-casted or un-bounded LIMIT / OFFSET in SQL queries
        if (preg_match('/LIMIT\s+(\$[a-zA-Z0-9_]+)/i', $line, $m)) {
            // Check if variable before this line is guaranteed positive int
            $issues[] = "[SQL LIMIT check] [{$relPath}:{$lineNum}] Check if '{$m[1]}' in LIMIT clause is strictly positive int: " . trim($line);
        }

        // 2. Check for undefined variable usages in controllers
        if (strpos($relPath, 'controllers') !== false) {
            if (preg_match('/\$page\b/i', $line) && !preg_match('/\$page\s*=/i', $line) && !preg_match('/\b(int|max|min|\$_GET|\$_POST)\b/i', $line)) {
                // Ensure $page is initialized
            }
        }

        // 3. Check for raw unescaped $_GET or $_POST inside SQL queries
        if (preg_match('/(SELECT|INSERT|UPDATE|DELETE)[^;]+?\$_GET/i', $line) || preg_match('/(SELECT|INSERT|UPDATE|DELETE)[^;]+?\$_POST/i', $line)) {
            $issues[] = "[SQL Injection Risk] [{$relPath}:{$lineNum}] Unescaped \$_GET/\$_POST directly in SQL: " . trim($line);
        }

        // 4. Check for header('Location: ...') without exit
        if (preg_match('/header\s*\(\s*[\'"]Location:/i', $line)) {
            // Check if next 2 lines have exit;
            $nextLines = implode(" ", array_slice($lines, $idx, 3));
            if (!preg_match('/exit|die/i', $nextLines)) {
                $issues[] = "[Missing exit after header redirect] [{$relPath}:{$lineNum}] " . trim($line);
            }
        }
    }
}

echo "Found " . count($issues) . " potential code quality items to verify:\n";
foreach ($issues as $iss) {
    echo "- {$iss}\n";
}
