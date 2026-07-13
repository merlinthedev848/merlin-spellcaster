<?php
/**
 * admin/imports.php — CSV / paste import subscribers
 * PHP 8.5+
 */
declare(strict_types=1);
$pageTitle = 'Import Subscribers';
require_once __DIR__ . '/../includes/header.php';

$lists   = $db->query("SELECT id,name FROM lists ORDER BY name")->fetchAll();
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $listId   = (int)($_POST['list_id'] ?? 0);
    $source   = trim($_POST['source'] ?? 'import');
    $mode     = $_POST['mode'] ?? 'csv'; // csv | paste

    $rows = [];
    if ($mode === 'paste') {
        $raw = trim($_POST['paste_data'] ?? '');
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line) {
                $parts = preg_split('/[,;\t]+/', $line);
                $rows[] = array_map('trim', $parts);
            }
        }
    } elseif ($mode === 'csv' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fh = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if ($fh) {
            $header = fgetcsv($fh); // skip header
            while (($row = fgetcsv($fh)) !== false) {
                $rows[] = $row;
            }
            fclose($fh);
        }
    }

    // Field map from POST
    $emailCol = (int)($_POST['col_email'] ?? 0);
    $firstCol = (int)($_POST['col_first'] ?? -1);
    $lastCol  = (int)($_POST['col_last']  ?? -1);
    $tagsCol  = (int)($_POST['col_tags']  ?? -1);

    $added = $updated = $skipped = 0;
    $errors = [];

    $stSub  = $db->prepare("INSERT INTO subscribers (email,first_name,last_name,status,source,tags) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE first_name=IF(first_name='',VALUES(first_name),first_name), last_name=IF(last_name='',VALUES(last_name),last_name), updated_at=NOW()");
    $stList = $db->prepare("INSERT IGNORE INTO subscriber_lists (subscriber_id,list_id,status) VALUES (?,?,?)");
    $stId   = $db->prepare("SELECT id FROM subscribers WHERE email=?");

    foreach ($rows as $i => $row) {
        $email = strtolower(trim($row[$emailCol] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped++; continue; }
        $firstName = $firstCol >= 0 ? trim($row[$firstCol] ?? '') : '';
        $lastName  = $lastCol  >= 0 ? trim($row[$lastCol]  ?? '') : '';
        $tags      = $tagsCol  >= 0 ? json_encode(array_filter(array_map('trim', explode(',', $row[$tagsCol] ?? '')))) : null;

        try {
            $stSub->execute([$email, $firstName, $lastName, 'active', $source, $tags]);
            $stId->execute([$email]);
            $subId = (int)($stId->fetchColumn() ?: $db->lastInsertId());
            if ($listId && $subId) {
                $stList->execute([$subId, $listId, 'confirmed']);
            }
            $added++;
        } catch (Throwable $e) {
            $errors[] = "Row " . ($i + 2) . ": " . $e->getMessage();
            $skipped++;
        }
    }

    if ($listId) updateListCounts($db);
    logActivity($db, currentUserId(), 'imported', 'subscriber', null, "Added:{$added} Skipped:{$skipped}");
    flash('success', "Import complete: {$added} added, {$skipped} skipped.");
    $results = compact('added', 'skipped', 'errors');
}
?>

