<?php
/**
 * admin/lists.php — Mailing Lists management
 * PHP 8.5+
 */
declare(strict_types=1);
$pageTitle = 'Lists';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::checkCsrf()) { flash('error', 'Invalid security token (CSRF). Please try again.'); sc_redirect(<?php
/**
 * admin/lists.php — Mailing Lists management
 * PHP 8.5+
 */
declare(strict_types=1);
$pageTitle = 'Lists';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if ($action === 'create') {
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['desc'] ?? '');
        $type   = in_array($_POST['type']??'public',['public','private']) ? $_POST['type'] : 'public';
        $optin  = isset($_POST['double_optin']) ? 1 : 0;
        if ($name) {
            $db->prepare("INSERT INTO lists (name,description,type,optin_confirm) VALUES (?,?,?,?)")->execute([$name,$desc,$type,$optin]);
            logActivity($db,currentUserId(),'created','list',(int)$db->lastInsertId(),$name);
            flash('success','List created.');
        } else { flash('error','List name required.'); }
    }
    if ($action === 'delete' && $id > 0) {
        $db->prepare("DELETE FROM lists WHERE id=?")->execute([$id]);
        flash('success','List deleted.');
    }
    sc_redirect('/admin/lists.php');
}

$lists = $db->query("SELECT l.*, COUNT(sl.subscriber_id) as real_count FROM lists l LEFT JOIN subscriber_lists sl ON l.id=sl.list_id AND sl.status='confirmed' GROUP BY l.id ORDER BY l.created_at DESC")->fetchAll();
?>

<div class="p-6 space-y-5">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-white">Lists</h1>
      <p class="text-sm text-slate-400 mt-0.5"><?= count($lists) ?> list<?= count($lists)!==1?'s':'' ?></p>
    </div>
    <button onclick="document.getElementById('createListModal').showModal()" class="btn btn-primary">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      New List
    </button>
  </div>

  <!-- Lists grid -->
  <?php if (empty($lists)): ?>
  <div class="card text-center py-16">
    <div class="text-5xl mb-4">📋</div>
    <h3 class="text-white font-bold mb-2">No lists yet</h3>
    <p class="text-slate-400 text-sm mb-6">Create your first mailing list to organize subscribers.</p>
    <button onclick="document.getElementById('createListModal').showModal()" class="btn btn-primary">Create List</button>
  </div>
  <?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($lists as $list): ?>
    <div class="card p-5 flex flex-col gap-4 group">
      <div class="flex items-start justify-between">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <h3 class="font-bold text-white"><?= e($list['name']) ?></h3>
            <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $list['type']==='public'?'text-emerald-400 bg-emerald-900/30':'text-amber-400 bg-amber-900/30' ?>"><?= ucfirst($list['type']) ?></span>
          </div>
          <?php if ($list['description']): ?><p class="text-xs text-slate-500"><?= e(mb_strimwidth($list['description'],0,80,'…')) ?></p><?php endif; ?>
        </div>
        <form method="post" onsubmit="return confirm('Delete this list and all subscriber associations?')">
    <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $list['id'] ?>">
          <button class="p-1.5 rounded-lg hover:bg-red-500/20 text-slate-600 hover:text-red-400 transition-colors opacity-0 group-hover:opacity-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </button>
        </form>
      </div>
      <div class="flex items-end justify-between">
        <div>
          <p class="text-2xl font-black text-white"><?= number_format((int)$list['real_count']) ?></p>
          <p class="text-xs text-slate-500">active subscribers</p>
        </div>
        <div class="text-right space-y-1">
          <?php if ($list['optin_confirm']): ?><div class="text-xs text-indigo-400">✓ Double opt-in</div><?php endif; ?>
          <p class="text-xs text-slate-600">Created <?= date('M j, Y', strtotime($list['created_at'])) ?></p>
        </div>
      </div>
      <!-- Copy subscription URL -->
      <?php if ($list['type'] === 'public'): ?>
      <div class="pt-3 border-t border-white/5">
        <p class="text-xs text-slate-500 mb-1">Subscription URL</p>
        <div class="flex items-center gap-2">
          <input type="text" value="<?= e(getSetting('app_url').'/subscribe.php?list='.$list['id']) ?>" readonly class="text-xs bg-slate-900 border border-white/10 rounded-lg px-2 py-1.5 flex-1 text-slate-400 focus:outline-none">
          <button type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.textContent='✓'" class="text-xs text-indigo-400 hover:text-indigo-300 px-2 py-1.5 rounded-lg bg-indigo-900/20 border border-indigo-700/20">Copy</button>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Create List Dialog -->
