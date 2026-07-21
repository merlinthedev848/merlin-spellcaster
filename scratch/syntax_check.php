<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);

$errors = 0;
$count = 0;

foreach ($files as $file) {
    if ($file->getExtension() === 'php' && strpos($file->getPathname(), '.git') === false) {
        $count++;
        $cmd = 'php -l ' . escapeshellarg($file->getPathname());
        $output = [];
        $returnVar = 0;
        exec($cmd, $output, $returnVar);

        if ($returnVar !== 0) {
            echo "SYNTAX ERROR in " . $file->getPathname() . ":\n" . implode("\n", $output) . "\n\n";
            $errors++;
        }
    }
}

if ($errors === 0) {
    echo "SUCCESS: Checked {$count} PHP files. 0 syntax errors found!\n";
} else {
    echo "FAILED: Found {$errors} file(s) with syntax errors!\n";
}
