<?php
/**
 * o.php — Open tracking pixel (1x1 GIF)
 * PHP 8.5+
 */
declare(strict_types=1);
// Serve pixel immediately, track asynchronously
header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
// 1x1 transparent GIF
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

// Flush output so browser gets the image immediately
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    if (ob_get_level() > 0) ob_end_flush();
    flush();
}

// Now do the database work
$c = (int)($_GET['c'] ?? 0); // campaign_id
$s = (int)($_GET['s'] ?? 0); // subscriber_id
$t = $_GET['t'] ?? '';        // token

if (!$c || !$s || !$t) exit;

require_once __DIR__ . '/config.php';

if (getSetting('tracking_enabled','1') !== '1') exit;

try {
    // Verify token
    $sub = $db->prepare("SELECT email FROM subscribers WHERE id=?"); $sub->execute([$s]); $email = $sub->fetchColumn();
    if (!$email) exit;
    $expected = generateToken((string)$email, $c, $s);
    if (!hash_equals($expected, $t)) exit;

    // Check if this is a unique open
    $existingSt = $db->prepare("SELECT id FROM campaign_opens WHERE campaign_id=? AND subscriber_id=? LIMIT 1");
    $existingSt->execute([$c, $s]);
    $isUnique = !$existingSt->fetchColumn();

    // Record open
    $db->prepare("INSERT INTO campaign_opens (campaign_id,subscriber_id,ip_address,user_agent,is_unique) VALUES (?,?,?,?,?)")
       ->execute([$c,$s,$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'',(int)$isUnique]);

    if ($isUnique) {
        $db->prepare("UPDATE campaigns SET open_count=open_count+1 WHERE id=?")->execute([$c]);
    }
} catch (Throwable) {
    // Silently fail — never show errors on tracking pixel
}
