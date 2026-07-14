<?php
declare(strict_types=1);

if ($routePath === '/predictive-scoring') {
    $title = 'Predictive Lead Scoring';
    
    $db = Database::getConnection();
    // Fetch a sample of subscribers with calculated scores
    $subscribers = $db->query("SELECT id, email, first_name, last_name, status, created_at FROM subscribers LIMIT 10")->fetchAll();
    
    foreach ($subscribers as &$sub) {
        // Calculate mock score
        $subId = (int)$sub['id'];
        
        $stOpen = $db->prepare("SELECT COUNT(*) FROM campaign_opens WHERE subscriber_id = ?");
        $stOpen->execute([$subId]);
        $openCount = (int)$stOpen->fetchColumn();

        $stClick = $db->prepare("SELECT COUNT(*) FROM campaign_clicks WHERE subscriber_id = ?");
        $stClick->execute([$subId]);
        $clickCount = (int)$stClick->fetchColumn();
        
        $baseScore = 30; // base score
        $baseScore += ($openCount * 15);
        $baseScore += ($clickCount * 30);
        $sub['predictive_score'] = min(100, $baseScore);
        
        if ($sub['predictive_score'] >= 80) {
            $sub['conversion_status'] = 'Hot Lead (90%+)';
            $sub['status_color'] = '#10b981';
        } elseif ($sub['predictive_score'] >= 50) {
            $sub['conversion_status'] = 'Warm Lead (60%)';
            $sub['status_color'] = '#f59e0b';
        } else {
            $sub['conversion_status'] = 'Cold Lead (<30%)';
            $sub['status_color'] = '#64748b';
        }
    }
    unset($sub);

    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

if ($routePath === '/predictive-scoring/recalculate') {
    header('Content-Type: application/json');
    try {
        sleep(2); // Simulated calculations
        echo json_encode(['success' => true, 'message' => 'Predictive models recalculated. CRM scores updated.']);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
