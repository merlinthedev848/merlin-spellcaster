<?php
/**
 * admin/subscribers.php — Subscriber list management (already partially written)
 * This is the full version. PHP 8.5+
 */
declare(strict_types=1);
$pageTitle = 'Subscribers';
require_once __DIR__ . '/../includes/header.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        $db->prepare("DELETE FROM subscribers WHERE id=?")->execute([$id]);
        flash('success','Subscriber deleted.');
    }
    if ($action === 'unsubscribe' && $id > 0) {
        $db->prepare("UPDATE subscribers SET status='unsubscribed' WHERE id=?")->execute([$id]);
        flash('success','Subscriber unsubscribed.');
    }
    if ($action === 'resubscribe' && $id > 0) {
        $db->prepare("UPDATE subscribers SET status='active' WHERE id=?")->execute([$id]);
        flash('success','Subscriber reactivated.');
    }
    if ($action === 'bulk_delete') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        if ($ids) {
            $in = implode(',', $ids);
            $db->exec("DELETE FROM subscribers WHERE id IN ({$in})");
            flash('success', count($ids) . ' subscribers deleted.');
        }
    }
    if ($action === 'bulk_unsub') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        if ($ids) {
            $in = implode(',', $ids);
            $db->exec("UPDATE subscribers SET status='unsubscribed' WHERE id IN ({$in})");
            flash('success', count($ids) . ' subscribers unsubscribed.');
        }
    }
    sc_redirect('/admin/subscribers.php?' . http_build_query(array_filter([
        'q'      => $_GET['q'] ?? '',
        'status' => $_GET['status'] ?? '',
        'list'   => $_GET['list'] ?? '',
        'page'   => $_GET['page'] ?? '',
    ])));
}

// Filters
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$listId = (int)($_GET['list'] ?? 0);
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($search) { $where[] = "(s.email LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)"; array_push($params, "%{$search}%", "%{$search}%", "%{$search}%"); }
if ($status) { $where[] = "s.status = ?"; $params[] = $status; }
if ($listId) { $where[] = "sl.list_id = ?"; $params[] = $listId; }
$join   = $listId ? "LEFT JOIN subscriber_lists sl ON s.id=sl.subscriber_id" : "";
$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

$stCount = $db->prepare("SELECT COUNT(DISTINCT s.id) FROM subscribers s {$join} {$whereSQL}");
$stCount->execute($params);
$total = (int)$stCount->fetchColumn();

$stRows = $db->prepare("SELECT DISTINCT s.*, (SELECT GROUP_CONCAT(l.name SEPARATOR ', ') FROM subscriber_lists sl2 JOIN lists l ON l.id=sl2.list_id WHERE sl2.subscriber_id=s.id) as list_names FROM subscribers s {$join} {$whereSQL} ORDER BY s.created_at DESC LIMIT {$perPage} OFFSET {$offset}");
$stRows->execute($params);
$subscribers = $stRows->fetchAll();

$totalPages = max(1, (int)ceil($total / $perPage));
$lists      = $db->query("SELECT id,name FROM lists ORDER BY name")->fetchAll();

$statusColors = [
    'active'       => 'text-emerald-400 bg-emerald-900/30',
    'unsubscribed' => 'text-amber-400 bg-amber-900/30',
    'bounced'      => 'text-red-400 bg-red-900/30',
    'complained'   => 'text-violet-400 bg-violet-900/30',
];
?>

