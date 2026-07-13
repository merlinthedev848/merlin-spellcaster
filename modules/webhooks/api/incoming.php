<?php
declare(strict_types=1);

// Incoming webhook handler
require_once dirname(__DIR__, 3) . '/config.php';

header('Content-Type: application/json');

// Accept JSON or form-urlencoded
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$email = trim($data['email'] ?? '');
$listId = (int)($data['list_id'] ?? 0);
$firstName = trim($data['first_name'] ?? '');
$lastName = trim($data['last_name'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$listId) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid email and list_id are required.']);
    exit;
}

try {
    // 1. Insert or update subscriber
    $st = $db->prepare("INSERT INTO subscribers (email, first_name, last_name, status) 
                        VALUES (?, ?, ?, 'active') 
                        ON DUPLICATE KEY UPDATE 
                        first_name = COALESCE(NULLIF(VALUES(first_name),''), first_name),
                        last_name = COALESCE(NULLIF(VALUES(last_name),''), last_name),
                        status = 'active'");
    $st->execute([$email, $firstName, $lastName]);
    
    // Get subscriber ID
    $subId = (int)$db->query("SELECT id FROM subscribers WHERE email = " . $db->quote($email))->fetchColumn();
    
    // 2. Add to list
    $db->prepare("INSERT IGNORE INTO subscriber_lists (subscriber_id, list_id, status) VALUES (?, ?, 'confirmed')")
       ->execute([$subId, $listId]);
       
    // Update list count
    $db->prepare("UPDATE lists SET subscriber_count = (SELECT COUNT(*) FROM subscriber_lists WHERE list_id = lists.id AND status='confirmed') WHERE id = ?")
       ->execute([$listId]);

    echo json_encode(['success' => true, 'message' => 'Subscriber added successfully.', 'subscriber_id' => $subId]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
