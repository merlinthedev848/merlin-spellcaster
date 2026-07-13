<?php
declare(strict_types=1);

// Register Navigation Item
ModuleManager::registerNavItem(
    'Market Research',
    '/admin/research.php',
    'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    ['research', 'survey_create', 'survey_view', 'scraper', 'scraper_job', 'hub'],
    'Research',
    10
);

// We will map /admin/research.php to our hub page via a hook, OR we can just create the page directly.
// For simplicity, we just use the existing admin/research.php and enhance it, OR we redirect it.
// Let's hook into the top of admin/research.php to redirect to our new hub.
ModuleManager::addHook('admin_init', function($page) {
    if ($page === 'research' && !isset($_GET['old'])) {
        header('Location: /modules/market_research/pages/hub.php');
        exit;
    }
});
