<?php
if (!isset($_GET['c']) || !isset($_GET['s']) || !isset($_GET['dest'])) {
    header("HTTP/1.1 400 Bad Request");
    exit;
}

require_once __DIR__ . '/config.php';

$campaignId = (int)$_GET['c'];
$subscriberId = (int)$_GET['s'];
$destination = filter_var($_GET['dest'], FILTER_SANITIZE_URL);

try {
    $stmt = $db->prepare("INSERT INTO campaign_clicks (campaign_id, subscriber_id, clicked_at) VALUES (?, ?, ?)");
    $stmt->execute([$campaignId, $subscriberId, time()]);
    
    ModuleManager::triggerAction('link_clicked', $subscriberId, $campaignId, $destination);
} catch (Exception $e) {
    // Fail silently to prioritize the redirect UX
}

header("Location: " . $destination);
exit;