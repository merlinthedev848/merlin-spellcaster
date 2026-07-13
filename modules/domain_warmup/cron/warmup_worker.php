<?php
declare(strict_types=1);

/**
 * modules/domain_warmup/cron/warmup_worker.php
 * Run this via CLI: php warmup_worker.php
 * 
 * Logic:
 * Calculates the current day of warmup and sends a batch of neutral emails to the seed list.
 */

if (php_sapi_name() !== 'cli' && !isset($_GET['secret'])) {
    die("CLI only or requires secret");
}

require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 3) . '/core/Mailer.php';

// In a real implementation, you would have a settings table to store 'warmup_active', 'warmup_start_date', 'warmup_seed_list_id'.
// We mock this logic for the structural implementation.

$active = getSetting('warmup_active', '0') === '1';
if (!$active) {
    echo "Warmup engine is inactive.\n";
    exit;
}

$startDate = getSetting('warmup_start_date', date('Y-m-d'));
$seedListId = (int)getSetting('warmup_seed_list', '0');

if (!$seedListId) {
    echo "No seed list selected.\n";
    exit;
}

$start = new DateTime($startDate);
$today = new DateTime();
$diff = $today->diff($start);
$day = $diff->days + 1;

if ($day > 30) {
    echo "Warmup complete (Day $day > 30).\n";
    setSetting($db, 'warmup_active', '0');
    exit;
}

// Simple exponential growth logic
// Day 1 = 5, Day 2 = 10, Day 3 = 15, Day 4 = 25...
$quota = (int)round(5 * pow(1.3, $day - 1));

echo "Warmup Day $day: Target quota is $quota emails.\n";

$seedSubs = $db->query("SELECT s.id, s.email, s.first_name 
                        FROM subscribers s 
                        JOIN subscriber_lists sl ON s.id = sl.subscriber_id 
                        WHERE sl.list_id = $seedListId AND s.status = 'active'
                        LIMIT $quota")->fetchAll();

if (!$seedSubs) {
    echo "No subscribers found in seed list.\n";
    exit;
}

$mailer = new Mailer();
$sent = 0;

foreach ($seedSubs as $sub) {
    $subject = "Daily Update - " . date('Y-m-d');
    $body = "<p>Hi {$sub['first_name']},</p><p>This is your daily system update. Have a great day.</p>";
    $altText = "Hi {$sub['first_name']},\nThis is your daily system update.";
    
    // Attempt send
    if ($mailer->send($sub['email'], $subject, $body, $altText)) {
        $sent++;
        echo "Sent to {$sub['email']}\n";
    }
    sleep(1); // Polite delay
}

echo "Warmup finished. Sent $sent emails.\n";
