<?php
declare(strict_types=1);

require_once __DIR__ . '/Verifier.php';

// 1. Hook into database entry validations
Hook::register('before_add_contact', function(&$data) {
    $res = EmailVerifier::verify($data['email']);
    if (!$res['valid']) {
        $data['valid'] = false;
        $data['error'] = $res['reason'];
    }
});

// 2. Custom Module routes
if ($routePath === '/verifier') {
    $db = Database::getConnection();
    
    // Fetch stats
    $total = (int)$db->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();
    $active = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE status='active'")->fetchColumn();
    $bounced = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE status='bounced'")->fetchColumn();
    $unsubscribed = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE status='unsubscribed'")->fetchColumn();

    $title = 'Email Verifier Dashboard';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

if ($routePath === '/verifier/scan') {
    header('Content-Type: application/json');
    try {
        $db = Database::getConnection();
        // Fetch active subscriber IDs
        $ids = $db->query("SELECT id FROM subscribers WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'ids' => array_map('intval', $ids)]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($routePath === '/verifier/scan-batch') {
    header('Content-Type: application/json');
    
    // Read JSON POST body
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $ids = $data['ids'] ?? [];
    
    if (empty($ids)) {
        echo json_encode(['success' => true, 'processed' => 0, 'results' => []]);
        exit;
    }
    
    $results = [];
    $db = Database::getConnection();
    
    // Ensure the bounced tag exists in database
    $stTagCheck = $db->prepare("SELECT id FROM tags WHERE name = ?");
    $stTagCheck->execute(['bounced']);
    $tagId = $stTagCheck->fetchColumn();
    if (!$tagId) {
        $stInsertTag = $db->prepare("INSERT INTO tags (name, color) VALUES (?, ?)");
        $stInsertTag->execute(['bounced', '#e53e3e']);
        $tagId = (int)$db->lastInsertId();
    } else {
        $tagId = (int)$tagId;
    }
    
    // Prepare queries
    $stSub = $db->prepare("SELECT email FROM subscribers WHERE id = ? AND status = 'active'");
    $stBounce = $db->prepare("UPDATE subscribers SET status = 'bounced' WHERE id = ?");
    $stFlushQueue = $db->prepare("DELETE FROM email_queue WHERE subscriber_id = ? AND status = 'pending'");
    
    foreach ($ids as $id) {
        $id = (int)$id;
        $stSub->execute([$id]);
        $email = $stSub->fetchColumn();
        
        if (!$email) {
            $results[] = ['id' => $id, 'valid' => true, 'skipped' => true];
            continue;
        }
        
        $res = EmailVerifier::verify((string)$email);
        
        if (!$res['valid']) {
            $db->beginTransaction();
            try {
                // Update subscriber status to bounced to suppress further mails
                $stBounce->execute([$id]);
                
                // Remove pending queue elements to prevent accidental delivery
                $stFlushQueue->execute([$id]);
                
                // Strip all marketing tags
                $db->prepare("DELETE FROM subscriber_tags WHERE subscriber_id = ?")->execute([$id]);
                
                // Assign the red bounced tag
                $db->prepare("INSERT INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)")->execute([$id, $tagId]);
                
                logActivity($id, 'bounce', "Email Verifier flagged deliverability: " . $res['reason']);
                
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
            }
        }
        
        $results[] = [
            'id' => $id,
            'email' => $email,
            'valid' => $res['valid'],
            'reason' => $res['reason']
        ];
    }
    
    echo json_encode(['success' => true, 'results' => $results]);
    exit;
}
