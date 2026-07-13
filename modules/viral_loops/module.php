<?php
declare(strict_types=1);

ModuleManager::registerNavItem(
    'Viral Loops',
    '/modules/viral_loops/pages/ui.php',
    'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    ['ui'],
    'Settings',
    50
);

$db = $GLOBALS['db'] ?? null;
if ($db) {
    try {
        $db->exec("ALTER TABLE subscribers ADD COLUMN IF NOT EXISTS referral_code VARCHAR(20) UNIQUE DEFAULT NULL");
        $db->exec("ALTER TABLE subscribers ADD COLUMN IF NOT EXISTS referred_by INT DEFAULT NULL");
        $db->exec("ALTER TABLE subscribers ADD COLUMN IF NOT EXISTS referral_count INT DEFAULT 0");
    } catch (Exception $e) {}
}
