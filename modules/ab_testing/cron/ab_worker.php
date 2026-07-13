<?php
declare(strict_types=1);

/**
 * modules/ab_testing/cron/ab_worker.php
 * Run this via CLI: php ab_worker.php
 * 
 * Logic:
 * 1. Find campaigns that have an A/B test but haven't started.
 * 2. If it's time to send (campaign is scheduled/sending), we split the queue.
 * 3. We rewrite the subject in the email_queue table for 10% to A, 10% to B, and HOLD 80%.
 * 4. After 2 hours, we evaluate the winner, update the remaining 80% with the winning subject, and release the hold.
 */

if (php_sapi_name() !== 'cli' && !isset($_GET['secret'])) {
    die("CLI only or requires secret");
}

require_once dirname(__DIR__, 3) . '/config.php';

// 1. Process NEW tests that need splitting
$newTests = $db->query("SELECT t.*, c.status as campaign_status FROM mod_ab_tests t 
                        JOIN campaigns c ON t.campaign_id = c.id
                        WHERE t.test_started_at IS NULL AND c.status = 'sending'")->fetchAll();

foreach ($newTests as $test) {
    echo "Starting A/B split for Campaign ID: {$test['campaign_id']}\n";
    
    // Get all pending queue IDs for this campaign
    $queueIds = $db->query("SELECT id FROM email_queue WHERE campaign_id = {$test['campaign_id']} AND status = 'pending'")->fetchAll(PDO::FETCH_COLUMN);
    $total = count($queueIds);
    
    if ($total < 10) {
        echo "Not enough subscribers to split test. Reverting to standard send.\n";
        $db->prepare("UPDATE mod_ab_tests SET winner_chosen='A', test_started_at=NOW() WHERE id=?")->execute([$test['id']]);
        continue;
    }
    
    shuffle($queueIds);
    $testSize = (int)ceil($total * 0.10); // 10%
    
    $groupA = array_slice($queueIds, 0, $testSize);
    $groupB = array_slice($queueIds, $testSize, $testSize);
    $groupHold = array_slice($queueIds, $testSize * 2);
    
    // We don't have a 'subject' override in email_queue natively, so we have to use a hack or add a column.
    // The cleanest way without altering core table heavily is to alter email_queue to have an override_subject column.
    try {
        $db->exec("ALTER TABLE email_queue ADD COLUMN IF NOT EXISTS ab_subject VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $e) {}
    
    // Update Group A
    if (!empty($groupA)) {
        $inA = implode(',', $groupA);
        $db->prepare("UPDATE email_queue SET ab_subject = ? WHERE id IN ($inA)")->execute([$test['variant_a_subject']]);
    }
    
    // Update Group B
    if (!empty($groupB)) {
        $inB = implode(',', $groupB);
        $db->prepare("UPDATE email_queue SET ab_subject = ? WHERE id IN ($inB)")->execute([$test['variant_b_subject']]);
    }
    
    // Hold the rest (set send_at to 2 hours in the future so main cron ignores them)
    if (!empty($groupHold)) {
        $inHold = implode(',', $groupHold);
        $db->exec("UPDATE email_queue SET send_at = DATE_ADD(NOW(), INTERVAL 2 HOUR) WHERE id IN ($inHold)");
    }
    
    $db->prepare("UPDATE mod_ab_tests SET test_started_at=NOW() WHERE id=?")->execute([$test['id']]);
}

// 2. Evaluate ONGOING tests (older than 2 hours)
$ongoingTests = $db->query("SELECT t.* FROM mod_ab_tests t 
                            WHERE t.test_started_at <= DATE_SUB(NOW(), INTERVAL 2 HOUR) 
                            AND t.winner_chosen IS NULL")->fetchAll();

foreach ($ongoingTests as $test) {
    echo "Evaluating winner for Campaign ID: {$test['campaign_id']}\n";
    
    // Calculate opens for A vs B
    // Since we didn't track which subscriber got A vs B in the opens table, we approximate by looking at the ab_subject assigned to them in email_queue
    $st = $db->prepare("
        SELECT eq.ab_subject, COUNT(co.id) as opens 
        FROM email_queue eq
        LEFT JOIN campaign_opens co ON eq.subscriber_id = co.subscriber_id AND eq.campaign_id = co.campaign_id
        WHERE eq.campaign_id = ? AND eq.ab_subject IS NOT NULL
        GROUP BY eq.ab_subject
    ");
    $st->execute([$test['campaign_id']]);
    $results = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $opensA = (int)($results[$test['variant_a_subject']] ?? 0);
    $opensB = (int)($results[$test['variant_b_subject']] ?? 0);
    
    $winner = 'A';
    $winningSubject = $test['variant_a_subject'];
    
    if ($opensB > $opensA) {
        $winner = 'B';
        $winningSubject = $test['variant_b_subject'];
    }
    
    echo "Winner is Variant $winner ($winningSubject) - A: $opensA, B: $opensB\n";
    
    // Apply winner to main campaign and release the hold
    $db->prepare("UPDATE campaigns SET subject = ? WHERE id = ?")->execute([$winningSubject, $test['campaign_id']]);
    $db->prepare("UPDATE mod_ab_tests SET winner_chosen = ? WHERE id = ?")->execute([$winner, $test['id']]);
    
    // Release the hold (reset send_at)
    $db->prepare("UPDATE email_queue SET send_at = NOW() WHERE campaign_id = ? AND status = 'pending' AND send_at > NOW()")->execute([$test['campaign_id']]);
}

echo "A/B worker finished.\n";
