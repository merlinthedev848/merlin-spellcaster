<?php
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

try {
    $db = Database::getConnection();
    
    // 1. Ensure the tag "checked-in" exists
    $tagName = 'checked-in';
    $stTag = $db->prepare("SELECT id FROM tags WHERE name = ?");
    $stTag->execute([$tagName]);
    $tagId = $stTag->fetchColumn();
    
    if (!$tagId) {
        $stInsertTag = $db->prepare("INSERT INTO tags (name, created_at) VALUES (?, NOW())");
        $stInsertTag->execute([$tagName]);
        $tagId = (int)$db->lastInsertId();
        echo "Created tag '{$tagName}' with ID {$tagId}.\n";
    } else {
        $tagId = (int)$tagId;
        echo "Found tag '{$tagName}' with ID {$tagId}.\n";
    }
    
    // 2. Find all subscribers who were sent campaign 6
    $stSubs = $db->prepare("SELECT DISTINCT subscriber_id FROM email_queue WHERE campaign_id = 6 AND status = 'sent'");
    $stSubs->execute();
    $subscriberIds = $stSubs->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found " . count($subscriberIds) . " subscribers who were sent campaign 6.\n";
    
    // 3. Link them in subscriber_tags
    $stLink = $db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)");
    $linkedCount = 0;
    foreach ($subscriberIds as $subId) {
        $stLink->execute([(int)$subId, $tagId]);
        if ($stLink->rowCount() > 0) {
            $linkedCount++;
        }
    }
    
    echo "Successfully linked {$linkedCount} new subscribers to the '{$tagName}' tag.\n";
    echo "This script has executed successfully and has deleted itself for security.\n";
    
    // Auto-delete for security
    @unlink(__FILE__);
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
