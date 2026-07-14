<?php
declare(strict_types=1);

if ($routePath === '/personalization') {
    $title = 'Dynamic Web Personalization';
    $appUrl = getSetting('app_url', 'http://localhost/merlin-spellcaster');
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

if ($routePath === '/personalization/api') {
    // API endpoint called by tracking pixel script to serve tags
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');
    
    $subId = (int)($_GET['sub_id'] ?? 0);
    
    if ($subId <= 0) {
        echo json_encode(['success' => false, 'tags' => []]);
        exit;
    }

    try {
        $db = Database::getConnection();
        $st = $db->prepare("
            SELECT t.name 
            FROM tags t
            JOIN subscriber_tags st ON st.tag_id = t.id
            WHERE st.subscriber_id = ?
        ");
        $st->execute([$subId]);
        $tags = $st->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'success' => true,
            'subscriber_id' => $subId,
            'tags' => $tags
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
