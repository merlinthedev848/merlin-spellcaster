<?php
declare(strict_types=1);

// Route: /engagement
if ($routePath === '/engagement') {
    $db = Database::getConnection();
    $tab = $_GET['tab'] ?? 'abtesting';
    $title = 'Engagement & Survey Powerhouse Suite';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

// Redirect aliases
if (in_array($routePath, ['/ab-testing', '/survey-builder', '/surveys'], true)) {
    $aliasTab = match($routePath) {
        '/ab-testing' => 'abtesting',
        '/survey-builder', '/surveys' => 'surveys',
        default => 'abtesting'
    };
    header('Location: ' . getSetting('app_url') . '/engagement?tab=' . $aliasTab);
    exit;
}
