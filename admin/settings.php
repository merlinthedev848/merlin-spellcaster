<?php
/**
 * admin/settings.php — Application settings
 * PHP 8.5+
 */
declare(strict_types=1);
$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::checkCsrf()) { flash('error', 'Invalid security token (CSRF). Please try again.'); sc_redirect(<?php
/**
 * admin/settings.php — Application settings
 * PHP 8.5+
 */
declare(strict_types=1);
$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';

    if ($section === 'general') {
        $fields = ['app_name','app_url','company_name','company_address','unsubscribe_message','tracking_enabled','cron_batch_size'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) setSetting($db, $f, trim($_POST[$f]));
        }
        flash('success','General settings saved.');
    }

    if ($section === 'smtp') {
        $fields = ['smtp_host','smtp_port','smtp_encryption','smtp_user','smtp_from_name','smtp_from_email'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) setSetting($db, $f, trim($_POST[$f]));
        }
        // Only update password if provided
        if (!empty($_POST['smtp_pass'])) {
            setSetting($db, 'smtp_pass', $_POST['smtp_pass']);
        }
        flash('success','SMTP settings saved.');
    }

    if ($section === 'regen_secret') {
        setSetting($db, 'cron_secret', bin2hex(random_bytes(16)));
        flash('success','Cron secret regenerated.');
    }

    if ($section === 'test_smtp') {
        require_once dirname(__DIR__) . '/core/Mailer.php';
        $testTo = trim($_POST['test_email'] ?? '');
        if (filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            $mailer = new Mailer();
            $ok = $mailer->send($testTo, 'Test Email from Merlin Spellcaster', '<h2>🧙 It works!</h2><p>Your SMTP configuration is working correctly.</p>', 'It works! Your SMTP configuration is working correctly.');
            flash($ok ? 'success' : 'error', $ok ? 'Test email sent to '.$testTo : 'Failed to send: '.$mailer->getLastError());
        } else {
            flash('error', 'Invalid email address for test.');
        }
    }

    if ($section === 'clear_queue') {
        $db->exec("DELETE FROM email_queue WHERE status IN ('pending','failed')");
        flash('success', 'Pending and failed email queue entries were cleared.');
    }

    if ($section === 'reset_setup') {
        setSetting($db, 'setup_complete', '0');
        flash('success', 'Setup wizard has been re-enabled for the current admin session.');
        sc_redirect('/setup/');
    }

    sc_redirect('/admin/settings.php');
}

$cronSecret = getSetting('cron_secret');
$appUrl     = getSetting('app_url');
$tab        = $_GET['tab'] ?? 'general';
?>

