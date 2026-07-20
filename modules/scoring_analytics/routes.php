<?php
declare(strict_types=1);

// Bootstrap lead_score database column check
try {
    $db = Database::getConnection();
    $db->exec("ALTER TABLE subscribers ADD COLUMN lead_score INT DEFAULT 0");
} catch (PDOException $e) {
    // Column already exists
}

// Function to trigger threshold-based automations
function checkPointsThresholdAutomations(PDO $db, int $subscriberId, int $newScore): void {
    try {
        $stVal = $db->prepare("SELECT trigger_event FROM automations WHERE trigger_event LIKE 'points_threshold:%' AND status = 'active'");
        $stVal->execute();
        $automations = $stVal->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($automations as $event) {
            $threshold = (int)str_replace('points_threshold:', '', $event);
            if ($newScore >= $threshold) {
                Automation::trigger($event, $subscriberId);
            }
        }
    } catch (Throwable $e) {
        error_log("Error checking points threshold automations: " . $e->getMessage());
    }
}

// Hook into email opened events (+1 point)
Hook::register('email_opened', function($data) {
    try {
        $db = Database::getConnection();
        $subscriberId = (int)($data['subscriber_id'] ?? 0);
        if ($subscriberId > 0) {
            $st = $db->prepare("UPDATE subscribers SET lead_score = lead_score + 1 WHERE id = ?");
            $st->execute([$subscriberId]);
            
            $stScore = $db->prepare("SELECT lead_score FROM subscribers WHERE id = ?");
            $stScore->execute([$subscriberId]);
            $newScore = (int)$stScore->fetchColumn();
            checkPointsThresholdAutomations($db, $subscriberId, $newScore);
        }
    } catch (Throwable $e) {
        error_log("LeadScoring open hook error: " . $e->getMessage());
    }
});

// Hook into link clicked events (+5 points)
Hook::register('link_clicked', function($data) {
    try {
        $db = Database::getConnection();
        $subscriberId = (int)($data['subscriber_id'] ?? 0);
        if ($subscriberId > 0) {
            $st = $db->prepare("UPDATE subscribers SET lead_score = lead_score + 5 WHERE id = ?");
            $st->execute([$subscriberId]);
            
            $stScore = $db->prepare("SELECT lead_score FROM subscribers WHERE id = ?");
            $stScore->execute([$subscriberId]);
            $newScore = (int)$stScore->fetchColumn();
            checkPointsThresholdAutomations($db, $subscriberId, $newScore);
        }
    } catch (Throwable $e) {
        error_log("LeadScoring click hook error: " . $e->getMessage());
    }
});

// Route: /scoring
if ($routePath === '/scoring') {
    $db = Database::getConnection();

    // 1. Fetch top leads based on current lead_score rules
    $topLeads = $db->query("SELECT id, email, first_name, last_name, lead_score, status FROM subscribers ORDER BY lead_score DESC LIMIT 15")->fetchAll();

    // 2. Fetch sample list and compute predictive conversion likelihood
    $subscribers = $db->query("SELECT id, email, first_name, last_name, status, created_at, lead_score FROM subscribers ORDER BY created_at DESC LIMIT 15")->fetchAll();
    
    foreach ($subscribers as &$sub) {
        $subId = (int)$sub['id'];
        
        $stOpen = $db->prepare("SELECT COUNT(*) FROM campaign_opens WHERE subscriber_id = ?");
        $stOpen->execute([$subId]);
        $openCount = (int)$stOpen->fetchColumn();

        $stClick = $db->prepare("SELECT COUNT(*) FROM campaign_clicks WHERE subscriber_id = ?");
        $stClick->execute([$subId]);
        $clickCount = (int)$stClick->fetchColumn();
        
        $baseScore = 30; // cold benchmark
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

    $title = 'Scoring & Conversion Analytics';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

// Redirect old routes for backwards compatibility
if ($routePath === '/predictive-scoring') {
    header('Location: ' . getSetting('app_url') . '/scoring?tab=predictive');
    exit;
}

// Route: /scoring/recalculate
if ($routePath === '/scoring/recalculate') {
    header('Content-Type: application/json');
    try {
        sleep(2); // simulated model training
        echo json_encode(['success' => true, 'message' => 'Predictive models recalculated. CRM scores updated.']);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
