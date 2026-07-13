<?php
/**
 * admin/campaigns.php — Campaign list
 * PHP 8.5+
 */
declare(strict_types=1);
$pageTitle = 'Campaigns';
require_once __DIR__ . '/../includes/header.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::checkCsrf()) { flash('error', 'Invalid security token (CSRF). Please try again.'); sc_redirect(<?php
/**
 * admin/campaigns.php — Campaign list
 * PHP 8.5+
 */
declare(strict_types=1);
$pageTitle = 'Campaigns';
require_once __DIR__ . '/../includes/header.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        $db->prepare("DELETE FROM campaigns WHERE id = ?")->execute([$id]);
        logActivity($db, currentUserId(), 'deleted', 'campaign', $id);
        flash('success', 'Campaign deleted.');
    }
    if ($action === 'send' && $id > 0) {
        // Build email queue
        $db->exec("INSERT IGNORE INTO email_queue (campaign_id, subscriber_id)
            SELECT {$id}, s.id FROM subscribers s
            JOIN subscriber_lists sl ON s.id = sl.subscriber_id
            JOIN campaign_lists cl ON sl.list_id = cl.list_id
            WHERE cl.campaign_id = {$id} AND s.status = 'active' AND sl.status = 'confirmed'");
        $queuedCount = (int)$db->query("SELECT COUNT(*) FROM email_queue WHERE campaign_id={$id} AND status='pending'")->fetchColumn();
        if ($queuedCount > 0) {
            $db->prepare("UPDATE campaigns SET status='sending', started_at=COALESCE(started_at,NOW()), send_count = ? WHERE id = ? AND status IN ('draft','scheduled','sending')")->execute([$queuedCount, $id]);
            logActivity($db, currentUserId(), 'started sending', 'campaign', $id, "Queued {$queuedCount} emails");
            flash('success', "Sending started! {$queuedCount} emails queued.");
        } else {
            flash('error', 'No confirmed active recipients found for this campaign. Add subscribers to the selected list before sending.');
        }
    }
    if ($action === 'pause' && $id > 0) {
        $db->prepare("UPDATE campaigns SET status='paused' WHERE id = ? AND status='sending'")->execute([$id]);
        flash('success', 'Campaign paused.');
    }
    if ($action === 'resume' && $id > 0) {
        $db->prepare("UPDATE campaigns SET status='sending' WHERE id = ? AND status='paused'")->execute([$id]);
        flash('success', 'Campaign resumed.');
    }
    sc_redirect('/admin/campaigns.php');
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($statusFilter) { $where[] = "c.status = ?"; $params[] = $statusFilter; }
if ($search)       { $where[] = "c.name LIKE ?"; $params[] = "%{$search}%"; }
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)$db->prepare("SELECT COUNT(*) FROM campaigns c {$whereClause}")->execute($params) ? $db->prepare("SELECT COUNT(*) FROM campaigns c {$whereClause}")->execute($params) ? (function() use ($db, $whereClause, $params) {
    $st = $db->prepare("SELECT COUNT(*) FROM campaigns c {$whereClause}");
    $st->execute($params);
    return (int)$st->fetchColumn();
})() : 0 : 0;

// cleaner approach
$stCount = $db->prepare("SELECT COUNT(*) FROM campaigns c {$whereClause}");
$stCount->execute($params);
$total = (int)$stCount->fetchColumn();

$stList = $db->prepare("SELECT c.*, GROUP_CONCAT(l.name SEPARATOR ', ') as list_names FROM campaigns c LEFT JOIN campaign_lists cl ON c.id=cl.campaign_id LEFT JOIN lists l ON cl.list_id=l.id {$whereClause} GROUP BY c.id ORDER BY c.created_at DESC LIMIT {$perPage} OFFSET {$offset}");
$stList->execute($params);
$campaigns = $stList->fetchAll();

$totalPages = max(1, (int)ceil($total / $perPage));

$statusColors = [
    'draft'     => 'text-slate-400 bg-slate-800/80',
    'scheduled' => 'text-amber-400 bg-amber-900/30',
    'sending'   => 'text-blue-400 bg-blue-900/30',
    'sent'      => 'text-emerald-400 bg-emerald-900/30',
    'paused'    => 'text-orange-400 bg-orange-900/30',
    'cancelled' => 'text-red-400 bg-red-900/30',
];
?>

