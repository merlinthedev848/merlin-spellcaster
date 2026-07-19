<?php
declare(strict_types=1);

// Set required headers for CORS since tracking pixel will be placed on external domains
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require dirname(__DIR__) . '/config.php';

$subId = $_GET['s'] ?? $_POST['s'] ?? null;
$url = $_GET['u'] ?? $_POST['u'] ?? null;

if (!$subId || !is_numeric($subId) || !$url) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing subscriber ID or URL']);
    exit;
}

$subId = (int)$subId;
$url = substr(trim((string)$url), 0, 2048);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

try {
    // Basic subscriber validation without leakage
    $stCheck = $db->prepare("SELECT id FROM subscribers WHERE id = ? LIMIT 1");
    $stCheck->execute([$subId]);
    if ($stCheck->fetchColumn()) {
        $st = $db->prepare("INSERT INTO contact_visits (subscriber_id, url, ip_address, visited_at) VALUES (?, ?, ?, NOW())");
        $st->execute([$subId, $url, $ip]);
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log("Tracking pixel error: " . $e->getMessage());
    // Return success to prevent leak in error cases too
    echo json_encode(['success' => true]);
}
