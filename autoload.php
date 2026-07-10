<?php
declare(strict_types=1);
/**
 * autoload.php — PSR-4 compatible class autoloader
 * PHP 8.5+ — Zero dependencies, no Composer required.
 *
 * Automatically loads classes from:
 *   core/     → Auth, Mailer, TemplateEngine, ModuleManager
 *   modules/  → module classes (if any)
 */

spl_autoload_register(static function (string $className): void {
    // Map of known class → file (flat structure, no namespaces needed)
    static $classMap = [
        'Auth'           => '/core/Auth.php',
        'Mailer'         => '/core/Mailer.php',
        'TemplateEngine' => '/core/TemplateEngine.php',
        'ModuleManager'  => '/core/ModuleManager.php',
    ];

    if (isset($classMap[$className])) {
        $file = __DIR__ . $classMap[$className];
        if (file_exists($file)) {
            require_once $file;
        }
        return;
    }

    // Fallback: scan core/ directory for {ClassName}.php
    $file = __DIR__ . '/core/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