<div class="p-6 space-y-5">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-white">Campaigns</h1>
      <p class="text-sm text-slate-400 mt-0.5"><?= number_format($total) ?> campaign<?= $total !== 1 ? 's' : '' ?></p>
    </div>
    <a href="/admin/campaign_create.php" class="btn btn-primary">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      New Campaign
    </a>
  </div>

  <!-- Filters -->
  <div class="flex flex-wrap gap-3">
    <form method="get" class="flex gap-2 flex-1">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search campaigns…" class="form-input flex-1 max-w-xs text-sm px-3 py-2 rounded-lg" style="background:rgba(255,255,255,0.03);border:1px solid rgba(148,163,184,0.1);color:#e2e8f0;outline:none;">
      <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
      <button class="btn btn-secondary text-sm">Search</button>
    </form>
    <div class="flex gap-1.5">
      <?php foreach ([''=>'All','draft'=>'Draft','sending'=>'Sending','sent'=>'Sent','scheduled'=>'Scheduled'] as $v=>$l): ?>
      <a href="?status=<?= $v ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all <?= $statusFilter===$v ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-slate-200 bg-white/5' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Table -->
  <div class="card overflow-hidden">
    <?php if (empty($campaigns)): ?>
    <div class="text-center py-16">
      <div class="text-5xl mb-4">📧</div>
      <h3 class="text-white font-bold mb-2">No campaigns yet</h3>
      <p class="text-slate-400 text-sm mb-6">Create your first campaign to start reaching your audience.</p>
      <a href="/admin/campaign_create.php" class="btn btn-primary">Create Campaign</a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr class="border-b border-white/5">
          <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-5 py-3">Campaign</th>
          <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Status</th>
          <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Sent</th>
          <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Open%</th>
          <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Click%</th>
          <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-5 py-3">Actions</th>
        </tr></thead>
        <tbody class="divide-y divide-white/5">
        <?php foreach ($campaigns as $c): ?>
          <?php
          $openRate  = $c['send_count'] > 0 ? round($c['open_count']/$c['send_count']*100,1) : 0;
          $clickRate = $c['send_count'] > 0 ? round($c['click_count']/$c['send_count']*100,1) : 0;
          ?>
          <tr class="hover:bg-white/2 transition-colors group">
            <td class="px-5 py-3.5">
              <a href="/admin/campaign_view.php?id=<?= $c['id'] ?>" class="font-semibold text-slate-100 hover:text-indigo-300"><?= e($c['name']) ?></a>
              <p class="text-xs text-slate-500 mt-0.5"><?= e(mb_strimwidth($c['subject'], 0, 60, '…')) ?></p>
              <?php if ($c['list_names']): ?><p class="text-xs text-slate-600 mt-0.5">Lists: <?= e(mb_strimwidth($c['list_names'], 0, 50, '…')) ?></p><?php endif; ?>
            </td>
            <td class="px-4 py-3.5 text-center">
              <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $statusColors[$c['status']] ?? 'text-slate-400 bg-slate-800' ?>"><?= ucfirst(e($c['status'])) ?></span>
            </td>
            <td class="px-4 py-3.5 text-right text-slate-300 font-mono text-xs"><?= number_format((int)$c['send_count']) ?></td>
            <td class="px-4 py-3.5 text-right font-semibold <?= $openRate >= 30 ? 'text-emerald-400' : ($openRate >= 15 ? 'text-amber-400' : 'text-slate-400') ?>"><?= $c['send_count'] > 0 ? $openRate.'%' : '—' ?></td>
            <td class="px-4 py-3.5 text-right font-semibold <?= $clickRate >= 5 ? 'text-emerald-400' : ($clickRate >= 2 ? 'text-amber-400' : 'text-slate-400') ?>"><?= $c['send_count'] > 0 ? $clickRate.'%' : '—' ?></td>
            <td class="px-5 py-3.5 text-right">
              <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <a href="/admin/campaign_view.php?id=<?= $c['id'] ?>" class="p-1.5 rounded-lg hover:bg-white/10 text-slate-400 hover:text-white transition-colors" title="View">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7"/></svg>
                </a>
                <?php if (in_array($c['status'], ['draft','scheduled'])): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Start sending this campaign?')">
                  <input type="hidden" name="action" value="send">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button class="p-1.5 rounded-lg hover:bg-emerald-500/20 text-slate-400 hover:text-emerald-400 transition-colors" title="Send Now">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                  </button>
                </form>
                <?php elseif ($c['status'] === 'sending'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="pause">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button class="p-1.5 rounded-lg hover:bg-orange-500/20 text-slate-400 hover:text-orange-400 transition-colors" title="Pause">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </button>
                </form>
                <?php elseif ($c['status'] === 'paused'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="resume">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button class="p-1.5 rounded-lg hover:bg-blue-500/20 text-slate-400 hover:text-blue-400 transition-colors" title="Resume">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </button>
                </form>
                <?php endif; ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this campaign permanently?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button class="p-1.5 rounded-lg hover:bg-red-500/20 text-slate-400 hover:text-red-400 transition-colors" title="Delete">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-between px-5 py-3 border-t border-white/5">
      <p class="text-xs text-slate-500">Page <?= $page ?> of <?= $totalPages ?></p>
      <div class="flex gap-1">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?page=<?= $p ?><?= $statusFilter ? '&status='.urlencode($statusFilter) : '' ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-3 py-1 rounded-lg text-xs font-semibold transition-all <?= $p===$page ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white bg-white/5' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
SERVER['REQUEST_URI']); }
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        $db->prepare("DELETE FROM campaigns WHERE id = ?")->execute([$id]);
        logActivity($db, currentUserId(), 'deleted', 'campaign', $id);
        flash('success', 'Campaign deleted.');
    }
    if ($action === 'send' && $id > 0) {
        // Build email queue
        $db->exec("INSERT IGNORE INTO email_queue (campaign_id, subscriber_id)
            SELECT {$id}, s.id FROM subscribers s
            JOIN subscriber_lists sl ON s.id = sl.subscriber_id
            JOIN campaign_lists cl ON sl.list_id = cl.list_id
            WHERE cl.campaign_id = {$id} AND s.status = 'active' AND sl.status = 'confirmed'");
        $queuedCount = (int)$db->query("SELECT COUNT(*) FROM email_queue WHERE campaign_id={$id} AND status='pending'")->fetchColumn();
        if ($queuedCount > 0) {
            $db->prepare("UPDATE campaigns SET status='sending', started_at=COALESCE(started_at,NOW()), send_count = ? WHERE id = ? AND status IN ('draft','scheduled','sending')")->execute([$queuedCount, $id]);
            logActivity($db, currentUserId(), 'started sending', 'campaign', $id, "Queued {$queuedCount} emails");
            flash('success', "Sending started! {$queuedCount} emails queued.");
        } else {
            flash('error', 'No confirmed active recipients found for this campaign. Add subscribers to the selected list before sending.');
        }
    }
    if ($action === 'pause' && $id > 0) {
        $db->prepare("UPDATE campaigns SET status='paused' WHERE id = ? AND status='sending'")->execute([$id]);
        flash('success', 'Campaign paused.');
    }
    if ($action === 'resume' && $id > 0) {
        $db->prepare("UPDATE campaigns SET status='sending' WHERE id = ? AND status='paused'")->execute([$id]);
        flash('success', 'Campaign resumed.');
    }
    sc_redirect('/admin/campaigns.php');
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($statusFilter) { $where[] = "c.status = ?"; $params[] = $statusFilter; }
if ($search)       { $where[] = "c.name LIKE ?"; $params[] = "%{$search}%"; }
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)$db->prepare("SELECT COUNT(*) FROM campaigns c {$whereClause}")->execute($params) ? $db->prepare("SELECT COUNT(*) FROM campaigns c {$whereClause}")->execute($params) ? (function() use ($db, $whereClause, $params) {
    $st = $db->prepare("SELECT COUNT(*) FROM campaigns c {$whereClause}");
    $st->execute($params);
    return (int)$st->fetchColumn();
})() : 0 : 0;

