<?php
declare(strict_types=1);

// Bootstrap lead_score database column check
try {
    $db = Database::getConnection();
    // Wrap ALTER in a try-catch to ignore duplicate column error
    $db->exec("ALTER TABLE subscribers ADD COLUMN lead_score INT DEFAULT 0");
} catch (PDOException $e) {
    // Column already exists or table doesn't support ALTER
}

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
