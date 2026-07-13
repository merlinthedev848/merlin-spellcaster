<?php
declare(strict_types=1);

ModuleManager::registerNavItem(
    'Link Rotator',
    '/modules/link_rotator/pages/ui.php',
    'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
    ['ui'],
    'Campaigns',
    35
);

// We need a DB table for this
$db = $GLOBALS['db'] ?? null;
if ($db) {
    $db->exec("
    CREATE TABLE IF NOT EXISTS mod_link_rotators (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(100) UNIQUE NOT NULL,
        destinations TEXT NOT NULL,
        clicks INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}