<div class="p-6 space-y-5">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-white">Import Subscribers</h1>
      <p class="text-sm text-slate-400 mt-0.5">Import from CSV or paste emails directly</p>
    </div>
    <a href="/admin/subscribers.php" class="btn btn-secondary">← Back to Subscribers</a>
  </div>

  <?php if ($results): ?>
  <div class="card p-5" style="border-color:rgba(16,185,129,0.25)">
    <h2 class="font-bold text-emerald-400 mb-3">✅ Import Complete</h2>
    <div class="grid grid-cols-3 gap-4 mb-4">
      <div class="text-center"><p class="text-2xl font-black text-white"><?= $results['added'] ?></p><p class="text-xs text-slate-500">Added</p></div>
      <div class="text-center"><p class="text-2xl font-black text-amber-400"><?= $results['skipped'] ?></p><p class="text-xs text-slate-500">Skipped/Errors</p></div>
      <div class="text-center"><p class="text-2xl font-black text-white"><?= $results['added']+$results['skipped'] ?></p><p class="text-xs text-slate-500">Total Rows</p></div>
    </div>
    <?php if ($results['errors']): ?>
    <details class="text-xs text-red-400"><summary class="cursor-pointer font-semibold">Show Errors (<?= count($results['errors']) ?>)</summary><ul class="mt-2 space-y-1"><?php foreach (array_slice($results['errors'],0,10) as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></details>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 xl:grid-cols-2 gap-5" x-data="{mode:'csv'}">

    <!-- CSV Import -->
    <div class="card p-6 space-y-4">
      <div class="flex gap-2 mb-4">
        <button type="button" @click="mode='csv'" :class="mode==='csv'?'bg-indigo-600 text-white':'text-slate-400 bg-white/5'" class="px-4 py-2 rounded-xl text-sm font-semibold transition-all">📄 Upload CSV</button>
        <button type="button" @click="mode='paste'" :class="mode==='paste'?'bg-indigo-600 text-white':'text-slate-400 bg-white/5'" class="px-4 py-2 rounded-xl text-sm font-semibold transition-all">📋 Paste Emails</button>
      </div>

      <form method="post" enctype="multipart/form-data" class="space-y-4">

        <!-- Mode -->
        <input type="hidden" name="mode" :value="mode" x-bind:value="mode">

        <!-- CSV upload -->
        <div x-show="mode==='csv'">
          <label class="block text-xs font-semibold text-slate-400 mb-2">CSV File</label>
          <div class="border-2 border-dashed border-white/10 rounded-xl p-8 text-center hover:border-indigo-500/40 transition-colors cursor-pointer" onclick="document.getElementById('csvFile').click()">
            <div class="text-3xl mb-2">📤</div>
            <p class="text-sm text-slate-300 font-semibold">Click or drag to upload CSV</p>
            <p class="text-xs text-slate-500 mt-1">First row should be headers (email, first_name, last_name, tags…)</p>
          </div>
          <input type="file" id="csvFile" name="csv_file" accept=".csv,.txt" class="hidden" onchange="document.querySelector('.csv-filename').textContent=this.files[0]?.name||''">
          <p class="text-xs text-indigo-400 mt-1 csv-filename"></p>
        </div>

        <!-- Paste -->
        <div x-show="mode==='paste'" x-cloak>
          <label class="block text-xs font-semibold text-slate-400 mb-2">Paste Emails <span class="text-slate-600">(one per line, or comma-separated: email,First,Last)</span></label>
          <textarea name="paste_data" class="form-input w-full font-mono text-xs" rows="10" placeholder="john@example.com,John,Doe&#10;jane@example.com&#10;bob@example.com,Bob,Smith"></textarea>
        </div>

        <!-- Column mapping (for CSV) -->
        <div x-show="mode==='csv'" class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Email Column Index *</label>
            <input type="number" name="col_email" value="0" min="0" class="form-input w-full text-sm">
            <p class="text-xs text-slate-600 mt-1">0 = first column</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">First Name Column</label>
            <input type="number" name="col_first" value="1" min="-1" class="form-input w-full text-sm">
            <p class="text-xs text-slate-600 mt-1">-1 = not present</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Last Name Column</label>
            <input type="number" name="col_last" value="2" min="-1" class="form-input w-full text-sm">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Tags Column</label>
            <input type="number" name="col_tags" value="-1" min="-1" class="form-input w-full text-sm">
          </div>
        </div>

        <!-- Common fields -->
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">Add to List (optional)</label>
          <select name="list_id" class="form-input w-full text-sm">
            <option value="">— Don't add to a list —</option>
            <?php foreach ($lists as $l): ?>
            <option value="<?= $l['id'] ?>"><?= e($l['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">Source Tag</label>
          <input type="text" name="source" value="import" class="form-input w-full text-sm" placeholder="import">
        </div>

        <button type="submit" class="btn btn-primary w-full justify-center">📥 Import Now</button>
      </form>
    </div>

    <!-- Tips -->
    <div class="space-y-4">
      <div class="card p-5 space-y-3">
        <h2 class="font-bold text-white">CSV Format Guide</h2>
        <div class="rounded-xl p-4 font-mono text-xs" style="background:#0b0f19;border:1px solid rgba(255,255,255,0.05)">
          <p class="text-emerald-400">email,first_name,last_name,tags</p>
          <p class="text-slate-400">john@example.com,John,Doe,vip,newsletter</p>
          <p class="text-slate-400">jane@example.com,Jane,Smith,</p>
          <p class="text-slate-400">bob@example.com,,,</p>
        </div>
        <ul class="text-xs text-slate-400 space-y-1.5">
          <li>✓ First row is treated as header (skipped)</li>
          <li>✓ Duplicate emails will update existing records</li>
          <li>✓ Invalid email addresses are skipped</li>
          <li>✓ UTF-8 encoding recommended</li>
          <li>✓ Max file size: <?= ini_get('upload_max_filesize') ?></li>
        </ul>
      </div>

      <div class="card p-5">
        <h2 class="font-bold text-white mb-3">API Auto-Import</h2>
        <p class="text-xs text-slate-400 mb-3">POST subscribers programmatically via the REST API:</p>
        <div class="rounded-xl p-3 font-mono text-xs" style="background:#0b0f19;border:1px solid rgba(255,255,255,0.05)">
          <p class="text-amber-400">POST <?= e(getSetting('app_url')) ?>/api/index.php</p>
          <p class="text-slate-500">Authorization: Bearer <?= e(getSetting('cron_secret')) ?></p>
          <p class="text-slate-500">Content-Type: application/json</p>
          <p class="text-emerald-400 mt-2">{"route":"subscribers","action":"create",</p>
          <p class="text-emerald-400"> "email":"user@example.com",</p>
          <p class="text-emerald-400"> "first_name":"Jane","list_id":1}</p>
        </div>
      </div>
    </div>
  </div>
</div>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>
