<?php
/**
 * cron/send.php — Email send queue processor
 * PHP 8.5+ — web-triggered, safe for shared hosting
 *
 * Usage: curl "https://yourdomain.com/cron/send.php?secret=YOUR_SECRET"
 *   Or: Add as Scheduled Task in Enhance control panel (run every minute)
 */
declare(strict_types=1);
ignore_user_abort(true);
set_time_limit(120);

// Must be run via HTTP (not CLI) or with correct secret
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

$batchSize = max(1, (int)getSetting('cron_batch_size', '50'));
$appUrl    = getSetting('app_url', 'http://localhost');
$fromName  = getSetting('smtp_from_name', 'Newsletter');
$fromEmail = getSetting('smtp_from_email', 'noreply@localhost');

// Lock: grab pending emails
try {
    $db->beginTransaction();
    $st = $db->prepare("SELECT eq.id as queue_id, eq.campaign_id, eq.subscriber_id,
                               c.subject, c.body_html, c.body_text, c.from_name, c.from_email, c.reply_to,
                               s.email, s.first_name, s.last_name
                        FROM email_queue eq
                        JOIN campaigns c ON c.id = eq.campaign_id
                        JOIN subscribers s ON s.id = eq.subscriber_id
                        WHERE eq.status = 'pending' AND eq.send_at <= NOW()
                        ORDER BY eq.send_at ASC
                        LIMIT {$batchSize}
                        FOR UPDATE SKIP LOCKED");
    $st->execute();
    $jobs = $st->fetchAll();

    if ($jobs) {
        $ids = array_column($jobs, 'queue_id');
        $in  = implode(',', $ids);
        $db->exec("UPDATE email_queue SET status='sending', attempts=attempts+1 WHERE id IN ({$in})");
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    die("DB Error: " . $e->getMessage() . "\n");
}

if (empty($jobs)) {
    echo "No jobs in queue.\n";
    exit;
}

$mailer   = new Mailer();
$engine   = new TemplateEngine();
$sent     = 0;
$failed   = 0;

foreach ($jobs as $job) {
    $subId   = (int)$job['subscriber_id'];
    $camId   = (int)$job['campaign_id'];
    $queueId = (int)$job['queue_id'];

    $token = generateToken($job['email'], $camId, $subId);
    $vars  = [
        'first_name'      => $job['first_name'] ?: 'Friend',
        'last_name'       => $job['last_name'],
        'email'           => $job['email'],
        'unsubscribe_url' => $appUrl . '/unsubscribe.php?c=' . $camId . '&s=' . $subId . '&t=' . $token,
        'app_name'        => getSetting('app_name', 'Newsletter'),
        'app_url'         => $appUrl,
    ];

    // Process HTML: add tracking pixel, wrap links
    $bodyHtml = $engine->render($job['body_html'], $vars);
    $bodyText = $job['body_text'] ? $engine->render($job['body_text'], $vars) : null;

    // Wrap links for click tracking
    $bodyHtml = preg_replace_callback('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', function ($m) use ($appUrl, $camId, $subId, $token) {
        $origUrl = $m[1];
        if (str_starts_with($origUrl, $appUrl . '/r.php') || str_starts_with($origUrl, $appUrl . '/unsubscribe')) {
            return $m[0];
        }
        $trackUrl = $appUrl . '/r.php?c=' . $camId . '&s=' . $subId . '&t=' . $token . '&url=' . urlencode($origUrl);
        return str_replace($origUrl, $trackUrl, $m[0]);
    }, $bodyHtml) ?? $bodyHtml;

    // Add open tracking pixel
    $pixelUrl = $appUrl . '/o.php?c=' . $camId . '&s=' . $subId . '&t=' . $token;
    if (!str_contains($bodyHtml, '</body>')) {
        $bodyHtml .= '<img src="' . $pixelUrl . '" width="1" height="1" border="0" style="display:none" alt="">';
    } else {
        $bodyHtml = str_replace('</body>', '<img src="' . $pixelUrl . '" width="1" height="1" border="0" style="display:none" alt=""></body>', $bodyHtml);
    }

    $emailFrom = $job['from_email'] ?: $fromEmail;
    $nameFrom  = $job['from_name']  ?: $fromName;

    $ok = $mailer->send($job['email'], $job['subject'], $bodyHtml, $bodyText, $nameFrom, $emailFrom, $job['reply_to'] ?: null);

    if ($ok) {
        $db->prepare("UPDATE email_queue SET status='sent', sent_at=NOW() WHERE id=?")->execute([$queueId]);
        $sent++;
    } else {
        $db->prepare("UPDATE email_queue SET status='failed', error_message=? WHERE id=?")->execute([$mailer->getLastError(), $queueId]);
        $failed++;
    }

    echo ($ok ? '✓' : '✗') . ' ' . $job['email'] . "\n";
}

// Mark campaign as sent if queue is empty
$stCheck = $db->prepare("SELECT campaign_id, COUNT(*) as remaining FROM email_queue WHERE status='pending' AND campaign_id IN (SELECT DISTINCT campaign_id FROM email_queue WHERE status='sent') GROUP BY campaign_id HAVING remaining = 0");
// Simpler: check each campaign in this batch
$campaignIds = array_unique(array_column($jobs, 'campaign_id'));
foreach ($campaignIds as $cid) {
    $remaining = (int)$db->prepare("SELECT COUNT(*) FROM email_queue WHERE campaign_id=? AND status='pending'")->execute([$cid]) ? (function() use ($db,$cid) {
        $s=$db->prepare("SELECT COUNT(*) FROM email_queue WHERE campaign_id=? AND status='pending'"); $s->execute([$cid]); return (int)$s->fetchColumn();
    })() : 0;
    if ($remaining === 0) {
        $db->prepare("UPDATE campaigns SET status='sent', sent_at=NOW() WHERE id=? AND status IN ('sending','paused')")->execute([$cid]);
    }
}

echo "\nDone. Sent: {$sent}, Failed: {$failed}\n";