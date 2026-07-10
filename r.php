<?php
/**
 * r.php — Click tracking redirect
 * PHP 8.5+
 */
declare(strict_types=1);
$c   = (int)($_GET['c'] ?? 0);
$s   = (int)($_GET['s'] ?? 0);
$t   = $_GET['t'] ?? '';
$url = trim($_GET['url'] ?? '');

$target = '/';
$trackingOk = false;
$email = '';

if ($c && $s && $t && $url && filter_var($url, FILTER_VALIDATE_URL)) {
    require_once __DIR__ . '/config.php';
    try {
        $sub = $db->prepare("SELECT email FROM subscribers WHERE id=?");
        $sub->execute([$s]);
        $email = (string)$sub->fetchColumn();
        if ($email !== '') {
            $expected = generateToken($email, $c, $s);
            $trackingOk = hash_equals($expected, $t);
            if ($trackingOk) {
                $target = $url;
            }
        }
    } catch (Throwable) {
        $trackingOk = false;
    }
}
header('Location: ' . $target);
header('Cache-Control: no-cache');

// Flush
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    if (ob_get_level() > 0) ob_end_flush();
    flush();
}

if (!$trackingOk) exit;

if (getSetting('tracking_enabled','1') !== '1') exit;

try {
    $existingSt = $db->prepare("SELECT id FROM campaign_clicks WHERE campaign_id=? AND subscriber_id=? AND url=? LIMIT 1");
    $existingSt->execute([$c,$s,$url]);
    $isUnique = !$existingSt->fetchColumn();

    $db->prepare("INSERT INTO campaign_clicks (campaign_id,subscriber_id,url,ip_address,is_unique) VALUES (?,?,?,?,?)")
       ->execute([$c,$s,$url,$_SERVER['REMOTE_ADDR']??'',(int)$isUnique]);

    if ($isUnique) {
        $db->prepare("UPDATE campaigns SET click_count=click_count+1 WHERE id=?")->execute([$c]);
    }
} catch (Throwable) {}
