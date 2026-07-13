<?php
declare(strict_types=1);

// Hook into email opens
ModuleManager::addHook('email_opened', function($campaignId, $subscriberId) {
    global $db;
    if (!$db) return;
    try {
        $db->prepare("UPDATE subscribers SET lead_score = lead_score + 1 WHERE id = ?")
           ->execute([$subscriberId]);
    } catch (Throwable $e) {}
});

// Hook into link clicks
ModuleManager::addHook('link_clicked', function($campaignId, $subscriberId, $url) {
    global $db;
    if (!$db) return;
    try {
        $db->prepare("UPDATE subscribers SET lead_score = lead_score + 5 WHERE id = ?")
           ->execute([$subscriberId]);
    } catch (Throwable $e) {}
});

// Hook into the subscriber view page to display the score
ModuleManager::addHook('admin_init', function($page) {
    if ($page === 'subscriber_view') {
        // We could inject UI here if we added a hook to subscriber_view.php,
        // but for now the score is passively tracked.
    }
});
