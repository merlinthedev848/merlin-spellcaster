<?php
declare(strict_types=1);

// Bootstrap A/B testing database schema
try {
    $db = Database::getConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS mod_ab_tests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT NOT NULL,
            variant_a_subject VARCHAR(255) NOT NULL,
            variant_b_subject VARCHAR(255) NOT NULL,
            winner_chosen VARCHAR(10) DEFAULT NULL,
            test_started_at DATETIME DEFAULT NULL,
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (PDOException $e) {
    error_log("AB testing database bootstrap error: " . $e->getMessage());
}

// Hook: campaign_form_after_subject (Displays A/B test form group in Campaign Creator)
Hook::register('campaign_form_after_subject', function() {
    $db = Database::getConnection();
    $subjectB = '';
    $abEnabled = false;
    
    // If editing campaign, load existing variant details
    $campaignId = (int)($_GET['id'] ?? 0);
    if ($campaignId > 0) {
        $st = $db->prepare("SELECT variant_b_subject FROM mod_ab_tests WHERE campaign_id = ?");
        $st->execute([$campaignId]);
        $subjectB = $st->fetchColumn();
        if ($subjectB) {
            $abEnabled = true;
        }
    }
    ?>
    <div style="margin-top: 16px; background-color: rgba(99, 91, 255, 0.05); border: 1px dashed rgba(99, 91, 255, 0.2); border-radius: 8px; padding: 16px;">
        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; font-size: 13px; color: var(--stripe-dark);">
            <input type="checkbox" name="ab_test_enabled" value="1" <?= $abEnabled ? 'checked' : '' ?> onchange="document.getElementById('ab-test-fields').style.display = this.checked ? 'block' : 'none';" style="cursor: pointer; accent-color: var(--stripe-blurple);">
            Enable A/B Subject Line Split Testing
        </label>
        <div id="ab-test-fields" style="display: <?= $abEnabled ? 'block' : 'none' ?>; margin-top: 12px;">
            <p style="font-size: 12px; color: var(--stripe-dark-slate); margin-bottom: 8px; line-height: 1.5;">
                Test A will use the main subject line specified above. Enter the subject line for Test B below.
                We will send A to 10% of targets, B to 10% of targets, wait 2 hours, select the winning version (by open rates), and send that version to the remaining 80%.
            </p>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="ab_subject_b">Test B Subject Line</label>
                <input class="form-control" type="text" id="ab_subject_b" name="ab_subject_b" value="<?= e($subjectB) ?>" placeholder="e.g. You don't want to miss this...">
            </div>
        </div>
    </div>
    <?php
});

// Hook: campaign_saved (Fires when campaign is saved/edited)
Hook::register('campaign_saved', function($data) {
    try {
        $db = Database::getConnection();
        $campaignId = (int)($data['campaign_id'] ?? 0);
        $postData = $data['post_data'] ?? [];
        
        $abEnabled = isset($postData['ab_test_enabled']);
        $subjectB = trim($postData['ab_subject_b'] ?? '');

        // Remove old configuration
        $db->prepare("DELETE FROM mod_ab_tests WHERE campaign_id = ?")->execute([$campaignId]);

        if ($abEnabled && $subjectB !== '') {
            $st = $db->prepare("SELECT subject FROM campaigns WHERE id = ?");
            $st->execute([$campaignId]);
            $subjectA = $st->fetchColumn() ?: 'Campaign Announcement';

            $stInsert = $db->prepare("INSERT INTO mod_ab_tests (campaign_id, variant_a_subject, variant_b_subject) VALUES (?, ?, ?)");
            $stInsert->execute([$campaignId, $subjectA, $subjectB]);
        }
    } catch (Throwable $e) {
        error_log("ABTesting campaign_saved hook error: " . $e->getMessage());
    }
});

// Route: worker triggering endpoint
if ($routePath === '/ab/work') {
    header('Content-Type: application/json');
    $db = Database::getConnection();
    $summary = ['started' => [], 'evaluated' => []];

    try {
        // 1. Split tests that are newly scheduled/sending
        $newTests = $db->query("
            SELECT t.*, c.status FROM mod_ab_tests t 
            JOIN campaigns c ON t.campaign_id = c.id
            WHERE t.test_started_at IS NULL AND c.status = 'sending'
        ")->fetchAll();

        foreach ($newTests as $test) {
            $campaignId = (int)$test['campaign_id'];

            // Get pending queue items
            $stQ = $db->prepare("SELECT id FROM email_queue WHERE campaign_id = ? AND status = 'pending'");
            $stQ->execute([$campaignId]);
            $queueIds = $stQ->fetchAll(PDO::FETCH_COLUMN);
            $total = count($queueIds);

            if ($total < 10) {
                // Not enough subscribers, fallback to standard Variant A send
                $db->prepare("UPDATE mod_ab_tests SET winner_chosen = 'A', test_started_at = NOW() WHERE id = ?")
                   ->execute([$test['id']]);
                $summary['started'][] = "Campaign #{$campaignId} - Too small, bypassed to A";
                continue;
            }

            shuffle($queueIds);
            $testSize = (int)ceil($total * 0.10); // 10%

            $groupA = array_slice($queueIds, 0, $testSize);
            $groupB = array_slice($queueIds, $testSize, $testSize);
            $groupHold = array_slice($queueIds, $testSize * 2);

            // Update Group A Override
            if (!empty($groupA)) {
                $inA = implode(',', array_map('intval', $groupA));
                $db->exec("UPDATE email_queue SET ab_subject = " . $db->quote($test['variant_a_subject']) . " WHERE id IN ($inA)");
            }

            // Update Group B Override
            if (!empty($groupB)) {
                $inB = implode(',', array_map('intval', $groupB));
                $db->exec("UPDATE email_queue SET ab_subject = " . $db->quote($test['variant_b_subject']) . " WHERE id IN ($inB)");
            }

            // Hold remaining 80% (Set send_at to 2 hours in future)
            if (!empty($groupHold)) {
                $inHold = implode(',', array_map('intval', $groupHold));
                $db->exec("UPDATE email_queue SET send_at = DATE_ADD(NOW(), INTERVAL 2 HOUR) WHERE id IN ($inHold)");
            }

            $db->prepare("UPDATE mod_ab_tests SET test_started_at = NOW() WHERE id = ?")->execute([$test['id']]);
            $summary['started'][] = "Campaign #{$campaignId} - Split 10% A / 10% B / 80% Hold";
        }

        // 2. Evaluate split tests older than 2 hours
        $ongoingTests = $db->query("
            SELECT t.* FROM mod_ab_tests t 
            WHERE t.test_started_at <= DATE_SUB(NOW(), INTERVAL 2 HOUR) 
            AND t.winner_chosen IS NULL
        ")->fetchAll();

        foreach ($ongoingTests as $test) {
            $campaignId = (int)$test['campaign_id'];

            // Query opens for Variant A vs B
            $stA = $db->prepare("
                SELECT COUNT(co.id) FROM email_queue eq
                JOIN campaign_opens co ON eq.subscriber_id = co.subscriber_id AND eq.campaign_id = co.campaign_id
                WHERE eq.campaign_id = ? AND eq.ab_subject = ?
            ");
            $stA->execute([$campaignId, $test['variant_a_subject']]);
            $opensA = (int)$stA->fetchColumn();

            $stB = $db->prepare("
                SELECT COUNT(co.id) FROM email_queue eq
                JOIN campaign_opens co ON eq.subscriber_id = co.subscriber_id AND eq.campaign_id = co.campaign_id
                WHERE eq.campaign_id = ? AND eq.ab_subject = ?
            ");
            $stB->execute([$campaignId, $test['variant_b_subject']]);
            $opensB = (int)$stB->fetchColumn();

            $winner = 'A';
            $winningSubject = $test['variant_a_subject'];

            if ($opensB > $opensA) {
                $winner = 'B';
                $winningSubject = $test['variant_b_subject'];
            }

            // Update main campaign subject
            $db->prepare("UPDATE campaigns SET subject = ? WHERE id = ?")->execute([$winningSubject, $campaignId]);
            $db->prepare("UPDATE mod_ab_tests SET winner_chosen = ? WHERE id = ?")->execute([$winner, $test['id']]);

            // Release hold (Set remaining to NOW so processor picks them up)
            $db->prepare("UPDATE email_queue SET send_at = NOW() WHERE campaign_id = ? AND status = 'pending' AND send_at > NOW()")
               ->execute([$campaignId]);

            $summary['evaluated'][] = "Campaign #{$campaignId} - Variant {$winner} won (A: {$opensA} opens, B: {$opensB} opens)";
        }

        echo json_encode(['success' => true, 'summary' => $summary]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
