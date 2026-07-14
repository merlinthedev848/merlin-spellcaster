<?php
declare(strict_types=1);

/**
 * Custom routing definition for HelloWorld module.
 * Handled when the module is enabled.
 */
if ($routePath === '/hello-world') {
    $title = 'Hello World Extension';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}
