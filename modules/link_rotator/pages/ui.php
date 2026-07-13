<?php
declare(strict_types=1);
$pageTitle = 'Link Rotator';
require_once dirname(__DIR__, 3) . '/includes/header.php';

$appUrl = getSetting('app_url', 'http://localhost');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['slug'])) {
    if (!Auth::checkCsrf()) { flash('error', 'Invalid security token'); sc_redirect($_SERVER['REQUEST_URI']); }
    $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($_POST['slug']));
    $dests = array_filter(array_map('trim', explode("\n", $_POST['destinations'])));
    
    if ($slug && $dests) {
        $db->prepare("INSERT INTO mod_link_rotators (slug, destinations) VALUES (?, ?) ON DUPLICATE KEY UPDATE destinations=VALUES(destinations)")
           ->execute([$slug, json_encode($dests)]);
        flash('success', 'Rotator link saved!');
    }
    sc_redirect('/modules/link_rotator/pages/ui.php');
}

$links = $db->query("SELECT * FROM mod_link_rotators ORDER BY id DESC")->fetchAll();
?>

<div class="flex items-center gap-4 mb-8">
  <h1 class="text-2xl font-bold text-white">Link Rotator & Cloaker</h1>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-1">
    <div class="card p-6 border-t-4 border-emerald-500">
      <h2 class="text-lg font-bold text-white mb-4">Create Rotator</h2>
      <form method="post">
        <?= Auth::csrfField() ?>
        <div class="mb-4">
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">Link Slug</label>
          <div class="flex">
            <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-slate-700 bg-slate-800 text-slate-400 text-xs">/go/</span>
            <input type="text" name="slug" class="form-input flex-1 rounded-l-none" placeholder="offer1" required>
          </div>
        </div>
        <div class="mb-6">
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">Destinations (1 per line)</label>
          <textarea name="destinations" class="form-input w-full h-32" placeholder="https://affiliate1.com/?ref=123&#10;https://affiliate2.com/?ref=123" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary bg-emerald-600 hover:bg-emerald-500 w-full justify-center">Save Link</button>
      </form>
    </div>
  </div>
  
  <div class="lg:col-span-2">
    <div class="card p-0 overflow-hidden">
      <table class="data-table w-full">
        <thead>
          <tr>
            <th>Cloaked URL</th>
            <th>Destinations</th>
            <th>Clicks</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!$links): ?>
          <tr><td colspan="3" class="text-center text-slate-500 py-8">No links created yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($links as $l): $dests = json_decode($l['destinations'], true); ?>
          <tr>
            <td>
                <div class="flex items-center gap-2">
                    <code class="text-emerald-400 bg-emerald-500/10 px-2 py-1 rounded text-xs select-all"><?= $appUrl ?>/go.php?s=<?= e($l['slug']) ?></code>
                </div>
            </td>
            <td>
                <div class="text-xs text-slate-400 max-w-xs truncate">
                    <?= count($dests) ?> link(s): <?= e($dests[0]) ?>...
                </div>
            </td>
            <td class="font-mono text-white"><?= number_format($l['clicks']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
