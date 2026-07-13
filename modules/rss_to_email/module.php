<?php
declare(strict_types=1);

ModuleManager::registerNavItem(
    'RSS to Email',
    '/modules/rss_to_email/pages/ui.php',
    'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z',
    ['ui'],
    'Campaigns',
    40
);

$db = $GLOBALS['db'] ?? null;
if ($db) {
    $db->exec("
    CREATE TABLE IF NOT EXISTS mod_rss_feeds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        feed_url VARCHAR(255) NOT NULL,
        list_id INT NOT NULL,
        frequency VARCHAR(50) DEFAULT 'weekly',
        last_checked_at DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}
