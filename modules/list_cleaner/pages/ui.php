<?php
declare(strict_types=1);
$pageTitle = 'List Cleaner & Verifier';
require_once dirname(__DIR__, 3) . '/includes/header.php';

$lists = $db->query("SELECT * FROM lists ORDER BY name")->fetchAll();
?>

<div class="flex items-center gap-4 mb-8">
  <h1 class="text-2xl font-bold text-white">List Cleaner & Verifier</h1>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
  <div class="card p-6 border-t-4 border-rose-500">
    <h2 class="text-lg font-bold text-white mb-4">Clean a List</h2>
    <form method="post" onsubmit="alert('Requires ZeroBounce API Key. Add it in Settings to activate the cleaner.'); return false;">
      <?= Auth::csrfField() ?>
      <div class="mb-4">
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Select List</label>
        <select name="list_id" class="form-input w-full" required>
            <option value="">-- Choose a list --</option>
            <?php foreach ($lists as $l): ?>
                <option value="<?= $l['id'] ?>"><?= e($l['name']) ?> (<?= $l['subscriber_count'] ?> subs)</option>
            <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-6">
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="delete_invalid" value="1" checked class="rounded border-rose-500/50 text-rose-500 focus:ring-rose-500 bg-slate-900/50">
            <span class="text-sm font-semibold text-slate-300">Automatically delete 'invalid' and 'spam_trap' emails</span>
        </label>
      </div>
      <button type="submit" class="btn btn-primary bg-rose-600 hover:bg-rose-500 w-full justify-center">Start Cleaning</button>
    </form>
  </div>
  
  <div class="card p-6 bg-rose-500/5 border border-rose-500/20">
      <h3 class="font-bold text-rose-400 mb-2">Protect Your Deliverability</h3>
      <p class="text-slate-300 text-sm mb-4 leading-relaxed">
          Hard bounces and spam traps are the #1 reason emails go to the spam folder. The List Cleaner pings each email address against the ZeroBounce API to ensure it's a real, active inbox before you ever hit send.
      </p>
      <ul class="text-sm text-slate-400 space-y-2">
          <li class="flex items-center gap-2">✓ Detects hard bounces</li>
          <li class="flex items-center gap-2">✓ Identifies spam traps</li>
          <li class="flex items-center gap-2">✓ Flags catch-all domains</li>
      </ul>
  </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
