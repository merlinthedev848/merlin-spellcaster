<?php
/**
 * setup/index.php — Merlin Spellcaster Setup Wizard
 * Standalone: no includes/header.php. PHP 8.5+ compatible.
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

// ── Requirements check ──────────────────────────────────────────────────────
$requirements = [
    ['label' => 'PHP 8.0+',                'pass' => PHP_MAJOR_VERSION >= 8,               'required' => true],
    ['label' => 'PDO extension',           'pass' => extension_loaded('pdo'),               'required' => true],
    ['label' => 'PDO MySQL driver',        'pass' => extension_loaded('pdo_mysql'),         'required' => true],
    ['label' => 'OpenSSL extension',       'pass' => extension_loaded('openssl'),           'required' => true],
    ['label' => 'JSON extension',          'pass' => extension_loaded('json'),              'required' => true],
    ['label' => 'File uploads enabled',    'pass' => (bool)ini_get('file_uploads'),        'required' => true],
    ['label' => 'uploads/ dir writable',   'pass' => is_writable(dirname(__DIR__).'/uploads'), 'required' => true],
    ['label' => 'cURL extension',          'pass' => extension_loaded('curl'),              'required' => false],
    ['label' => 'Mbstring extension',      'pass' => extension_loaded('mbstring'),          'required' => false],
];
$canProceed = !in_array(false, array_column(array_filter($requirements, fn($r) => $r['required']), 'pass'), true);

// ── Step handlers ────────────────────────────────────────────────────────────
$stepError = '';
$stepSuccess = '';
$currentStep = (int)($_SESSION['setup_step'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_db') {
        require_once __DIR__ . '/test_db.php';
        $result = testDbConnection($_POST['db_host'] ?? 'localhost', (int)($_POST['db_port'] ?? 3306), $_POST['db_name'] ?? '', $_POST['db_user'] ?? '', $_POST['db_pass'] ?? '');
        if ($result['success']) {
            writeConfigLocal($_POST['db_host'], (int)$_POST['db_port'], $_POST['db_name'], $_POST['db_user'], $_POST['db_pass']);
            $_SESSION['setup_db']   = true;
            $_SESSION['setup_step'] = 3;
            sc_wiz_redirect('?step=3');
        } else {
            $stepError = $result['message'];
        }
    }

    if ($action === 'save_smtp') {
        $smtpData = [
            'smtp_host'       => $_POST['smtp_host'] ?? '',
            'smtp_port'       => $_POST['smtp_port'] ?? '587',
            'smtp_encryption' => $_POST['smtp_enc']  ?? 'tls',
            'smtp_user'       => $_POST['smtp_user'] ?? '',
            'smtp_pass'       => $_POST['smtp_pass'] ?? '',
            'smtp_from_name'  => $_POST['smtp_from_name']  ?? '',
            'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
        ];
        $_SESSION['setup_smtp']   = $smtpData;
        $_SESSION['setup_step']   = 4;
        sc_wiz_redirect('?step=4');
    }

    if ($action === 'skip_smtp') {
        $_SESSION['setup_smtp']   = [];
        $_SESSION['setup_step']   = 4;
        sc_wiz_redirect('?step=4');
    }

    if ($action === 'create_admin') {
        $name    = trim($_POST['admin_name']  ?? '');
        $email   = trim($_POST['admin_email'] ?? '');
        $pass    = $_POST['admin_pass']    ?? '';
        $confirm = $_POST['admin_confirm'] ?? '';
        if (!$name || !$email || !$pass) { $stepError = 'All fields required.'; }
        elseif ($pass !== $confirm) { $stepError = 'Passwords do not match.'; }
        elseif (strlen($pass) < 8) { $stepError = 'Password must be at least 8 characters.'; }
        else {
            require_once dirname(__DIR__) . '/config.php';
            require_once dirname(__DIR__) . '/core/Auth.php';
            if ($db !== null) {
                Auth::createUser($db, $name, $email, $pass, 'admin');
                // Save SMTP settings to DB
                if (!empty($_SESSION['setup_smtp'])) {
                    foreach ($_SESSION['setup_smtp'] as $k => $v) {
                        setSetting($db, $k, $v);
                    }
                }
                $_SESSION['setup_admin'] = ['name' => $name, 'email' => $email];
                $_SESSION['setup_step']  = 5;
                sc_wiz_redirect('?step=5');
            } else {
                $stepError = 'Database connection lost. Please restart setup.';
            }
        }
    }

    if ($action === 'create_list') {
        $listName = trim($_POST['list_name'] ?? '');
        $listDesc = trim($_POST['list_desc'] ?? '');
        $listType = $_POST['list_type'] ?? 'public';
        $dblOptin = isset($_POST['double_optin']) ? 1 : 0;
        if ($listName) {
            require_once dirname(__DIR__) . '/config.php';
            if ($db !== null) {
                $st = $db->prepare("INSERT INTO lists (name, description, type, optin_confirm) VALUES (?, ?, ?, ?)");
                $st->execute([$listName, $listDesc, $listType, $dblOptin]);
                $_SESSION['setup_list'] = ['name' => $listName];
            }
        }
        $_SESSION['setup_step'] = 6;
        sc_wiz_redirect('?step=6');
    }

    if ($action === 'skip_list') {
        $_SESSION['setup_step'] = 6;
        sc_wiz_redirect('?step=6');
    }

    if ($action === 'finish') {
        require_once dirname(__DIR__) . '/config.php';
        if ($db !== null) setSetting($db, 'setup_complete', '1');
        // Log in admin
        $user = $db?->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        if ($user) {
            $adminEmail = $_SESSION['setup_admin']['email'] ?? '';
            $user->execute([$adminEmail]);
            $u = $user->fetch();
            if ($u) {
                $_SESSION['user_id']   = $u['id'];
                $_SESSION['user_name'] = $u['name'];
                $_SESSION['user_role'] = $u['role'];
            }
        }
        sc_wiz_redirect('/admin/dashboard.php');
    }
}

$requestedStep = (int)($_GET['step'] ?? $currentStep);
if ($requestedStep <= $currentStep) $currentStep = $requestedStep;

function sc_wiz_redirect(string $url): never
{
    header("Location: $url");
    exit;
}

function writeConfigLocal(string $host, int $port, string $name, string $user, string $pass): bool
{
    $content  = "<?php\n";
    $content .= "\$db_host = " . var_export($host, true) . ";\n";
    $content .= "\$db_port = " . $port . ";\n";
    $content .= "\$db_name = " . var_export($name, true) . ";\n";
    $content .= "\$db_user = " . var_export($user, true) . ";\n";
    $content .= "\$db_pass = " . var_export($pass, true) . ";\n";
    return file_put_contents(dirname(__DIR__) . '/config.local.php', $content) !== false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup Wizard — Merlin Spellcaster</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  body { font-family: 'Inter', sans-serif; background: #0B0F19; min-height: 100vh; overflow-x: hidden; }

  /* Floating shapes */
  .shape { position: fixed; border-radius: 50%; pointer-events: none; z-index: 0; }
  .shape-1 { width: 500px; height: 500px; background: radial-gradient(circle, rgba(99,102,241,0.12) 0%, transparent 70%); top: -150px; right: -100px; animation: float1 8s ease-in-out infinite; }
  .shape-2 { width: 350px; height: 350px; background: radial-gradient(circle, rgba(167,139,250,0.08) 0%, transparent 70%); bottom: -100px; left: -80px; animation: float2 10s ease-in-out infinite; }
  .shape-3 { width: 200px; height: 200px; background: radial-gradient(circle, rgba(34,211,238,0.06) 0%, transparent 70%); top: 40%; left: 40%; animation: float3 12s ease-in-out infinite; }

  @keyframes float1 { 0%,100%{transform:translate(0,0) rotate(0deg)} 50%{transform:translate(-20px,30px) rotate(10deg)} }
  @keyframes float2 { 0%,100%{transform:translate(0,0)} 50%{transform:translate(20px,-20px)} }
  @keyframes float3 { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(-10px,10px) scale(1.1)} }

  /* Progress bar */
  .progress-bar { transition: width 0.6s cubic-bezier(0.4,0,0.2,1); }

  /* Step transition */
  [x-cloak] { display: none !important; }

  .step-panel { animation: fadeSlide 0.4s ease; }
  @keyframes fadeSlide { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

  /* Sidebar indicators */
  .step-dot-done { background: linear-gradient(135deg,#6366f1,#8b5cf6); box-shadow: 0 0 12px rgba(99,102,241,0.5); }
  .step-dot-current { background: #111827; border: 2px solid #6366f1; box-shadow: 0 0 0 4px rgba(99,102,241,0.15); animation: pulse-ring 2s ease-in-out infinite; }
  .step-dot-future { background: #1e293b; border: 2px solid #334155; }
  @keyframes pulse-ring { 0%,100%{box-shadow:0 0 0 4px rgba(99,102,241,0.15)} 50%{box-shadow:0 0 0 8px rgba(99,102,241,0.05)} }

  /* Password strength */
  .strength-bar { height: 4px; border-radius: 2px; flex: 1; background: #1e293b; transition: background 0.3s; }

  /* Input focus glow */
  .form-input {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(148,163,184,0.12);
    color: #e2e8f0;
    border-radius: 10px;
    padding: 10px 14px;
    width: 100%;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
  }
  .form-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
  .form-input::placeholder { color: #475569; }

  /* Buttons */
  .btn-magic {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white; font-weight: 700; padding: 12px 28px;
    border-radius: 10px; border: none; cursor: pointer;
    font-size: 14px; letter-spacing: 0.01em;
    box-shadow: 0 4px 20px rgba(99,102,241,0.35);
    transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px;
  }
  .btn-magic:hover { transform: translateY(-1px); box-shadow: 0 8px 28px rgba(99,102,241,0.5); }
  .btn-magic:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }
  .btn-secondary-wiz {
    background: rgba(255,255,255,0.04); color: #94a3b8; font-weight: 500;
    padding: 12px 24px; border-radius: 10px; border: 1px solid rgba(148,163,184,0.1);
    cursor: pointer; font-size: 14px; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
  }
  .btn-secondary-wiz:hover { background: rgba(255,255,255,0.07); color: #e2e8f0; }

  /* M Logo */
  .m-logo {
    width: 80px; height: 80px; background: linear-gradient(135deg,#6366f1,#8b5cf6,#ec4899);
    border-radius: 24px; display: flex; align-items: center; justify-content: center;
    font-size: 40px; font-weight: 900; color: white;
    box-shadow: 0 0 40px rgba(99,102,241,0.5), 0 0 80px rgba(99,102,241,0.2);
    animation: logoGlow 3s ease-in-out infinite;
    margin: 0 auto;
  }
  @keyframes logoGlow { 0%,100%{box-shadow:0 0 40px rgba(99,102,241,0.5),0 0 80px rgba(99,102,241,0.2)} 50%{box-shadow:0 0 60px rgba(139,92,246,0.7),0 0 100px rgba(99,102,241,0.3)} }

  /* Confetti */
  .confetti-piece { position: fixed; width: 10px; height: 10px; top: -10px; animation: confettiFall linear forwards; pointer-events: none; }
  @keyframes confettiFall { to{transform:translateY(110vh) rotate(720deg); opacity:0;} }

  /* SMTP preset buttons */
  .preset-btn {
    background: rgba(255,255,255,0.04); border: 1px solid rgba(148,163,184,0.1);
    color: #94a3b8; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600;
    cursor: pointer; transition: all 0.15s;
  }
  .preset-btn:hover { background: rgba(99,102,241,0.15); border-color: rgba(99,102,241,0.3); color: #a5b4fc; }
</style>
</head>
<body x-data="wizard()" x-init="init()">

<!-- Background shapes -->
<div class="shape shape-1"></div>
<div class="shape shape-2"></div>
<div class="shape shape-3"></div>

<!-- Confetti container -->
<div id="confetti-container"></div>

<div class="relative z-10 min-h-screen flex">

  <!-- ── Sidebar ── -->
  <aside class="hidden lg:flex flex-col w-72 fixed top-0 left-0 bottom-0 z-20" style="background: linear-gradient(160deg, #1a1440 0%, #0f0a2e 50%, #12082a 100%); border-right: 1px solid rgba(99,102,241,0.15);">
    <!-- Logo -->
    <div class="p-6 border-b" style="border-color: rgba(99,102,241,0.1);">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-lg text-white" style="background: linear-gradient(135deg,#6366f1,#8b5cf6);">M</div>
        <div>
          <div class="text-white font-bold text-sm">Merlin Spellcaster</div>
          <div class="text-xs" style="color: #7c6fcd;">Setup Wizard</div>
        </div>
      </div>
    </div>

    <!-- Steps list -->
    <nav class="flex-1 p-6 space-y-1">
      <?php
      $steps = [
        [1, '✦', 'Requirements', 'Check your system'],
        [2, '⚙', 'Database',     'Connect your DB'],
        [3, '📬', 'Email / SMTP', 'Configure sending'],
        [4, '👤', 'Admin Account','Create your login'],
        [5, '📋', 'First List',   'Set up a list'],
        [6, '🚀', 'Launch!',      'You\'re ready'],
      ];
      foreach ($steps as [$n, $icon, $label, $sub]) {
        $done    = $currentStep > $n;
        $active  = $currentStep === $n;
        $future  = $currentStep < $n;
        $opacity = $future ? 'opacity-40' : '';
      ?>
      <div class="flex items-center gap-3 px-2 py-2 rounded-xl transition-all <?= $opacity ?> <?= $active ? 'bg-white/5' : '' ?>">
        <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold transition-all
          <?= $done ? 'step-dot-done text-white' : ($active ? 'step-dot-current text-indigo-400' : 'step-dot-future text-slate-500') ?>">
          <?= $done ? '✓' : $n ?>
        </div>
        <div>
          <div class="text-sm font-semibold <?= $active ? 'text-white' : ($done ? 'text-indigo-300' : 'text-slate-400') ?>"><?= $label ?></div>
          <div class="text-xs text-slate-500"><?= $sub ?></div>
        </div>
      </div>
      <?php } ?>
    </nav>

    <!-- Progress -->
    <div class="p-6 border-t" style="border-color: rgba(99,102,241,0.1);">
      <div class="flex justify-between text-xs text-slate-500 mb-2">
        <span>Progress</span>
        <span><?= round(($currentStep - 1) / 5 * 100) ?>%</span>
      </div>
      <div class="h-1.5 bg-slate-800 rounded-full overflow-hidden">
        <div class="h-full rounded-full progress-bar" style="width:<?= round(($currentStep - 1) / 5 * 100) ?>%; background: linear-gradient(90deg,#6366f1,#8b5cf6);"></div>
      </div>
    </div>
  </aside>

  <!-- ── Main Content ── -->
  <main class="flex-1 lg:ml-72 p-4 lg:p-10 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-xl">

      <?php if ($stepError): ?>
      <div class="mb-4 p-4 rounded-xl text-sm font-medium" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);color:#f87171;">
        ⚠️ <?= htmlspecialchars($stepError, ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endif; ?>

      <!-- ─── STEP 1: Welcome & Requirements ─── -->
      <?php if ($currentStep === 1): ?>
      <div class="step-panel">
        <div class="text-center mb-8">
          <div class="m-logo mb-6">M</div>
          <h1 class="text-3xl font-black text-white mb-2">Welcome to <span style="background:linear-gradient(135deg,#6366f1,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Merlin Spellcaster</span></h1>
          <p class="text-slate-400 text-sm">The email marketing platform that casts spells on your audience</p>
        </div>

        <div class="rounded-2xl p-6 mb-6 space-y-3" style="background:#111827;border:1px solid rgba(148,163,184,0.08);">
          <h2 class="text-sm font-bold text-slate-300 mb-4 uppercase tracking-widest">System Requirements</h2>
          <?php foreach ($requirements as $req): ?>
          <div class="flex items-center justify-between py-2 border-b border-white/5 last:border-0">
            <div class="flex items-center gap-3">
              <span class="text-base"><?= $req['pass'] ? '✅' : ($req['required'] ? '❌' : '⚠️') ?></span>
              <span class="text-sm <?= $req['pass'] ? 'text-slate-200' : ($req['required'] ? 'text-red-400' : 'text-amber-400') ?>"><?= htmlspecialchars($req['label']) ?></span>
              <?php if (!$req['required']): ?><span class="text-xs text-slate-600 ml-1">(optional)</span><?php endif; ?>
            </div>
            <span class="text-xs font-bold <?= $req['pass'] ? 'text-emerald-400' : ($req['required'] ? 'text-red-400' : 'text-amber-400') ?>"><?= $req['pass'] ? 'OK' : 'MISSING' ?></span>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="flex gap-3">
          <?php if ($canProceed): ?>
          <a href="?step=2" class="btn-magic flex-1 justify-center">
            Begin Setup <span>→</span>
          </a>
          <?php else: ?>
          <button disabled class="btn-magic flex-1 justify-center">Fix requirements to continue</button>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- ─── STEP 2: Database ─── -->
      <?php if ($currentStep === 2): ?>
      <div class="step-panel" x-data="{dbTested:false,testing:false,dbResult:'',dbOk:false}">
        <div class="mb-8">
          <div class="text-xs font-bold text-indigo-400 uppercase tracking-widest mb-2">Step 2 of 6</div>
          <h1 class="text-2xl font-black text-white mb-1">Database Configuration</h1>
          <p class="text-slate-400 text-sm">Connect Merlin to your MySQL / MariaDB database</p>
        </div>

        <div class="rounded-2xl p-6" style="background:#111827;border:1px solid rgba(148,163,184,0.08);">
          <form method="post" id="dbForm">
            <input type="hidden" name="action" value="save_db">
            <div class="grid grid-cols-3 gap-3 mb-4">
              <div class="col-span-2">
                <label class="block text-xs font-semibold text-slate-400 mb-1.5">DB Host</label>
                <input class="form-input" type="text" name="db_host" id="db_host" value="localhost" placeholder="localhost">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1.5">Port</label>
                <input class="form-input" type="number" name="db_port" id="db_port" value="3306">
              </div>
            </div>
            <div class="mb-4">
              <label class="block text-xs font-semibold text-slate-400 mb-1.5">Database Name</label>
              <input class="form-input" type="text" name="db_name" id="db_name" placeholder="spellcaster" required>
            </div>
            <div class="mb-4">
              <label class="block text-xs font-semibold text-slate-400 mb-1.5">Username</label>
              <input class="form-input" type="text" name="db_user" id="db_user" placeholder="db_username" required>
            </div>
            <div class="mb-6" x-data="{show:false}">
              <label class="block text-xs font-semibold text-slate-400 mb-1.5">Password</label>
              <div class="relative">
                <input class="form-input pr-10" :type="show?'text':'password'" name="db_pass" id="db_pass" placeholder="••••••••">
                <button type="button" @click="show=!show" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
              </div>
            </div>

            <!-- Test result -->
            <div x-show="dbResult" x-cloak class="mb-4 p-3 rounded-lg text-sm font-medium" :class="dbOk?'text-emerald-400':'text-red-400'" :style="dbOk?'background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2)':'background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2)'" x-text="dbResult"></div>

            <div class="flex gap-3">
              <button type="button" class="btn-secondary-wiz" :disabled="testing" @click="testDb()">
                <span x-show="!testing">🔌 Test Connection</span>
                <span x-show="testing" x-cloak>Testing…</span>
              </button>
              <button type="submit" class="btn-magic flex-1 justify-center" :disabled="!dbOk">
                Next Step →
              </button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- ─── STEP 3: SMTP ─── -->
      <?php if ($currentStep === 3): ?>
      <div class="step-panel" x-data="{smtpTested:false,testing:false,smtpResult:'',smtpOk:false}">
        <div class="mb-8">
          <div class="text-xs font-bold text-indigo-400 uppercase tracking-widest mb-2">Step 3 of 6</div>
          <h1 class="text-2xl font-black text-white mb-1">Email / SMTP Setup</h1>
          <p class="text-slate-400 text-sm">Configure how Merlin sends emails. You can skip and use PHP mail().</p>
        </div>

        <div class="rounded-2xl p-6" style="background:#111827;border:1px solid rgba(148,163,184,0.08);">
          <!-- Quick presets -->
          <div class="mb-4">
            <div class="text-xs font-semibold text-slate-500 mb-2 uppercase tracking-wider">Quick Presets</div>
            <div class="flex flex-wrap gap-2" x-data="">
              <button type="button" class="preset-btn" @click="fillSmtp('smtp.gmail.com','587','tls','','','Gmail')">Gmail</button>
              <button type="button" class="preset-btn" @click="fillSmtp('smtp-mail.outlook.com','587','tls','','','Outlook')">Outlook</button>
              <button type="button" class="preset-btn" @click="fillSmtp('smtp.mailgun.org','587','tls','','','Mailgun')">Mailgun</button>
              <button type="button" class="preset-btn" @click="fillSmtp('smtp.sendgrid.net','587','tls','apikey','','SendGrid')">SendGrid</button>
              <button type="button" class="preset-btn" @click="fillSmtp('smtp.postmarkapp.com','587','tls','','','Postmark')">Postmark</button>
            </div>
          </div>

          <form method="post">
            <input type="hidden" name="action" value="save_smtp">
            <div class="grid grid-cols-3 gap-3 mb-4">
              <div class="col-span-2">
                <label class="block text-xs font-semibold text-slate-400 mb-1.5">SMTP Host</label>
                <input class="form-input" type="text" name="smtp_host" id="smtp_host" placeholder="smtp.gmail.com">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1.5">Port</label>
                <input class="form-input" type="number" name="smtp_port" id="smtp_port" value="587">
              </div>
            </div>
            <div class="mb-4">
              <label class="block text-xs font-semibold text-slate-400 mb-1.5">Encryption</label>
              <select class="form-input" name="smtp_enc" id="smtp_enc">
                <option value="tls">TLS (STARTTLS) — Recommended</option>
                <option value="ssl">SSL</option>
                <option value="none">None</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-3 mb-4">
              <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1.5">Username</label>
                <input class="form-input" type="text" name="smtp_user" id="smtp_user" placeholder="user@example.com">
              </div>
              <div x-data="{show:false}">
                <label class="block text-xs font-semibold text-slate-400 mb-1.5">Password</label>
                <div class="relative">
                  <input class="form-input pr-10" :type="show?'text':'password'" name="smtp_pass" id="smtp_pass">
                  <button type="button" @click="show=!show" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                  </button>
                </div>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3 mb-6">
              <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1.5">From Name</label>
                <input class="form-input" type="text" name="smtp_from_name" placeholder="Merlin Spellcaster">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1.5">From Email</label>
                <input class="form-input" type="email" name="smtp_from_email" placeholder="hi@example.com">
              </div>
            </div>

            <div x-show="smtpResult" x-cloak class="mb-4 p-3 rounded-lg text-sm font-medium" :class="smtpOk?'text-emerald-400':'text-red-400'" :style="smtpOk?'background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2)':'background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2)'" x-text="smtpResult"></div>

            <div class="flex gap-3">
              <button type="button" class="btn-secondary-wiz" :disabled="testing" @click="testSmtp()">
                <span x-show="!testing">🔌 Test SMTP</span>
                <span x-show="testing" x-cloak>Testing…</span>
              </button>
              <button type="submit" class="btn-magic flex-1 justify-center">Save & Continue →</button>
            </div>
            <div class="text-center mt-3">
              <button type="submit" name="action" value="skip_smtp" formaction="?step=3" class="text-slate-500 hover:text-slate-300 text-sm underline">Skip for now (use PHP mail())</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- ─── STEP 4: Admin Account ─── -->
      <?php if ($currentStep === 4): ?>
      <div class="step-panel" x-data="{name:'',passScore:0,passMatch:true}">
        <div class="mb-8">
          <div class="text-xs font-bold text-indigo-400 uppercase tracking-widest mb-2">Step 4 of 6</div>
          <h1 class="text-2xl font-black text-white mb-1">Create Admin Account</h1>
          <p class="text-slate-400 text-sm">This will be your login to manage everything.</p>
        </div>

        <div class="rounded-2xl p-6" style="background:#111827;border:1px solid rgba(148,163,184,0.08);">
          <!-- Avatar preview -->
          <div class="flex justify-center mb-6">
            <div class="w-16 h-16 rounded-full flex items-center justify-center text-2xl font-black text-white" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
              <span x-text="name.split(' ').map(w=>w[0]??'').join('').toUpperCase().slice(0,2) || '?'"></span>
            </div>
          </div>

          <form method="post">
            <input type="hidden" name="action" value="create_admin">
            <div class="mb-4">
              <label class="block text-xs font-semibold text-slate-400 mb-1.5">Full Name *</label>
              <input class="form-input" type="text" name="admin_name" x-model="name" placeholder="John Merlin" required>
            </div>
            <div class="mb-4">
              <label class="block text-xs font-semibold text-slate-400 mb-1.5">Email Address *</label>
              <input class="form-input" type="email" name="admin_email" placeholder="admin@example.com" required>
            </div>
            <div class="mb-4" x-data="{show:false,pass:''}" x-init="$watch('pass',v=>passScore=scorePass(v))">
              <label class="block text-xs font-semibold text-slate-400 mb-1.5">Password *</label>
              <div class="relative">
                <input class="form-input pr-10" :type="show?'text':'password'" name="admin_pass" x-model="pass" placeholder="Min 8 characters" required>
                <button type="button" @click="show=!show" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
              </div>
              <!-- Strength bar -->
              <div class="flex gap-1 mt-2">
                <div class="strength-bar" :style="passScore>=1?'background:#ef4444':''"></div>
                <div class="strength-bar" :style="passScore>=2?'background:#f59e0b':''"></div>
                <div class="strength-bar" :style="passScore>=3?'background:#3b82f6':''"></div>
                <div class="strength-bar" :style="passScore>=4?'background:#10b981':''"></div>
              </div>
              <div class="text-xs mt-1" :class="['text-red-400','text-amber-400','text-blue-400','text-emerald-400'][passScore-1]||'text-slate-600'" x-text="['','Weak','Fair','Good','Strong'][passScore]||'Enter password'"></div>
            </div>
            <div class="mb-6">
              <label class="block text-xs font-semibold text-slate-400 mb-1.5">Confirm Password *</label>
              <input class="form-input" type="password" name="admin_confirm" @input="checkMatch($event.target)" placeholder="Repeat password" required>
              <div x-show="!passMatch" x-cloak class="text-xs text-red-400 mt-1">Passwords don't match</div>
            </div>
            <button type="submit" class="btn-magic w-full justify-center">Create Account →</button>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- ─── STEP 5: First List ─── -->
      <?php if ($currentStep === 5): ?>
      <div class="step-panel">
        <div class="mb-8">
          <div class="text-xs font-bold text-indigo-400 uppercase tracking-widest mb-2">Step 5 of 6</div>
          <h1 class="text-2xl font-black text-white mb-1">Create Your First List</h1>
          <p class="text-slate-400 text-sm">Every subscriber belongs to a list. You can create more later.</p>
        </div>

        <div class="rounded-2xl p-6" style="background:#111827;border:1px solid rgba(148,163,184,0.08);">
          <form method="post">
            <input type="hidden" name="action" value="create_list">
            <div class="mb-4">
              <label class="block text-xs font-semibold text-slate-400 mb-1.5">List Name *</label>
              <input class="form-input" type="text" name="list_name" placeholder="Main Newsletter" required>
            </div>
            <div class="mb-4">
              <label class="block text-xs font-semibold text-slate-400 mb-1.5">Description</label>
              <textarea class="form-input h-20 resize-none" name="list_desc" placeholder="A brief description of this list…"></textarea>
            </div>
            <div class="mb-4">
              <div class="text-xs font-semibold text-slate-400 mb-2">List Type</div>
              <div class="flex gap-3">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" name="list_type" value="public" checked class="accent-indigo-500">
                  <span class="text-sm text-slate-300">Public — show in subscription forms</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" name="list_type" value="private" class="accent-indigo-500">
                  <span class="text-sm text-slate-300">Private — internal only</span>
                </label>
              </div>
            </div>
            <div class="mb-6 flex items-center gap-3 p-3 rounded-xl" style="background:rgba(99,102,241,0.07);border:1px solid rgba(99,102,241,0.15);">
              <input type="checkbox" name="double_optin" id="dbl_optin" class="accent-indigo-500 w-4 h-4">
              <label for="dbl_optin" class="text-sm text-slate-300 cursor-pointer">Enable Double Opt-in (send confirmation email before adding)</label>
            </div>
            <div class="flex gap-3">
              <button type="submit" class="btn-magic flex-1 justify-center">Create List →</button>
            </div>
          </form>
          <div class="text-center mt-3">
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="skip_list">
              <button type="submit" class="text-slate-500 hover:text-slate-300 text-sm underline">Skip for now</button>
            </form>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- ─── STEP 6: Launch ─── -->
      <?php if ($currentStep === 6): ?>
      <div class="step-panel text-center" x-init="launchConfetti()">
        <div class="mb-6">
          <div class="text-6xl mb-4 animate-bounce">🎉</div>
          <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6" style="background:rgba(16,185,129,0.15);border:2px solid rgba(16,185,129,0.4);">
            <svg class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
          </div>
          <h1 class="text-3xl font-black text-white mb-2">You're all set!</h1>
          <p class="text-slate-400">Merlin Spellcaster is ready to cast spells on your audience.</p>
        </div>

        <!-- Summary -->
        <div class="rounded-2xl p-5 mb-6 text-left space-y-3" style="background:#111827;border:1px solid rgba(148,163,184,0.08);">
          <div class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Setup Summary</div>
          <div class="flex items-center gap-3 text-sm">
            <span class="text-emerald-400">✓</span>
            <span class="text-slate-300">Database connected</span>
          </div>
          <div class="flex items-center gap-3 text-sm">
            <span class="text-<?= !empty($_SESSION['setup_smtp']) ? 'emerald' : 'amber' ?>-400"><?= !empty($_SESSION['setup_smtp']) ? '✓' : '~' ?></span>
            <span class="text-slate-300"><?= !empty($_SESSION['setup_smtp']) ? 'SMTP configured' : 'Using PHP mail()' ?></span>
          </div>
          <div class="flex items-center gap-3 text-sm">
            <span class="text-emerald-400">✓</span>
            <span class="text-slate-300">Admin account: <strong class="text-white"><?= htmlspecialchars($_SESSION['setup_admin']['email'] ?? 'created', ENT_QUOTES, 'UTF-8') ?></strong></span>
          </div>
          <div class="flex items-center gap-3 text-sm">
            <span class="text-<?= !empty($_SESSION['setup_list']) ? 'emerald' : 'amber' ?>-400"><?= !empty($_SESSION['setup_list']) ? '✓' : '~' ?></span>
            <span class="text-slate-300"><?= !empty($_SESSION['setup_list']) ? 'List: <strong class="text-white">'.htmlspecialchars($_SESSION['setup_list']['name']).'</strong>' : 'No list created' ?></span>
          </div>
        </div>

        <form method="post">
          <input type="hidden" name="action" value="finish">
          <div class="flex gap-3">
            <button type="submit" class="btn-magic flex-1 justify-center text-base py-3">
              Go to Dashboard →
            </button>
            <a href="/admin/imports.php" class="btn-secondary-wiz flex-1 justify-center">Import Subscribers</a>
          </div>
        </form>
      </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<script>
function wizard() {
  return {
    init() {},
    // DB test
    async testDb() {
      this.testing = true;
      const form = document.getElementById('dbForm');
      const data = new FormData(form);
      data.set('action', 'db');
      try {
        const r = await fetch('/setup/test_db.php?action=db', {method:'POST', body: new URLSearchParams({
          host: document.getElementById('db_host').value,
          port: document.getElementById('db_port').value,
          name: document.getElementById('db_name').value,
          user: document.getElementById('db_user').value,
          pass: document.getElementById('db_pass').value,
        })});
        const j = await r.json();
        this.dbResult = (j.success ? '✅ ' : '❌ ') + j.message;
        this.dbOk = j.success;
      } catch(e) { this.dbResult = '❌ Network error'; this.dbOk = false; }
      this.testing = false;
    },
    // SMTP test
    async testSmtp() {
      this.testing = true;
      try {
        const r = await fetch('/setup/test_db.php?action=smtp', {method:'POST', body: new URLSearchParams({
          host: document.getElementById('smtp_host').value,
          port: document.getElementById('smtp_port').value,
          encryption: document.getElementById('smtp_enc').value,
          smtp_user: document.getElementById('smtp_user').value,
          smtp_pass: document.getElementById('smtp_pass').value,
        })});
        const j = await r.json();
        this.smtpResult = (j.success ? '✅ ' : '❌ ') + j.message;
        this.smtpOk = j.success;
      } catch(e) { this.smtpResult = '❌ Network error'; this.smtpOk = false; }
      this.testing = false;
    }
  };
}

function fillSmtp(host, port, enc, user, label) {
  document.getElementById('smtp_host').value = host;
  document.getElementById('smtp_port').value = port;
  document.getElementById('smtp_enc').value  = enc;
  if (user) document.getElementById('smtp_user').value = user;
}

function scorePass(p) {
  let s = 0;
  if (p.length >= 8) s++;
  if (/[A-Z]/.test(p)) s++;
  if (/[0-9]/.test(p)) s++;
  if (/[^A-Za-z0-9]/.test(p)) s++;
  return s;
}

function checkMatch(el) {
  const pass = document.querySelector('[name="admin_pass"]').value;
  // find Alpine component
  // simple DOM approach
  const indicator = document.querySelector('[x-show="!passMatch"]');
  if (el.value && el.value !== pass) {
    if (indicator) indicator.style.display = 'block';
  } else {
    if (indicator) indicator.style.display = 'none';
  }
}

function launchConfetti() {
  const colors = ['#6366f1','#8b5cf6','#ec4899','#22d3ee','#f59e0b','#10b981'];
  for (let i = 0; i < 80; i++) {
    setTimeout(() => {
      const el = document.createElement('div');
      el.className = 'confetti-piece';
      el.style.left = Math.random() * 100 + 'vw';
      el.style.background = colors[Math.floor(Math.random() * colors.length)];
      el.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
      el.style.width = (Math.random() * 8 + 4) + 'px';
      el.style.height = (Math.random() * 8 + 4) + 'px';
      el.style.animationDuration = (Math.random() * 2 + 2) + 's';
      el.style.animationDelay = Math.random() + 's';
      document.getElementById('confetti-container').appendChild(el);
      setTimeout(() => el.remove(), 4000);
    }, i * 30);
  }
}
</script>
</body>
</html>