<div class="p-6 space-y-5">
  <div>
    <h1 class="text-2xl font-bold text-white">Settings</h1>
    <p class="text-sm text-slate-400 mt-0.5">Configure your Merlin Spellcaster installation</p>
  </div>

  <!-- Tabs -->
  <div class="flex gap-1 border-b border-white/5">
    <?php foreach (['general'=>'⚙️ General','smtp'=>'📬 SMTP / Email','cron'=>'⏱ Cron & API','danger'=>'🔴 Danger Zone'] as $t=>$l): ?>
    <a href="?tab=<?= $t ?>" class="px-4 py-2 text-sm font-semibold border-b-2 transition-all <?= $tab===$t?'border-indigo-500 text-indigo-400':'border-transparent text-slate-500 hover:text-slate-300' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>

  <!-- General -->
  <?php if ($tab === 'general'): ?>
  <form method="post" class="card p-6 space-y-4 max-w-2xl">
    <input type="hidden" name="section" value="general">
    <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider">General Settings</h2>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Application Name</label>
        <input type="text" name="app_name" value="<?= e(getSetting('app_name')) ?>" class="form-input w-full">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Application URL</label>
        <input type="url" name="app_url" value="<?= e($appUrl) ?>" class="form-input w-full" placeholder="https://yourdomain.com">
      </div>
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">Company Name</label>
      <input type="text" name="company_name" value="<?= e(getSetting('company_name')) ?>" class="form-input w-full">
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">Company Address <span class="text-slate-600">(shown in email footers per CAN-SPAM)</span></label>
      <textarea name="company_address" rows="2" class="form-input w-full"><?= e(getSetting('company_address')) ?></textarea>
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">Unsubscribe Message</label>
      <input type="text" name="unsubscribe_message" value="<?= e(getSetting('unsubscribe_message')) ?>" class="form-input w-full">
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Batch Size (emails per cron run)</label>
        <input type="number" name="cron_batch_size" value="<?= e(getSetting('cron_batch_size','50')) ?>" min="1" max="500" class="form-input w-full">
      </div>
      <div class="flex items-center gap-3 pt-5">
        <input type="checkbox" name="tracking_enabled" id="tracking" value="1" <?= getSetting('tracking_enabled','1')==='1'?'checked':'' ?> class="accent-indigo-500 w-4 h-4">
        <label for="tracking" class="text-sm text-slate-300 cursor-pointer">Enable open/click tracking</label>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Save General Settings</button>
  </form>
  <?php endif; ?>

  <!-- SMTP -->
  <?php if ($tab === 'smtp'): ?>
  <div class="space-y-4 max-w-2xl">
    <form method="post" class="card p-6 space-y-4">
      <input type="hidden" name="section" value="smtp">
      <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider">SMTP Configuration</h2>
      <div class="grid grid-cols-3 gap-3">
        <div class="col-span-2">
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">SMTP Host</label>
          <input type="text" name="smtp_host" value="<?= e(getSetting('smtp_host')) ?>" class="form-input w-full" placeholder="smtp.gmail.com">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">Port</label>
          <input type="number" name="smtp_port" value="<?= e(getSetting('smtp_port','587')) ?>" class="form-input w-full">
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Encryption</label>
        <select name="smtp_encryption" class="form-input w-full">
          <?php foreach (['tls'=>'TLS (STARTTLS)','ssl'=>'SSL','none'=>'None'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= getSetting('smtp_encryption','tls')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">SMTP Username</label>
          <input type="text" name="smtp_user" value="<?= e(getSetting('smtp_user')) ?>" class="form-input w-full">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">SMTP Password <span class="text-slate-600">(leave blank to keep)</span></label>
          <input type="password" name="smtp_pass" class="form-input w-full" placeholder="••••••••">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">From Name</label>
          <input type="text" name="smtp_from_name" value="<?= e(getSetting('smtp_from_name')) ?>" class="form-input w-full">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">From Email</label>
          <input type="email" name="smtp_from_email" value="<?= e(getSetting('smtp_from_email')) ?>" class="form-input w-full">
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Save SMTP Settings</button>
    </form>

    <!-- Test email -->
    <form method="post" class="card p-5 flex gap-3 items-end">
      <input type="hidden" name="section" value="test_smtp">
      <div class="flex-1">
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Send Test Email</label>
        <input type="email" name="test_email" class="form-input w-full" placeholder="your@email.com" required>
      </div>
      <button type="submit" class="btn btn-secondary">Send Test</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- Cron & API -->
  <?php if ($tab === 'cron'): ?>
  <div class="space-y-4 max-w-2xl">
    <div class="card p-6 space-y-4">
      <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Cron Jobs</h2>
      <p class="text-xs text-slate-500">Run these URLs as cron jobs every minute on your shared host (Enhance → Scheduled Tasks).</p>
      <?php foreach ([
        ['Send Emails',       $appUrl.'/cron/send.php?secret='.$cronSecret,    'Sends queued emails in batches'],
        ['Run Automations',   $appUrl.'/cron/automation.php?secret='.$cronSecret, 'Processes automation sequences'],
      ] as [$label,$url,$desc]): ?>
      <div class="rounded-xl p-4 space-y-2" style="background:rgba(255,255,255,0.02);border:1px solid rgba(148,163,184,0.08);">
        <div class="flex justify-between items-center">
          <span class="text-sm font-semibold text-slate-200"><?= $label ?></span>
          <span class="text-xs text-slate-600"><?= $desc ?></span>
        </div>
        <div class="flex items-center gap-2">
          <input type="text" value="<?= e($url) ?>" readonly class="text-xs bg-slate-900 border border-white/10 rounded-lg px-3 py-2 flex-1 text-slate-400 focus:outline-none font-mono">
          <button type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.textContent='✓ Copied'" class="text-xs text-indigo-400 hover:text-indigo-300 px-3 py-2 rounded-lg bg-indigo-900/20 border border-indigo-700/20 whitespace-nowrap">Copy</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="card p-6 space-y-4">
      <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider">API Token</h2>
      <p class="text-xs text-slate-500">Use this secret for API requests (Bearer token) and cron URLs.</p>
      <div class="flex items-center gap-2">
        <input type="text" value="<?= e($cronSecret) ?>" readonly class="text-xs bg-slate-900 border border-white/10 rounded-lg px-3 py-2 flex-1 text-slate-400 focus:outline-none font-mono">
        <form method="post" style="display:inline">
          <input type="hidden" name="section" value="regen_secret">
          <button type="submit" onclick="return confirm('Regenerate secret? This will break existing cron URLs.')" class="btn btn-secondary text-xs">🔄 Regenerate</button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Danger Zone -->
  <?php if ($tab === 'danger'): ?>
  <div class="card p-6 max-w-2xl space-y-4" style="border-color:rgba(239,68,68,0.2)">
    <h2 class="text-sm font-bold text-red-400 uppercase tracking-wider">⚠️ Danger Zone</h2>
    <p class="text-sm text-slate-400">These actions are irreversible. Make a backup first.</p>
    <div class="space-y-3">
      <div class="flex items-center justify-between p-4 rounded-xl" style="background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.15)">
        <div>
          <p class="text-sm font-semibold text-slate-200">Clear Email Queue</p>
          <p class="text-xs text-slate-500">Remove all pending/failed emails from the queue</p>
        </div>
        <form method="post" onsubmit="return confirm('Clear ALL pending emails from queue?')">
          <input type="hidden" name="section" value="clear_queue">
          <button class="btn text-xs py-1.5 px-3" style="background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3)">Clear Queue</button>
        </form>
      </div>
      <div class="flex items-center justify-between p-4 rounded-xl" style="background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.15)">
        <div>
          <p class="text-sm font-semibold text-slate-200">Re-run Setup Wizard</p>
          <p class="text-xs text-slate-500">Reset setup_complete flag to restart onboarding</p>
        </div>
        <form method="post" onsubmit="return confirm('Reset setup wizard?')">
          <input type="hidden" name="section" value="reset_setup">
          <button class="btn text-xs py-1.5 px-3" style="background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3)">Reset Setup</button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>
SERVER['REQUEST_URI']); }
    $section = $_POST['section'] ?? '';

    if ($section === 'general') {
        $fields = ['app_name','app_url','company_name','company_address','unsubscribe_message','tracking_enabled','cron_batch_size'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) setSetting($db, $f, trim($_POST[$f]));
        }
        flash('success','General settings saved.');
    }

    if ($section === 'smtp') {
        $fields = ['smtp_host','smtp_port','smtp_encryption','smtp_user','smtp_from_name','smtp_from_email'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) setSetting($db, $f, trim($_POST[$f]));
        }
        // Only update password if provided
        if (!empty($_POST['smtp_pass'])) {
            setSetting($db, 'smtp_pass', $_POST['smtp_pass']);
        }
        flash('success','SMTP settings saved.');
    }

    if ($section === 'regen_secret') {
        setSetting($db, 'cron_secret', bin2hex(random_bytes(16)));
        flash('success','Cron secret regenerated.');
    }

    if ($section === 'test_smtp') {
        require_once dirname(__DIR__) . '/core/Mailer.php';
        $testTo = trim($_POST['test_email'] ?? '');
        if (filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            $mailer = new Mailer();
            $ok = $mailer->send($testTo, 'Test Email from Merlin Spellcaster', '<h2>🧙 It works!</h2><p>Your SMTP configuration is working correctly.</p>', 'It works! Your SMTP configuration is working correctly.');
            flash($ok ? 'success' : 'error', $ok ? 'Test email sent to '.$testTo : 'Failed to send: '.$mailer->getLastError());
        } else {
            flash('error', 'Invalid email address for test.');
        }
    }

    if ($section === 'clear_queue') {
        $db->exec("DELETE FROM email_queue WHERE status IN ('pending','failed')");
        flash('success', 'Pending and failed email queue entries were cleared.');
    }

    if ($section === 'reset_setup') {
        setSetting($db, 'setup_complete', '0');
        flash('success', 'Setup wizard has been re-enabled for the current admin session.');
        sc_redirect('/setup/');
    }

    sc_redirect('/admin/settings.php');
}