<div class="p-6 space-y-5" x-data="{selected:[],selectAll:false,bulkOpen:false}" x-init="$watch('selectAll',v=>{selected=v?<?= json_encode(array_column($subscribers,'id')) ?>:[]})">

  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-white">Subscribers</h1>
      <p class="text-sm text-slate-400 mt-0.5"><?= number_format($total) ?> total</p>
    </div>
    <div class="flex gap-2">
      <a href="/admin/imports.php" class="btn btn-secondary">📥 Import</a>
      <button onclick="exportCsv()" class="btn btn-secondary">📤 Export CSV</button>
    </div>
  </div>

  <!-- Filters -->
  <div class="flex flex-wrap gap-2 items-center">
    <form method="get" class="flex gap-2">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search email, name…" class="form-input-sm">
      <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
      <?php if ($listId): ?><input type="hidden" name="list" value="<?= $listId ?>"><?php endif; ?>
      <button class="btn btn-secondary text-xs">Search</button>
      <?php if ($search||$status||$listId): ?><a href="/admin/subscribers.php" class="btn btn-secondary text-xs">✕ Clear</a><?php endif; ?>
    </form>
    <div class="flex gap-1">
      <?php foreach ([''=>'All','active'=>'Active','unsubscribed'=>'Unsub','bounced'=>'Bounced'] as $v=>$l): ?>
      <a href="?status=<?= $v ?><?= $search?'&q='.urlencode($search):'' ?><?= $listId?'&list='.$listId:'' ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all <?= $status===$v?'bg-indigo-600 text-white':'text-slate-400 hover:text-slate-200 bg-white/5' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
    <select onchange="location='?list='+this.value+'<?= $search?'&q='.urlencode($search):'' ?><?= $status?'&status='.urlencode($status):'' ?>'" class="form-input-sm text-xs">
      <option value="">All Lists</option>
      <?php foreach ($lists as $l): ?>
      <option value="<?= $l['id'] ?>" <?= $listId===$l['id']?'selected':'' ?>><?= e($l['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Bulk actions -->
  <div x-show="selected.length > 0" x-cloak class="flex items-center gap-3 p-3 rounded-xl" style="background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.2)">
    <span class="text-sm text-indigo-300 font-semibold" x-text="selected.length+' selected'"></span>
    <form method="post" style="display:inline" onsubmit="document.getElementById('bulkIds').value=selected.join(','); return confirm('Unsubscribe selected?')">
      <input type="hidden" name="action" value="bulk_unsub">
      <input type="hidden" id="bulkIds" name="ids" :value="selected.join(',')">
      <button class="btn btn-secondary text-xs py-1 px-3">Unsubscribe</button>
    </form>
    <form method="post" style="display:inline" onsubmit="document.getElementById('bulkDelIds').value=selected.join(','); return confirm('DELETE selected permanently?')">
      <input type="hidden" name="action" value="bulk_delete">
      <input type="hidden" id="bulkDelIds" name="ids" :value="selected.join(',')">
      <button class="btn text-xs py-1 px-3" style="background:rgba(239,68,68,0.1);color:#f87171;border:1px solid rgba(239,68,68,0.2)">Delete</button>
    </form>
  </div>

  <!-- Table -->
  <div class="card overflow-hidden">
    <?php if (empty($subscribers)): ?>
    <div class="text-center py-16">
      <div class="text-5xl mb-4">👤</div>
      <h3 class="text-white font-bold mb-2">No subscribers found</h3>
      <p class="text-slate-400 text-sm mb-6">Import subscribers or add a subscription form to your website.</p>
      <a href="/admin/imports.php" class="btn btn-primary">Import Subscribers</a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm" id="subsTable">
        <thead><tr class="border-b border-white/5">
          <th class="px-4 py-3 w-8"><input type="checkbox" x-model="selectAll" class="accent-indigo-500 w-4 h-4"></th>
          <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Subscriber</th>
          <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Status</th>
          <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Lists</th>
          <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Source</th>
          <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Joined</th>
          <th class="px-4 py-3 w-20"></th>
        </tr></thead>
        <tbody class="divide-y divide-white/5">
        <?php foreach ($subscribers as $s): ?>
        <tr class="hover:bg-white/2 transition-colors group">
          <td class="px-4 py-3">
            <input type="checkbox" :value="<?= $s['id'] ?>" x-model="selected" class="accent-indigo-500 w-4 h-4">
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
                <?= strtoupper(substr($s['first_name'] ?: $s['email'], 0, 1)) ?>
              </div>
              <div>
                <a href="/admin/subscriber_view.php?id=<?= $s['id'] ?>" class="font-semibold text-slate-200 hover:text-indigo-300">
                  <?= e(trim($s['first_name'].' '.$s['last_name']) ?: '(no name)') ?>
                </a>
                <p class="text-xs text-slate-500"><?= e($s['email']) ?></p>
              </div>
            </div>
          </td>
          <td class="px-4 py-3 text-center">
            <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $statusColors[$s['status']] ?? 'text-slate-400 bg-slate-800' ?>"><?= ucfirst(e($s['status'])) ?></span>
          </td>
          <td class="px-4 py-3 text-xs text-slate-500"><?= e(mb_strimwidth($s['list_names']??'',0,40,'…')) ?: '—' ?></td>
          <td class="px-4 py-3 text-xs text-slate-500"><?= e($s['source']) ?></td>
          <td class="px-4 py-3 text-right text-xs text-slate-500"><?= date('M j, Y', strtotime($s['created_at'])) ?></td>
          <td class="px-4 py-3">
            <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
              <a href="/admin/subscriber_view.php?id=<?= $s['id'] ?>" class="p-1.5 rounded-lg hover:bg-white/10 text-slate-400 hover:text-white transition-colors" title="View">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7"/></svg>
              </a>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this subscriber?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <button class="p-1.5 rounded-lg hover:bg-red-500/20 text-slate-400 hover:text-red-400 transition-colors" title="Delete">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-between px-5 py-3 border-t border-white/5">
      <p class="text-xs text-slate-500">Showing <?= $offset+1 ?> – <?= min($offset+$perPage,$total) ?> of <?= number_format($total) ?></p>
      <div class="flex gap-1">
        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
        <a href="?page=<?= $p ?><?= $status?'&status='.urlencode($status):'' ?><?= $search?'&q='.urlencode($search):'' ?><?= $listId?'&list='.$listId:'' ?>" class="px-3 py-1 rounded-lg text-xs font-semibold transition-all <?= $p===$page?'bg-indigo-600 text-white':'text-slate-400 hover:text-white bg-white/5' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<style>
.form-input-sm { background:rgba(255,255,255,0.03);border:1px solid rgba(148,163,184,0.1);color:#e2e8f0;border-radius:8px;padding:6px 10px;font-size:13px;outline:none; }
.form-input-sm:focus { border-color:#6366f1; }
</style>

<script>
function exportCsv() {
  window.location.href = '/api/index.php?route=subscribers&action=export&format=csv&secret=<?= e(getSetting('cron_secret')) ?>';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
