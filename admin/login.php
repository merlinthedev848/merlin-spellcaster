<?php
/**
 * admin/login.php — Admin login page
 * PHP 8.5+
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';

// Check if setup complete
if (!getSetting('setup_complete')) {
    header('Location: /setup/index.php');
    exit;
}

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    $st = $db->prepare("SELECT * FROM users WHERE email=? AND status='active' LIMIT 1");
    $st->execute([$email]);
    $user = $st->fetch();

    if ($user && password_verify($pass, $user['password_hash'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $db->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
        logActivity($db, (int)$user['id'], 'login', 'user', (int)$user['id'], $user['name']);

        $redirect = $_SESSION['redirect_after_login'] ?? '/admin/dashboard.php';
        unset($_SESSION['redirect_after_login']);
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'Invalid email or password.';
        // Rate limit: could track failed attempts but kept simple for shared hosting
    }
}

$appName = getSetting('app_name', 'Merlin Spellcaster');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= e($appName) ?></title>
<meta name="robots" content="noindex">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:#0B0F19;font-family:'Inter',sans-serif;overflow:hidden}
body{display:flex;align-items:center;justify-content:center}

/* Animated background */
.bg-shapes{position:fixed;inset:0;overflow:hidden;pointer-events:none;z-index:0}
.shape{position:absolute;border-radius:50%;filter:blur(80px);opacity:.12;animation:float 8s ease-in-out infinite}
.shape:nth-child(1){width:400px;height:400px;background:#6366f1;top:-100px;left:-100px;animation-delay:0s}
.shape:nth-child(2){width:300px;height:300px;background:#8b5cf6;bottom:-80px;right:-80px;animation-delay:-3s}
.shape:nth-child(3){width:200px;height:200px;background:#22d3ee;top:50%;left:60%;animation-delay:-5s}
@keyframes float{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-30px) scale(1.05)}}

.card{position:relative;z-index:1;width:100%;max-width:400px;padding:40px;background:rgba(17,24,39,0.85);backdrop-filter:blur(20px);border:1px solid rgba(148,163,184,0.1);border-radius:24px;box-shadow:0 25px 60px rgba(0,0,0,0.5)}

.logo{display:flex;align-items:center;justify-content:center;flex-direction:column;gap:12px;margin-bottom:32px}
.logo-icon{width:60px;height:60px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:28px;box-shadow:0 8px 24px rgba(99,102,241,0.4)}
.logo-text{font-size:20px;font-weight:800;color:#fff}
.logo-sub{font-size:13px;color:#64748b;font-weight:500}

h1{text-align:center;font-size:22px;font-weight:800;color:#fff;margin-bottom:6px}
.sub{text-align:center;font-size:13px;color:#64748b;margin-bottom:28px}

.form-group{margin-bottom:16px}
label{display:block;font-size:12px;font-weight:600;color:#94a3b8;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em}
input{width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(148,163,184,0.12);color:#e2e8f0;border-radius:12px;padding:12px 16px;font-size:14px;outline:none;transition:border-color .2s,box-shadow .2s;font-family:inherit}
input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,0.15)}
input::placeholder{color:#475569}

.btn{width:100%;padding:13px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;font-weight:700;font-size:15px;border:none;border-radius:12px;cursor:pointer;transition:all .2s;box-shadow:0 4px 20px rgba(99,102,241,0.35)}
.btn:hover{transform:translateY(-1px);box-shadow:0 8px 28px rgba(99,102,241,0.5)}
.btn:active{transform:translateY(0)}

.error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#f87171;border-radius:10px;padding:12px;font-size:13px;text-align:center;margin-bottom:16px}
.footer-links{text-align:center;margin-top:20px;font-size:12px;color:#475569}

/* Password toggle */
.pw-wrap{position:relative}
.pw-wrap input{padding-right:44px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#64748b;padding:4px;font-size:18px}
.pw-toggle:hover{color:#94a3b8}
</style>
</head>
<body>
<div class="bg-shapes">
  <div class="shape"></div>
  <div class="shape"></div>
  <div class="shape"></div>
</div>

<div class="card">
  <div class="logo">
    <div class="logo-icon">🧙</div>
    <div>
      <div class="logo-text"><?= e($appName) ?></div>
      <div class="logo-sub">Email Marketing Platform</div>
    </div>
  </div>

  <h1>Welcome back</h1>
  <p class="sub">Sign in to your admin panel</p>

  <?php if ($error): ?>
  <div class="error">⚠️ <?= e($error) ?></div>
  <?php endif; ?>

  <?php
  // Show flash from session
  $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
  if ($flash): ?>
  <div class="error" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.2);color:#34d399">✅ <?= e($flash['message']) ?></div>
  <?php endif; ?>

  <form method="post" autocomplete="on">
    <div class="form-group">
      <label for="email">Email Address</label>
      <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="admin@example.com" required autofocus autocomplete="email">
    </div>
    <div class="form-group">
      <label for="password">Password</label>
      <div class="pw-wrap">
        <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
        <button type="button" class="pw-toggle" onclick="togglePw()" title="Show/hide password">👁</button>
      </div>
    </div>
    <button type="submit" class="btn">Sign In →</button>
  </form>

  <div class="footer-links">
    Forgot password? <a href="/setup/index.php" style="color:#6366f1">Re-run setup</a>
  </div>
</div>

<script>
function togglePw() {
  const inp = document.getElementById('password');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}
// Shake on error
<?php if ($error): ?>
document.querySelector('.card').style.animation='shake .5s ease';
const style = document.createElement('style');
style.textContent='@keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-8px)}75%{transform:translateX(8px)}}';
document.head.appendChild(style);
<?php endif; ?>
</script>
</body>
</html>
