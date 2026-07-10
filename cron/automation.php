<?php
/**
 * cron/automation.php — Automation sequence processor
 * PHP 8.5+
 */
declare(strict_types=1);
ignore_user_abort(true);
set_time_limit(120);

$secret = $_GET['secret'] ?? $_SERVER['argv'][1] ?? '';
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/core/Mailer.php';
require_once dirname(__DIR__) . '/core/TemplateEngine.php';

$cronSecret = getSetting('cron_secret');
if ($cronSecret && $secret !== $cronSecret && php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Unauthorized\n");
}

$appUrl  = getSetting('app_url', 'http://localhost');
$mailer  = new Mailer();
$engine  = new TemplateEngine();
$batchSize = 20;
$processed = 0;

try {
    $db->beginTransaction();
    $st = $db->prepare("SELECT aq.id as queue_id, aq.sequence_id, aq.step_id, aq.subscriber_id,
                               as2.type, as2.subject, as2.body_html, as2.tag_action, as2.tag_value, as2.webhook_url,
                               s.email, s.first_name, s.last_name, s.attributes, s.tags
                        FROM automation_queue aq
                        JOIN automation_steps as2 ON as2.id = aq.step_id
                        JOIN subscribers s ON s.id = aq.subscriber_id
                        JOIN automation_sequences aseq ON aseq.id = aq.sequence_id
                        WHERE aq.status = 'pending'
                          AND aq.scheduled_at <= NOW()
                          AND aseq.status = 'active'
                        ORDER BY aq.scheduled_at ASC
                        LIMIT {$batchSize}
                        FOR UPDATE SKIP LOCKED");
    $st->execute();
    $jobs = $st->fetchAll();

    if ($jobs) {
        $ids = implode(',', array_column($jobs, 'queue_id'));
        $db->exec("UPDATE automation_queue SET status='sending' WHERE id IN ({$ids})");
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    die("DB Error: " . $e->getMessage() . "\n");
}

foreach ($jobs as $job) {
    $qid   = (int)$job['queue_id'];
    $subId = (int)$job['subscriber_id'];
    $seqId = (int)$job['sequence_id'];
    $ok    = false;

    $vars = [
        'first_name' => $job['first_name'] ?: 'Friend',
        'last_name'  => $job['last_name'],
        'email'      => $job['email'],
        'app_name'   => getSetting('app_name','Newsletter'),
        'app_url'    => $appUrl,
        'unsubscribe_url' => $appUrl . '/unsubscribe.php?email=' . urlencode($job['email']),
    ];

    if ($job['type'] === 'email' && $job['subject'] && $job['body_html']) {
        $html = $engine->render($job['body_html'], $vars);
        $ok   = $mailer->send($job['email'], $engine->render($job['subject'], $vars), $html);
        echo ($ok ? '✓ Email' : '✗ Email') . ' → ' . $job['email'] . "\n";
    } elseif ($job['type'] === 'tag') {
        // Apply tag
        $tags = json_decode($job['tags'] ?? '[]', true) ?: [];
        if ($job['tag_action'] === 'add' && !in_array($job['tag_value'], $tags)) {
            $tags[] = $job['tag_value'];
        } elseif ($job['tag_action'] === 'remove') {
            $tags = array_filter($tags, fn($t) => $t !== $job['tag_value']);
        }
        $db->prepare("UPDATE subscribers SET tags=? WHERE id=?")->execute([json_encode(array_values($tags)), $subId]);
        $ok = true;
        echo "✓ Tag → {$job['email']}\n";
    } elseif ($job['type'] === 'webhook' && $job['webhook_url']) {
        // Send webhook
        if (function_exists('curl_init')) {
            $ch = curl_init($job['webhook_url']);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['email'=>$job['email'],'subscriber_id'=>$subId,'sequence_id'=>$seqId]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 5,
                CURLOPT_RETURNTRANSFER => true,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
        $ok = true;
        echo "✓ Webhook → {$job['email']}\n";
    } else {
        $ok = true; // wait/unknown — mark done
    }

    $db->prepare("UPDATE automation_queue SET status=?, completed_at=NOW() WHERE id=?")->execute([$ok?'done':'failed',$qid]);

    // Enqueue next step
    if ($ok) {
        $nextStep = $db->prepare("SELECT * FROM automation_steps WHERE sequence_id=? AND step_order > (SELECT step_order FROM automation_steps WHERE id=?) ORDER BY step_order ASC LIMIT 1");
        $nextStep->execute([$seqId, $job['step_id']]);
        $next = $nextStep->fetch();
        if ($next) {
            $delay    = (int)$next['delay_days'] * 86400 + (int)$next['delay_hours'] * 3600;
            $schedAt  = date('Y-m-d H:i:s', time() + $delay);
            $db->prepare("INSERT IGNORE INTO automation_queue (sequence_id,step_id,subscriber_id,scheduled_at,status) VALUES (?,?,?,?,'pending')")
               ->execute([$seqId,$next['id'],$subId,$schedAt]);
        }
    }

    $processed++;
}

echo "\nDone. Processed: {$processed}\n";