<dialog id="createListModal" class="rounded-2xl border-0 p-0 shadow-2xl w-full max-w-md" style="background:#111827">
  <form method="post" class="p-6 space-y-4">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="create">
    <h2 class="text-lg font-bold text-white">Create New List</h2>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">List Name *</label>
      <input type="text" name="name" class="form-input w-full" placeholder="Main Newsletter" required autofocus>
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">Description</label>
      <textarea name="desc" class="form-input w-full" rows="2" placeholder="Brief description…"></textarea>
    </div>
    <div class="flex gap-4">
      <label class="flex items-center gap-2 cursor-pointer">
        <input type="radio" name="type" value="public" checked class="accent-indigo-500">
        <span class="text-sm text-slate-300">Public</span>
      </label>
      <label class="flex items-center gap-2 cursor-pointer">
        <input type="radio" name="type" value="private" class="accent-indigo-500">
        <span class="text-sm text-slate-300">Private</span>
      </label>
    </div>
    <label class="flex items-center gap-3 cursor-pointer">
      <input type="checkbox" name="double_optin" class="accent-indigo-500 w-4 h-4">
      <span class="text-sm text-slate-300">Enable Double Opt-in</span>
    </label>
    <div class="flex gap-2 pt-2">
      <button type="submit" class="btn btn-primary flex-1 justify-center">Create List</button>
      <button type="button" onclick="document.getElementById('createListModal').close()" class="btn btn-secondary flex-1 justify-center">Cancel</button>
    </div>
  </form>
</dialog>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
SERVER['REQUEST_URI']); }
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if ($action === 'create') {
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['desc'] ?? '');
        $type   = in_array($_POST['type']??'public',['public','private']) ? $_POST['type'] : 'public';
        $optin  = isset($_POST['double_optin']) ? 1 : 0;
        if ($name) {
            $db->prepare("INSERT INTO lists (name,description,type,optin_confirm) VALUES (?,?,?,?)")->execute([$name,$desc,$type,$optin]);
            logActivity($db,currentUserId(),'created','list',(int)$db->lastInsertId(),$name);
            flash('success','List created.');
        } else { flash('error','List name required.'); }
    }
    if ($action === 'delete' && $id > 0) {
        $db->prepare("DELETE FROM lists WHERE id=?")->execute([$id]);
        flash('success','List deleted.');
    }
    sc_redirect('/admin/lists.php');
}

$lists = $db->query("SELECT l.*, COUNT(sl.subscriber_id) as real_count FROM lists l LEFT JOIN subscriber_lists sl ON l.id=sl.list_id AND sl.status='confirmed' GROUP BY l.id ORDER BY l.created_at DESC")->fetchAll();
?>

