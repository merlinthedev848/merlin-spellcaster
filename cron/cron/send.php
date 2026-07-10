<?php
set_time_limit(55); 
require_once __DIR__ . '/../config.php';

$batchSize = ModuleManager::applyFilter('cron_batch_size', 50);

// Use MariaDB FOR UPDATE SKIP LOCKED to prevent duplicate sends if cron overlaps
$db->beginTransaction();
$stmt = $db->prepare("
    SELECT q.id as queue_id, q.campaign_id, q.subscriber_id, c.subject, c.body_html, s.email 
    FROM email_queue q
    JOIN campaigns c ON q.campaign_id = c.id
    JOIN subscribers s ON q.subscriber_id = s.id
    WHERE q.status = 'pending' AND q.send_at <= ? 
    LIMIT ? FOR UPDATE SKIP LOCKED
");
$stmt->execute([time(), $batchSize]);
$jobs = $stmt->fetchAll();

if (empty($jobs)) {
    $db->rollBack();
    exit("Queue empty.");
}

foreach ($jobs as $job) {
    $processedBody = ModuleManager::applyFilter('before_send_body', $job['body_html'], $job['subscriber_id']);
    
    // [Insert SMTP/API Delivery Logic Here]
    $mailSuccess = true; 

    if ($mailSuccess) {
        $update = $db->prepare("UPDATE email_queue SET status = 'sent' WHERE id = ?");
        $update->execute([$job['queue_id']]);
        ModuleManager::triggerAction('email_sent_success', $job['subscriber_id'], $job['campaign_id']);
    }
}
$db->commit();
echo "Processed " . count($jobs) . " emails.";