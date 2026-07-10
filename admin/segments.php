<?php
declare(strict_types=1);
$pageTitle = 'Segments';
require_once dirname(__DIR__) . '/includes/header.php';

$statuses = $db->query("SELECT status, COUNT(*) AS total FROM subscribers GROUP BY status ORDER BY status")->fetchAll();
$tagRows = $db->query("SELECT tags FROM subscribers WHERE tags IS NOT NULL AND tags <> '' LIMIT 500")->fetchAll();
$tags = [];
foreach ($tagRows as $row) {
    foreach (array_filter(array_map('trim', explode(',', (string)$row['tags']))) as $tag) {
        $tags[$tag] = ($tags[$tag] ?? 0) + 1;
    }
}
arsort($tags);
?>

<div class="flex items-center justify-between mb-8">
  <div>
    <h1 class="text-2xl font-bold text-white">Segments</h1>
    <p class="text-slate-500 text-sm mt-1">Use subscriber status and tags to target campaigns.</p>
  </div>
  <a href="/admin/subscribers.php" class="btn btn-primary">View Subscribers</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <div class="card p-6">
    <h2 class="text-lg font-bold text-white mb-4">By Status</h2>
    <div class="space-y-3">
      <?php foreach ($statuses as $status): ?>
        <a href="/admin/subscribers.php?status=<?= e((string)$status['status']) ?>" class="flex items-center justify-between p-3 rounded-lg bg-slate-950/50 hover:bg-slate-900 transition-colors">
          <span class="text-slate-200 font-medium"><?= e(ucfirst((string)$status['status'])) ?></span>
          <span class="badge badge-active"><?= (int)$status['total'] ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (!$statuses): ?>
        <p class="text-slate-500 text-sm">No subscribers yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card p-6">
    <h2 class="text-lg font-bold text-white mb-4">Top Tags</h2>
    <div class="flex flex-wrap gap-2">
      <?php foreach (array_slice($tags, 0, 40, true) as $tag => $count): ?>
        <a href="/admin/subscribers.php?q=<?= urlencode($tag) ?>" class="badge badge-draft hover:border-indigo-500 hover:text-indigo-300">
          <?= e($tag) ?> <span class="text-slate-500"><?= (int)$count ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (!$tags): ?>
        <p class="text-slate-500 text-sm">No tags found yet.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
