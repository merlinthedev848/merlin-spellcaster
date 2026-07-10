<?php
/**
 * login.php — Merlin Spellcaster admin login
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Auth.php';

// Already logged in
if (Auth::isLoggedIn()) {
    header('Location: /admin/dashboard.php');
    exit();
}

// Setup not complete
if ($db === null || !isSetupComplete()) {
    header('Location: /setup/');
    exit();
}

$error    = '';
$redirect = $_GET['redirect'] ?? '/admin/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } elseif (Auth::login($email, $password, $db)) {
        logActivity($db, currentUserId(), 'login', 'user', currentUserId(), 'Successful login from ' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $safe = filter_var($redirect, FILTER_VALIDATE_URL) ? $redirect : '/admin/dashboard.php';
        header('Location: ' . $safe);
        exit();
    } else {
        $error = 'Invalid email or password. Please try again.';
        logActivity($db, null, 'login_failed', 'user', null, 'Failed login attempt for: ' . $email);
    }
}

$appName = getSetting('app_name', 'Merlin Spellcaster');
$loggedOut = ($_GET['msg'] ?? '') === 'logged_out';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — <?= e($appName) ?></title>
<meta name="robots" content="noindex,nofollow">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Inter', sans-serif; }
  @keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }
  @keyframes fadeUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
  .float { animation: float 6s ease-in-out infinite; }
  .fade-up { animation: fadeUp 0.5s ease forwards; }
  .orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.15; }
  .form-input {
    background: rgba(15,23,42,0.8); border: 1px solid rgba(148,163,184,0.12);
    border-radius: 10px; padding: 12px 16px; color: #e2e8f0; font-size: 14.5px;
    width: 100%; transition: all 0.2s; font-family: inherit;
  }
  .form-input:focus { outline: none; border-color: rgba(99,102,241,0.5); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
  .form-input::placeholder { color: #475569; }
</style>
</head>
<body class="bg-[#0B0F19] min-h-screen flex items-center justify-center relative overflow-hidden" x-data="{ showPass: false }">

<!-- Ambient orbs -->
<div class="orb w-96 h-96 bg-indigo-600 top-[-100px] left-[-100px]"></div>
<div class="orb w-80 h-80 bg-violet-600 bottom-[-80px] right-[-80px]"></div>
<div class="orb w-64 h-64 bg-cyan-500 top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2"></div>

<!-- Floating decoration -->
<div class="absolute top-20 right-20 float opacity-20">
  <svg width="80" height="80" viewBox="0 0 80 80" fill="none">
    <circle cx="40" cy="40" r="38" stroke="url(#g1)" stroke-width="2"/>
    <path d="M40 20 L55 35 L40 30 L25 35 Z" fill="url(#g1)" opacity="0.6"/>
    <defs><linearGradient id="g1" x1="0" y1="0" x2="80" y2="80"><stop stop-color="#6366f1"/><stop offset="1" stop-color="#8b5cf6"/></linearGradient></defs>
  </svg>
</div>

<!-- Login Card -->
<div class="relative z-10 w-full max-w-md px-6 fade-up">

  <!-- Logo -->
  <div class="text-center mb-8">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 shadow-2xl shadow-indigo-500/40 mb-4">
      <span class="text-white font-black text-3xl">M</span>
    </div>
    <h1 class="text-2xl font-bold text-white"><?= e($appName) ?></h1>
    <p class="text-slate-500 text-sm mt-1">Email Marketing Platform</p>
  </div>

  <!-- Card -->
  <div class="bg-[#111827] border border-slate-800/60 rounded-2xl p-8 shadow-2xl shadow-black/40"
       style="backdrop-filter:blur(20px)">

    <?php if ($loggedOut): ?>
    <div class="mb-6 flex items-center gap-3 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      You've been signed out successfully.
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="mb-6 flex items-center gap-3 px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <?= e($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="/login.php" class="space-y-5">
      <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Email Address</label>
        <input type="email" name="email" class="form-input" placeholder="admin@example.com"
               value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email">
      </div>

      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Password</label>
        </div>
        <div class="relative">
          <input :type="showPass ? 'text' : 'password'" name="password" class="form-input pr-12"
                 placeholder="••••••••••" required autocomplete="current-password">
          <button type="button" @click="showPass = !showPass"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors">
            <svg x-show="!showPass" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            <svg x-show="showPass" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit"
              class="w-full py-3 px-6 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-semibold rounded-xl transition-all shadow-lg shadow-indigo-500/30 hover:shadow-indigo-500/50 flex items-center justify-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
        Sign In to Dashboard
      </button>
    </form>
  </div>

  <p class="text-center text-slate-600 text-xs mt-6">
    Merlin Spellcaster v<?= APP_VERSION ?> &bull; <a href="/setup/" class="text-indigo-500 hover:text-indigo-400">Setup Wizard</a>
  </p>
</div>

</body>
</html>
