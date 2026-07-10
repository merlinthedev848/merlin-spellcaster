<?php
/**
 * subscribe.php — Public subscription endpoint
 * PHP 8.5+
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

$listId   = (int)($_GET['list'] ?? $_POST['list_id'] ?? 0);
$formId   = (int)($_GET['form'] ?? $_POST['form_id'] ?? 0);
$isAjax   = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || ($_GET['format'] ?? '') === 'json';

// Load form config
$form = null;
if ($formId) {
    $st = $db->prepare("SELECT * FROM forms WHERE id=?"); $st->execute([$formId]); $form = $st->fetch();
    if ($form && !$listId) $listId = (int)($form['list_id'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');

    $respond = static function(bool $ok, string $msg) use ($isAjax, $form): never {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>$ok,'message'=>$msg]);
            exit;
        }
        if ($ok && !empty($form['redirect_url'])) {
            header('Location: '.$form['redirect_url']);
            exit;
        }
        // Redirect back with flash
        session_start();
        $_SESSION['sub_'.($ok?'ok':'err')] = $msg;
        header('Location: '.$_SERVER['HTTP_REFERER']);
        exit;
    };

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $respond(false, 'Please enter a valid email address.');
    }

    try {
        // Upsert subscriber
        $db->prepare("INSERT INTO subscribers (email,first_name,last_name,status,source,ip_address) VALUES (?,?,?,'active','form',?) ON DUPLICATE KEY UPDATE first_name=IF(first_name='',VALUES(first_name),first_name), status=IF(status='unsubscribed','active',status)")->execute([$email,$firstName,$lastName,$_SERVER['REMOTE_ADDR']??'']);
        $stId = $db->prepare("SELECT id FROM subscribers WHERE email=?"); $stId->execute([$email]); $subId = (int)$stId->fetchColumn();

        if ($listId && $subId) {
            $dblOptin = $form['double_optin'] ?? 0;
            $status   = $dblOptin ? 'pending' : 'confirmed';
            $db->prepare("INSERT IGNORE INTO subscriber_lists (subscriber_id,list_id,status) VALUES (?,?,?)")->execute([$subId,$listId,$status]);
            updateListCounts($db);

            // TODO: send confirmation email for double opt-in
        }
        $respond(true, $form['success_message'] ?? 'Thank you for subscribing!');
    } catch (Throwable $e) {
        $respond(false, 'Something went wrong. Please try again.');
    }
}

// GET: render a simple form
$appName = getSetting('app_name','Merlin Spellcaster');
$okMsg   = $_SESSION['sub_ok'] ?? ''; unset($_SESSION['sub_ok']);
$errMsg  = $_SESSION['sub_err'] ?? ''; unset($_SESSION['sub_err']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($form['headline'] ?? 'Subscribe') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{font-family:'Inter',sans-serif;background:#0b0f19;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.box{background:#111827;border:1px solid rgba(148,163,184,0.08);border-radius:16px;padding:32px;max-width:400px;width:100%}
h1{color:#fff;font-size:22px;font-weight:800;margin:0 0 8px;text-align:center}
p{color:#94a3b8;font-size:14px;line-height:1.7;text-align:center;margin:0 0 24px}
input{background:rgba(255,255,255,0.03);border:1px solid rgba(148,163,184,0.12);color:#e2e8f0;border-radius:10px;padding:10px 14px;width:100%;font-size:14px;outline:none;margin-bottom:12px;transition:border-color 0.2s;font-family:inherit}
input:focus{border-color:#6366f1}
input::placeholder{color:#475569}
button{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;font-weight:700;padding:12px;border-radius:10px;border:none;cursor:pointer;width:100%;font-size:14px;box-shadow:0 4px 20px rgba(99,102,241,0.3);transition:all 0.2s}
button:hover{transform:translateY(-1px)}
.ok{color:#34d399;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);border-radius:10px;padding:12px;text-align:center;margin-bottom:12px;font-size:14px}
.err{color:#f87171;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);border-radius:10px;padding:12px;text-align:center;margin-bottom:12px;font-size:14px}
</style>
</head>
<body>
<div class="box">
  <h1><?= e($form['headline'] ?? 'Subscribe to our Newsletter') ?></h1>
  <?php if ($form['description'] ?? ''): ?><p><?= e($form['description']) ?></p><?php endif; ?>
  <?php if ($okMsg): ?><div class="ok"><?= e($okMsg) ?></div><?php endif; ?>
  <?php if ($errMsg): ?><div class="err"><?= e($errMsg) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="list_id" value="<?= $listId ?>">
    <input type="hidden" name="form_id" value="<?= $formId ?>">
    <?php if ($form['show_name'] ?? true): ?>
    <input type="text" name="first_name" placeholder="First Name" <?= ($form['require_name']??0)?'required':'' ?>>
    <?php endif; ?>
    <input type="email" name="email" placeholder="Email Address" required>
    <button type="submit"><?= e($form['button_text'] ?? 'Subscribe') ?></button>
  </form>
</div>
</body>
</html>
