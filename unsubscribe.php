<?php
/**
 * unsubscribe.php — One-click unsubscribe
 * PHP 8.5+
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

$token  = trim($_GET['t'] ?? '');
$camId  = (int)($_GET['c'] ?? 0);
$subId  = (int)($_GET['s'] ?? 0);
$valid  = false;
$error  = '';
$appName= getSetting('app_name','Merlin Spellcaster');
$msg    = getSetting('unsubscribe_message','You have been successfully unsubscribed.');

if ($token && $camId && $subId) {
    // Verify HMAC token
    $sub = $db->prepare("SELECT * FROM subscribers WHERE id=?"); $sub->execute([$subId]); $subscriber = $sub->fetch();
    if ($subscriber) {
        $expected = generateToken($subscriber['email'], $camId, $subId);
        if (hash_equals($expected, $token)) {
            $valid = true;
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $db->prepare("UPDATE subscribers SET status='unsubscribed', updated_at=NOW() WHERE id=?")->execute([$subId]);
                $db->prepare("UPDATE campaigns SET unsub_count=unsub_count+1 WHERE id=?")->execute([$camId]);
                $db->prepare("UPDATE subscriber_lists SET status='unsubscribed' WHERE subscriber_id=?")->execute([$subId]);
                logActivity($db, null, 'unsubscribed', 'subscriber', $subId, "Via campaign #{$camId}");
            }
        } else {
            $error = 'Invalid or expired unsubscribe link.';
        }
    } else {
        $error = 'Subscriber not found.';
    }
} else {
    $error = 'Invalid unsubscribe link. Please use the secure unsubscribe link from your email.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Unsubscribe — <?= e($appName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{font-family:'Inter',sans-serif;background:#0b0f19;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.box{background:#111827;border:1px solid rgba(148,163,184,0.08);border-radius:16px;padding:40px 32px;max-width:420px;width:100%;text-align:center}
.icon{font-size:48px;margin-bottom:16px}
h1{color:#fff;font-size:20px;font-weight:800;margin:0 0 12px}
p{color:#94a3b8;font-size:14px;line-height:1.7;margin:0 0 24px}
.btn{display:inline-block;background:#ef4444;color:#fff;font-weight:700;padding:12px 28px;border-radius:10px;border:none;cursor:pointer;font-size:14px;text-decoration:none;transition:all 0.2s}
.btn:hover{background:#dc2626}
.btn-soft{display:inline-block;background:rgba(255,255,255,0.05);color:#94a3b8;font-weight:600;padding:12px 28px;border-radius:10px;border:1px solid rgba(148,163,184,0.1);cursor:pointer;font-size:14px;text-decoration:none;transition:all 0.2s}
.btn-soft:hover{color:#e2e8f0;background:rgba(255,255,255,0.08)}
.success{color:#34d399}
.error{color:#f87171}
</style>
</head>
<body>
<div class="box">
  <?php if ($error): ?>
  <div class="icon">⚠️</div>
  <h1 class="error">Invalid Link</h1>
  <p><?= e($error) ?></p>
  <?php elseif ($valid && $_SERVER['REQUEST_METHOD']==='POST'): ?>
  <div class="icon">✅</div>
  <h1 class="success">Unsubscribed</h1>
  <p><?= e($msg) ?></p>
  <p style="font-size:13px;color:#475569">You will no longer receive marketing emails from us.</p>
  <?php elseif ($valid): ?>
  <div class="icon">📭</div>
  <h1>Unsubscribe?</h1>
  <p>Are you sure you want to unsubscribe? You will stop receiving emails from <strong><?= e($appName) ?></strong>.</p>
  <div style="display:flex;gap:12px;justify-content:center">
    <form method="post" style="display:inline">
      <button type="submit" class="btn">Yes, Unsubscribe</button>
    </form>
    <a href="javascript:history.back()" class="btn-soft">Go Back</a>
  </div>
  <?php else: ?>
  <div class="icon">❓</div>
  <h1>Unsubscribe</h1>
  <p>Enter your email address to unsubscribe from all marketing emails.</p>
  <form method="get" style="text-align:left">
    <input type="email" name="email" placeholder="your@email.com" required style="background:rgba(255,255,255,0.03);border:1px solid rgba(148,163,184,0.12);color:#e2e8f0;border-radius:10px;padding:10px 14px;width:100%;font-size:14px;outline:none;margin-bottom:12px">
    <button type="submit" class="btn" style="width:100%">Unsubscribe</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