// cleaner approach
$stCount = $db->prepare("SELECT COUNT(*) FROM campaigns c {$whereClause}");
$stCount->execute($params);
$total = (int)$stCount->fetchColumn();

$stList = $db->prepare("SELECT c.*, GROUP_CONCAT(l.name SEPARATOR ', ') as list_names FROM campaigns c LEFT JOIN campaign_lists cl ON c.id=cl.campaign_id LEFT JOIN lists l ON cl.list_id=l.id {$whereClause} GROUP BY c.id ORDER BY c.created_at DESC LIMIT {$perPage} OFFSET {$offset}");
$stList->execute($params);
$campaigns = $stList->fetchAll();

$totalPages = max(1, (int)ceil($total / $perPage));

$statusColors = [
    'draft'     => 'text-slate-400 bg-slate-800/80',
    'scheduled' => 'text-amber-400 bg-amber-900/30',
    'sending'   => 'text-blue-400 bg-blue-900/30',
    'sent'      => 'text-emerald-400 bg-emerald-900/30',
    'paused'    => 'text-orange-400 bg-orange-900/30',
    'cancelled' => 'text-red-400 bg-red-900/30',
];
?>

<div class="p-6 space-y-5">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-white">Campaigns</h1>
      <p class="text-sm text-slate-400 mt-0.5"><?= number_format($total) ?> campaign<?= $total !== 1 ? 's' : '' ?></p>
    </div>
    <a href="/admin/campaign_create.php" class="btn btn-primary">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      New Campaign
    </a>
  </div>

  <!-- Filters -->
  <div class="flex flex-wrap gap-3">
    <form method="get" class="flex gap-2 flex-1">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search campaigns…" class="form-input flex-1 max-w-xs text-sm px-3 py-2 rounded-lg" style="background:rgba(255,255,255,0.03);border:1px solid rgba(148,163,184,0.1);color:#e2e8f0;outline:none;">
      <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
      <button class="btn btn-secondary text-sm">Search</button>
    </form>
    <div class="flex gap-1.5">
      <?php foreach ([''=>'All','draft'=>'Draft','sending'=>'Sending','sent'=>'Sent','scheduled'=>'Scheduled'] as $v=>$l): ?>
      <a href="?status=<?= $v ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all <?= $statusFilter===$v ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-slate-200 bg-white/5' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Table -->
  <div class="card overflow-hidden">
    <?php if (empty($campaigns)): ?>
    <div class="text-center py-16">
      <div class="text-5xl mb-4">📧</div>
      <h3 class="text-white font-bold mb-2">No campaigns yet</h3>
      <p class="text-slate-400 text-sm mb-6">Create your first campaign to start reaching your audience.</p>
      <a href="/admin/campaign_create.php" class="btn btn-primary">Create Campaign</a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr class="border-b border-white/5">
          <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-5 py-3">Campaign</th>
          <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Status</th>
          <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Sent</th>
          <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Open%</th>
          <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Click%</th>
          <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-5 py-3">Actions</th>
        </tr></thead>
        <tbody class="divide-y divide-white/5">
        <?php foreach ($campaigns as $c): ?>
          <?php
          $openRate  = $c['send_count'] > 0 ? round($c['open_count']/$c['send_count']*100,1) : 0;
          $clickRate = $c['send_count'] > 0 ? round($c['click_count']/$c['send_count']*100,1) : 0;
          ?>
          <tr class="hover:bg-white/2 transition-colors group">
            <td class="px-5 py-3.5">
              <a href="/admin/campaign_view.php?id=<?= $c['id'] ?>" class="font-semibold text-slate-100 hover:text-indigo-300"><?= e($c['name']) ?></a>
              <p class="text-xs text-slate-500 mt-0.5"><?= e(mb_strimwidth($c['subject'], 0, 60, '…')) ?></p>
              <?php if ($c['list_names']): ?><p class="text-xs text-slate-600 mt-0.5">Lists: <?= e(mb_strimwidth($c['list_names'], 0, 50, '…')) ?></p><?php endif; ?>
            </td>
            <td class="px-4 py-3.5 text-center">
              <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $statusColors[$c['status']] ?? 'text-slate-400 bg-slate-800' ?>"><?= ucfirst(e($c['status'])) ?></span>
            </td>
            <td class="px-4 py-3.5 text-right text-slate-300 font-mono text-xs"><?= number_format((int)$c['send_count']) ?></td>
            <td class="px-4 py-3.5 text-right font-semibold <?= $openRate >= 30 ? 'text-emerald-400' : ($openRate >= 15 ? 'text-amber-400' : 'text-slate-400') ?>"><?= $c['send_count'] > 0 ? $openRate.'%' : '—' ?></td>
            <td class="px-4 py-3.5 text-right font-semibold <?= $clickRate >= 5 ? 'text-emerald-400' : ($clickRate >= 2 ? 'text-amber-400' : 'text-slate-400') ?>"><?= $c['send_count'] > 0 ? $clickRate.'%' : '—' ?></td>
            <td class="px-5 py-3.5 text-right">
              <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <a href="/admin/campaign_view.php?id=<?= $c['id'] ?>" class="p-1.5 rounded-lg hover:bg-white/10 text-slate-400 hover:text-white transition-colors" title="View">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7"/></svg>
                </a>
                <?php if (in_array($c['status'], ['draft','scheduled'])): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Start sending this campaign?')">
                  <input type="hidden" name="action" value="send">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button class="p-1.5 rounded-lg hover:bg-emerald-500/20 text-slate-400 hover:text-emerald-400 transition-colors" title="Send Now">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                  </button>
                </form>
                <?php elseif ($c['status'] === 'sending'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="pause">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button class="p-1.5 rounded-lg hover:bg-orange-500/20 text-slate-400 hover:text-orange-400 transition-colors" title="Pause">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </button>
                </form>
                <?php elseif ($c['status'] === 'paused'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="resume">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button class="p-1.5 rounded-lg hover:bg-blue-500/20 text-slate-400 hover:text-blue-400 transition-colors" title="Resume">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  </button>
                </form>
                <?php endif; ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this campaign permanently?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button class="p-1.5 rounded-lg hover:bg-red-500/20 text-slate-400 hover:text-red-400 transition-colors" title="Delete">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-between px-5 py-3 border-t border-white/5">
      <p class="text-xs text-slate-500">Page <?= $page ?> of <?= $totalPages ?></p>
      <div class="flex gap-1">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?page=<?= $p ?><?= $statusFilter ? '&status='.urlencode($statusFilter) : '' ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="px-3 py-1 rounded-lg text-xs font-semibold transition-all <?= $p===$page ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white bg-white/5' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