<div class="p-6 space-y-5">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-white">Lists</h1>
      <p class="text-sm text-slate-400 mt-0.5"><?= count($lists) ?> list<?= count($lists)!==1?'s':'' ?></p>
    </div>
    <button onclick="document.getElementById('createListModal').showModal()" class="btn btn-primary">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      New List
    </button>
  </div>

  <!-- Lists grid -->
  <?php if (empty($lists)): ?>
  <div class="card text-center py-16">
    <div class="text-5xl mb-4">📋</div>
    <h3 class="text-white font-bold mb-2">No lists yet</h3>
    <p class="text-slate-400 text-sm mb-6">Create your first mailing list to organize subscribers.</p>
    <button onclick="document.getElementById('createListModal').showModal()" class="btn btn-primary">Create List</button>
  </div>
  <?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($lists as $list): ?>
    <div class="card p-5 flex flex-col gap-4 group">
      <div class="flex items-start justify-between">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <h3 class="font-bold text-white"><?= e($list['name']) ?></h3>
            <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $list['type']==='public'?'text-emerald-400 bg-emerald-900/30':'text-amber-400 bg-amber-900/30' ?>"><?= ucfirst($list['type']) ?></span>
          </div>
          <?php if ($list['description']): ?><p class="text-xs text-slate-500"><?= e(mb_strimwidth($list['description'],0,80,'…')) ?></p><?php endif; ?>
        </div>
        <form method="post" onsubmit="return confirm('Delete this list and all subscriber associations?')">
    <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $list['id'] ?>">
          <button class="p-1.5 rounded-lg hover:bg-red-500/20 text-slate-600 hover:text-red-400 transition-colors opacity-0 group-hover:opacity-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </button>
        </form>
      </div>
      <div class="flex items-end justify-between">
        <div>
          <p class="text-2xl font-black text-white"><?= number_format((int)$list['real_count']) ?></p>
          <p class="text-xs text-slate-500">active subscribers</p>
        </div>
        <div class="text-right space-y-1">
          <?php if ($list['optin_confirm']): ?><div class="text-xs text-indigo-400">✓ Double opt-in</div><?php endif; ?>
          <p class="text-xs text-slate-600">Created <?= date('M j, Y', strtotime($list['created_at'])) ?></p>
        </div>
      </div>
      <!-- Copy subscription URL -->
      <?php if ($list['type'] === 'public'): ?>
      <div class="pt-3 border-t border-white/5">
        <p class="text-xs text-slate-500 mb-1">Subscription URL</p>
        <div class="flex items-center gap-2">
          <input type="text" value="<?= e(getSetting('app_url').'/subscribe.php?list='.$list['id']) ?>" readonly class="text-xs bg-slate-900 border border-white/10 rounded-lg px-2 py-1.5 flex-1 text-slate-400 focus:outline-none">
          <button type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.textContent='✓'" class="text-xs text-indigo-400 hover:text-indigo-300 px-2 py-1.5 rounded-lg bg-indigo-900/20 border border-indigo-700/20">Copy</button>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Create List Dialog -->
<dialog id="createListModal" class="rounded-2xl border-0 p-0 shadow-2xl w-full max-w-md" style="background:#111827">
  <form method="post" class="p-6 space-y-4">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="create">
    <h2 class="text-lg font-bold text-white">Create New List</h2>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">List Name *</label>
      <input type="text" name="name" class="form-input w-full" placeholder="Main Newsletter" required autofocus>
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">Description</label>
      <textarea name="desc" class="form-input w-full" rows="2" placeholder="Brief description…"></textarea>
    </div>
    <div class="flex gap-4">
      <label class="flex items-center gap-2 cursor-pointer">
        <input type="radio" name="type" value="public" checked class="accent-indigo-500">
        <span class="text-sm text-slate-300">Public</span>
      </label>
      <label class="flex items-center gap-2 cursor-pointer">
        <input type="radio" name="type" value="private" class="accent-indigo-500">
        <span class="text-sm text-slate-300">Private</span>
      </label>
    </div>
    <label class="flex items-center gap-3 cursor-pointer">
      <input type="checkbox" name="double_optin" class="accent-indigo-500 w-4 h-4">
      <span class="text-sm text-slate-300">Enable Double Opt-in</span>
    </label>
    <div class="flex gap-2 pt-2">
      <button type="submit" class="btn btn-primary flex-1 justify-center">Create List</button>
      <button type="button" onclick="document.getElementById('createListModal').close()" class="btn btn-secondary flex-1 justify-center">Cancel</button>
    </div>
  </form>
</dialog>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
