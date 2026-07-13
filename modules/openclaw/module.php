<?php
declare(strict_types=1);

ModuleManager::registerNavItem(
    'OpenClaw API',
    '/modules/openclaw/pages/ui.php',
    'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    ['ui'],
    'Integrations',
    60
);

$db = $GLOBALS['db'] ?? null;
if ($db) {
    try {
        $db->exec("
        CREATE TABLE IF NOT EXISTS mod_openclaw_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agent_name VARCHAR(100) NOT NULL,
            api_key VARCHAR(64) UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Exception $e) {}
}