$cronSecret = getSetting('cron_secret');
$appUrl     = getSetting('app_url');
$tab        = $_GET['tab'] ?? 'general';
?>

<div class="p-6 space-y-5">
  <div>
    <h1 class="text-2xl font-bold text-white">Settings</h1>
    <p class="text-sm text-slate-400 mt-0.5">Configure your Merlin Spellcaster installation</p>
  </div>

  <!-- Tabs -->
  <div class="flex gap-1 border-b border-white/5">
    <?php foreach (['general'=>'⚙️ General','smtp'=>'📬 SMTP / Email','cron'=>'⏱ Cron & API','danger'=>'🔴 Danger Zone'] as $t=>$l): ?>
    <a href="?tab=<?= $t ?>" class="px-4 py-2 text-sm font-semibold border-b-2 transition-all <?= $tab===$t?'border-indigo-500 text-indigo-400':'border-transparent text-slate-500 hover:text-slate-300' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>

  <!-- General -->
  <?php if ($tab === 'general'): ?>
  <form method="post" class="card p-6 space-y-4 max-w-2xl">
    <input type="hidden" name="section" value="general">
    <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider">General Settings</h2>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Application Name</label>
        <input type="text" name="app_name" value="<?= e(getSetting('app_name')) ?>" class="form-input w-full">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Application URL</label>
        <input type="url" name="app_url" value="<?= e($appUrl) ?>" class="form-input w-full" placeholder="https://yourdomain.com">
      </div>
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">Company Name</label>
      <input type="text" name="company_name" value="<?= e(getSetting('company_name')) ?>" class="form-input w-full">
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">Company Address <span class="text-slate-600">(shown in email footers per CAN-SPAM)</span></label>
      <textarea name="company_address" rows="2" class="form-input w-full"><?= e(getSetting('company_address')) ?></textarea>
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">Unsubscribe Message</label>
      <input type="text" name="unsubscribe_message" value="<?= e(getSetting('unsubscribe_message')) ?>" class="form-input w-full">
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Batch Size (emails per cron run)</label>
        <input type="number" name="cron_batch_size" value="<?= e(getSetting('cron_batch_size','50')) ?>" min="1" max="500" class="form-input w-full">
      </div>
      <div class="flex items-center gap-3 pt-5">
        <input type="checkbox" name="tracking_enabled" id="tracking" value="1" <?= getSetting('tracking_enabled','1')==='1'?'checked':'' ?> class="accent-indigo-500 w-4 h-4">
        <label for="tracking" class="text-sm text-slate-300 cursor-pointer">Enable open/click tracking</label>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Save General Settings</button>
  </form>
  <?php endif; ?>

  <!-- SMTP -->
  <?php if ($tab === 'smtp'): ?>
  <div class="space-y-4 max-w-2xl">
    <form method="post" class="card p-6 space-y-4">
      <input type="hidden" name="section" value="smtp">
      <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider">SMTP Configuration</h2>
      <div class="grid grid-cols-3 gap-3">
        <div class="col-span-2">
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">SMTP Host</label>
          <input type="text" name="smtp_host" value="<?= e(getSetting('smtp_host')) ?>" class="form-input w-full" placeholder="smtp.gmail.com">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">Port</label>
          <input type="number" name="smtp_port" value="<?= e(getSetting('smtp_port','587')) ?>" class="form-input w-full">
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Encryption</label>
        <select name="smtp_encryption" class="form-input w-full">
          <?php foreach (['tls'=>'TLS (STARTTLS)','ssl'=>'SSL','none'=>'None'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= getSetting('smtp_encryption','tls')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">SMTP Username</label>
          <input type="text" name="smtp_user" value="<?= e(getSetting('smtp_user')) ?>" class="form-input w-full">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">SMTP Password <span class="text-slate-600">(leave blank to keep)</span></label>
          <input type="password" name="smtp_pass" class="form-input w-full" placeholder="••••••••">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">From Name</label>
          <input type="text" name="smtp_from_name" value="<?= e(getSetting('smtp_from_name')) ?>" class="form-input w-full">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">From Email</label>
          <input type="email" name="smtp_from_email" value="<?= e(getSetting('smtp_from_email')) ?>" class="form-input w-full">
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Save SMTP Settings</button>
    </form>

    <!-- Test email -->
    <form method="post" class="card p-5 flex gap-3 items-end">
      <input type="hidden" name="section" value="test_smtp">
      <div class="flex-1">
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Send Test Email</label>
        <input type="email" name="test_email" class="form-input w-full" placeholder="your@email.com" required>
      </div>
      <button type="submit" class="btn btn-secondary">Send Test</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- Cron & API -->
  <?php if ($tab === 'cron'): ?>
  <div class="space-y-4 max-w-2xl">
    <div class="card p-6 space-y-4">
      <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Cron Jobs</h2>
      <p class="text-xs text-slate-500">Run these URLs as cron jobs every minute on your shared host (Enhance → Scheduled Tasks).</p>
      <?php foreach ([
        ['Send Emails',       $appUrl.'/cron/send.php?secret='.$cronSecret,    'Sends queued emails in batches'],
        ['Run Automations',   $appUrl.'/cron/automation.php?secret='.$cronSecret, 'Processes automation sequences'],
      ] as [$label,$url,$desc]): ?>
      <div class="rounded-xl p-4 space-y-2" style="background:rgba(255,255,255,0.02);border:1px solid rgba(148,163,184,0.08);">
        <div class="flex justify-between items-center">
          <span class="text-sm font-semibold text-slate-200"><?= $label ?></span>
          <span class="text-xs text-slate-600"><?= $desc ?></span>
        </div>
        <div class="flex items-center gap-2">
          <input type="text" value="<?= e($url) ?>" readonly class="text-xs bg-slate-900 border border-white/10 rounded-lg px-3 py-2 flex-1 text-slate-400 focus:outline-none font-mono">
          <button type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.textContent='✓ Copied'" class="text-xs text-indigo-400 hover:text-indigo-300 px-3 py-2 rounded-lg bg-indigo-900/20 border border-indigo-700/20 whitespace-nowrap">Copy</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="card p-6 space-y-4">
      <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider">API Token</h2>
      <p class="text-xs text-slate-500">Use this secret for API requests (Bearer token) and cron URLs.</p>
      <div class="flex items-center gap-2">
        <input type="text" value="<?= e($cronSecret) ?>" readonly class="text-xs bg-slate-900 border border-white/10 rounded-lg px-3 py-2 flex-1 text-slate-400 focus:outline-none font-mono">
        <form method="post" style="display:inline">
          <input type="hidden" name="section" value="regen_secret">
          <button type="submit" onclick="return confirm('Regenerate secret? This will break existing cron URLs.')" class="btn btn-secondary text-xs">🔄 Regenerate</button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Danger Zone -->
  <?php if ($tab === 'danger'): ?>
  <div class="card p-6 max-w-2xl space-y-4" style="border-color:rgba(239,68,68,0.2)">
    <h2 class="text-sm font-bold text-red-400 uppercase tracking-wider">⚠️ Danger Zone</h2>
    <p class="text-sm text-slate-400">These actions are irreversible. Make a backup first.</p>
    <div class="space-y-3">
      <div class="flex items-center justify-between p-4 rounded-xl" style="background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.15)">
        <div>
          <p class="text-sm font-semibold text-slate-200">Clear Email Queue</p>
          <p class="text-xs text-slate-500">Remove all pending/failed emails from the queue</p>
        </div>
        <form method="post" onsubmit="return confirm('Clear ALL pending emails from queue?')">
          <input type="hidden" name="section" value="clear_queue">
          <button class="btn text-xs py-1.5 px-3" style="background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3)">Clear Queue</button>
        </form>
      </div>
      <div class="flex items-center justify-between p-4 rounded-xl" style="background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.15)">
        <div>
          <p class="text-sm font-semibold text-slate-200">Re-run Setup Wizard</p>
          <p class="text-xs text-slate-500">Reset setup_complete flag to restart onboarding</p>
        </div>
        <form method="post" onsubmit="return confirm('Reset setup wizard?')">
          <input type="hidden" name="section" value="reset_setup">
          <button class="btn text-xs py-1.5 px-3" style="background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3)">Reset Setup</button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>
