<?php
declare(strict_types=1);
$pageTitle = 'Automated RSS-to-Email';
require_once dirname(__DIR__, 3) . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feed_url'])) {
    if (!Auth::checkCsrf()) { flash('error', 'Invalid security token'); sc_redirect($_SERVER['REQUEST_URI']); }
    
    $url = filter_var($_POST['feed_url'], FILTER_VALIDATE_URL);
    $listId = (int)$_POST['list_id'];
    
    if ($url && $listId) {
        $db->prepare("INSERT INTO mod_rss_feeds (feed_url, list_id) VALUES (?, ?)")->execute([$url, $listId]);
        flash('success', 'RSS Feed automation created!');
    }
    sc_redirect('/modules/rss_to_email/pages/ui.php');
}

$feeds = $db->query("SELECT f.*, l.name as list_name FROM mod_rss_feeds f JOIN lists l ON f.list_id = l.id ORDER BY f.id DESC")->fetchAll();
$lists = $db->query("SELECT id, name FROM lists ORDER BY name")->fetchAll();
?>

<div class="flex items-center gap-4 mb-8">
  <h1 class="text-2xl font-bold text-white">Automated RSS-to-Email</h1>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
  <div class="card p-6 border-t-4 border-orange-500">
    <h2 class="text-lg font-bold text-white mb-4">Add RSS Automation</h2>
    <form method="post">
      <?= Auth::csrfField() ?>
      <div class="mb-4">
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">RSS / Atom Feed URL</label>
        <input type="url" name="feed_url" class="form-input w-full" placeholder="https://yourblog.com/feed" required>
      </div>
      <div class="mb-6">
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Target Subscriber List</label>
        <select name="list_id" class="form-input w-full" required>
            <option value="">-- Choose a list --</option>
            <?php foreach ($lists as $l): ?>
                <option value="<?= $l['id'] ?>"><?= e($l['name']) ?></option>
            <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary bg-orange-600 hover:bg-orange-500 w-full justify-center">Create Automation</button>
    </form>
  </div>
  
  <div class="card p-6 bg-orange-500/5 border border-orange-500/20">
      <h3 class="font-bold text-orange-400 mb-2">How it works</h3>
      <p class="text-slate-300 text-sm mb-4 leading-relaxed">
          The engine checks your RSS feed automatically via the background cron task. When it detects a new post, it instantly generates a beautiful HTML campaign featuring the post title, excerpt, and thumbnail, and schedules it to send to your selected list!
      </p>
  </div>
</div>

<div class="card p-0 overflow-hidden">
  <table class="data-table w-full">
    <thead>
      <tr>
        <th>Feed URL</th>
        <th>Target List</th>
        <th>Frequency</th>
        <th>Last Checked</th>
      </tr>
    </thead>
    <tbody>
      <?php if(!$feeds): ?>
      <tr><td colspan="4" class="text-center text-slate-500 py-8">No feeds configured.</td></tr>
      <?php endif; ?>
      <?php foreach ($feeds as $f): ?>
      <tr>
        <td class="font-mono text-xs text-orange-300"><?= e($f['feed_url']) ?></td>
        <td class="font-semibold text-slate-200"><?= e($f['list_name']) ?></td>
        <td><span class="badge badge-info"><?= e($f['frequency']) ?></span></td>
        <td class="text-slate-400 text-sm"><?= $f['last_checked_at'] ?: 'Never' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
