<?php
declare(strict_types=1);

/**
 * autoload.php — PSR-4 Class Autoloader
 * Automatically maps class names to files in core/ and controllers/ directories.
 */

spl_autoload_register(static function (string $class): void {
    $paths = [
        __DIR__ . '/core/',
        __DIR__ . '/controllers/',
    ];

    foreach ($paths as $path) {
        $file = $path . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
