<?php
/**
 * admin/templates.php — Email template list
 * PHP 8.5+
 */
declare(strict_types=1);
$pageTitle = 'Email Templates';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) { $db->prepare("DELETE FROM templates WHERE id=?")->execute([$id]); flash('success','Template deleted.'); }
    sc_redirect('/admin/templates.php');
}

$templates = $db->query("SELECT * FROM templates ORDER BY updated_at DESC")->fetchAll();
?>
<div class="p-6 space-y-5">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-white">Email Templates</h1>
      <p class="text-sm text-slate-400 mt-0.5"><?= count($templates) ?> template<?= count($templates)!==1?'s':'' ?></p>
    </div>
    <a href="/admin/template_edit.php" class="btn btn-primary">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      New Template
    </a>
  </div>

  <?php if (empty($templates)): ?>
  <div class="card text-center py-16">
    <div class="text-5xl mb-4">🎨</div>
    <h3 class="text-white font-bold mb-2">No templates yet</h3>
    <p class="text-slate-400 text-sm mb-6">Create reusable email templates to speed up campaign creation.</p>
    <a href="/admin/template_edit.php" class="btn btn-primary">Create Template</a>
  </div>
  <?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($templates as $t): ?>
    <div class="card p-5 flex flex-col gap-3 group hover:border-indigo-500/20 transition-all">
      <div class="flex items-start justify-between">
        <div>
          <h3 class="font-bold text-white"><?= e($t['name']) ?></h3>
          <?php if ($t['subject']): ?><p class="text-xs text-slate-500 mt-0.5"><?= e(mb_strimwidth($t['subject'],0,50,'…')) ?></p><?php endif; ?>
        </div>
        <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
          <a href="/admin/template_edit.php?id=<?= $t['id'] ?>" class="p-1.5 rounded-lg hover:bg-white/10 text-slate-400 hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
          </a>
          <form method="post" onsubmit="return confirm('Delete template?')" style="display:inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $t['id'] ?>">
            <button class="p-1.5 rounded-lg hover:bg-red-500/20 text-slate-400 hover:text-red-400 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </form>
        </div>
      </div>
      <!-- HTML Preview -->
      <div class="rounded-lg overflow-hidden bg-white pointer-events-none" style="height:140px">
        <iframe srcdoc="<?= htmlspecialchars($t['body_html'],ENT_QUOTES,'UTF-8') ?>" class="w-full h-full pointer-events-none" style="transform:scale(0.5);transform-origin:top left;width:200%;height:200%" sandbox=""></iframe>
      </div>
      <div class="flex items-center justify-between text-xs text-slate-500">
        <span>Updated <?= timeAgo($t['updated_at']) ?></span>
        <a href="/admin/template_edit.php?id=<?= $t['id'] ?>" class="text-indigo-400 hover:text-indigo-300 font-semibold">Edit →</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
