<?php
/**
 * admin/subscriber_view.php — Single subscriber profile
 * PHP 8.5+
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/core/Auth.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { flash('error','Invalid subscriber.'); sc_redirect('/admin/subscribers.php'); }
$pageTitle = 'Subscriber Profile';
require_once __DIR__ . '/../includes/header.php';

$st = $db->prepare("SELECT * FROM subscribers WHERE id=?");
$st->execute([$id]);
$sub = $st->fetch();
if (!$sub) { flash('error','Subscriber not found.'); sc_redirect('/admin/subscribers.php'); }

// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    if (!Auth::checkCsrf()) { flash('error', 'Invalid security token (CSRF). Please try again.'); sc_redirect(<?php
/**
 * admin/subscriber_view.php — Single subscriber profile
 * PHP 8.5+
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/core/Auth.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { flash('error','Invalid subscriber.'); sc_redirect('/admin/subscribers.php'); }
$pageTitle = 'Subscriber Profile';
require_once __DIR__ . '/../includes/header.php';

$st = $db->prepare("SELECT * FROM subscribers WHERE id=?");
$st->execute([$id]);
$sub = $st->fetch();
if (!$sub) { flash('error','Subscriber not found.'); sc_redirect('/admin/subscribers.php'); }

// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $db->prepare("UPDATE subscribers SET first_name=?,last_name=?,phone=?,status=?,updated_at=NOW() WHERE id=?")
       ->execute([trim($_POST['first_name']??''), trim($_POST['last_name']??''), trim($_POST['phone']??''), $_POST['status']??'active', $id]);
    flash('success','Subscriber updated.');
    sc_redirect('/admin/subscriber_view.php?id='.$id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        $deleted = deleteSubscriber($db, $id);
        flash($deleted ? 'success' : 'error', $deleted ? 'Subscriber deleted.' : 'Subscriber not found.');
        sc_redirect('/admin/subscribers.php');
    } catch (Throwable $e) {
        flash('error', 'Could not delete subscriber: ' . $e->getMessage());
        sc_redirect('/admin/subscriber_view.php?id='.$id);
    }
}

$subLists   = (function() use ($db,$id) { $s=$db->prepare("SELECT l.*,sl.status as sub_status,sl.subscribed_at FROM subscriber_lists sl JOIN lists l ON l.id=sl.list_id WHERE sl.subscriber_id=?"); $s->execute([$id]); return $s->fetchAll(); })();
$opens      = (function() use ($db,$id) { $s=$db->prepare("SELECT o.*,c.name as campaign_name FROM campaign_opens o JOIN campaigns c ON c.id=o.campaign_id WHERE o.subscriber_id=? ORDER BY o.opened_at DESC LIMIT 10"); $s->execute([$id]); return $s->fetchAll(); })();
$clicks     = (function() use ($db,$id) { $s=$db->prepare("SELECT ck.*,c.name as campaign_name FROM campaign_clicks ck JOIN campaigns c ON c.id=ck.campaign_id WHERE ck.subscriber_id=? ORDER BY ck.clicked_at DESC LIMIT 10"); $s->execute([$id]); return $s->fetchAll(); })();
$attrs      = json_decode($sub['attributes'] ?? '{}', true) ?: [];
$tags       = json_decode($sub['tags'] ?? '[]', true) ?: [];

$statusColors = ['active'=>'text-emerald-400 bg-emerald-900/30','unsubscribed'=>'text-amber-400 bg-amber-900/30','bounced'=>'text-red-400 bg-red-900/30','complained'=>'text-violet-400 bg-violet-900/30'];
?>

<div class="p-6 space-y-5">
  <div class="flex items-center gap-4">
    <a href="/admin/subscribers.php" class="text-slate-400 hover:text-white">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    </a>
    <h1 class="text-2xl font-bold text-white">Subscriber Profile</h1>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    <!-- Left: Profile -->
    <div class="xl:col-span-1 space-y-4">
      <div class="card p-6 text-center">
        <div class="w-20 h-20 rounded-full flex items-center justify-center text-2xl font-black text-white mx-auto mb-4" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
          <?= strtoupper(substr($sub['first_name']?:$sub['email'],0,1)) ?>
        </div>
        <h2 class="text-xl font-bold text-white mb-1"><?= e(trim($sub['first_name'].' '.$sub['last_name']) ?: '(no name)') ?></h2>
        <p class="text-sm text-slate-400 mb-3"><?= e($sub['email']) ?></p>
        <span class="px-3 py-1 rounded-full text-sm font-bold <?= $statusColors[$sub['status']] ?? 'text-slate-400 bg-slate-800' ?>"><?= ucfirst(e($sub['status'])) ?></span>
        <div class="mt-4 pt-4 border-t border-white/5 text-xs text-slate-500 space-y-1">
          <div>Joined: <?= date('M j, Y', strtotime($sub['created_at'])) ?></div>
          <div>Source: <?= e($sub['source']) ?></div>
          <?php if ($sub['ip_address']): ?><div>IP: <?= e($sub['ip_address']) ?></div><?php endif; ?>
        </div>
      </div>

      <!-- Tags -->
      <?php if ($tags): ?>
      <div class="card p-5">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">Tags</h3>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($tags as $tag): ?>
          <span class="px-2.5 py-1 rounded-full text-xs font-semibold text-indigo-300 bg-indigo-900/30 border border-indigo-700/30"><?= e($tag) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Lists -->
      <div class="card p-5">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">List Memberships</h3>
        <?php if (empty($subLists)): ?>
        <p class="text-xs text-slate-500">Not on any lists.</p>
        <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($subLists as $sl): ?>
          <div class="flex justify-between items-center">
            <span class="text-sm text-slate-200"><?= e($sl['name']) ?></span>
            <span class="text-xs text-slate-500 <?= $sl['sub_status']==='confirmed'?'text-emerald-400':'' ?>"><?= e($sl['sub_status']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="card p-5">
        <h3 class="text-sm font-bold text-red-400 uppercase tracking-wider mb-3">Danger Zone</h3>
        <form method="post" onsubmit="return confirm('Delete this subscriber permanently?')">
          <input type="hidden" name="action" value="delete">
          <button class="btn btn-danger w-full justify-center" type="submit">Delete Subscriber</button>
        </form>
      </div>
    </div>

    <!-- Right: Edit form + activity -->
    <div class="xl:col-span-2 space-y-4">
      <div class="card p-6">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4">Edit Details</h3>
        <form method="post" class="grid grid-cols-2 gap-4">
          <input type="hidden" name="action" value="update">
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">First Name</label>
            <input type="text" name="first_name" value="<?= e($sub['first_name']) ?>" class="form-input w-full">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Last Name</label>
            <input type="text" name="last_name" value="<?= e($sub['last_name']) ?>" class="form-input w-full">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Email (read-only)</label>
            <input type="email" value="<?= e($sub['email']) ?>" class="form-input w-full opacity-50" disabled>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Phone</label>
            <input type="text" name="phone" value="<?= e($sub['phone']) ?>" class="form-input w-full">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Status</label>
            <select name="status" class="form-input w-full">
              <?php foreach (['active','unsubscribed','bounced','complained'] as $st): ?>
              <option value="<?= $st ?>" <?= $sub['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="flex items-end">
            <button type="submit" class="btn btn-primary w-full justify-center">Save Changes</button>
          </div>
        </form>
      </div>

      <!-- Campaign opens -->
      <div class="card p-5">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">Recent Opens</h3>
        <?php if (empty($opens)): ?>
        <p class="text-xs text-slate-500">No opens recorded.</p>
        <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($opens as $o): ?>
          <div class="flex justify-between text-xs py-1 border-b border-white/5 last:border-0">
            <span class="text-slate-200"><?= e(mb_strimwidth($o['campaign_name'],0,40,'…')) ?></span>
            <span class="text-slate-500"><?= timeAgo($o['opened_at']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Clicks -->
      <div class="card p-5">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">Recent Clicks</h3>
        <?php if (empty($clicks)): ?>
        <p class="text-xs text-slate-500">No clicks recorded.</p>
        <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($clicks as $ck): ?>
          <div class="flex justify-between text-xs py-1 border-b border-white/5 last:border-0">
            <a href="<?= e($ck['url']) ?>" target="_blank" class="text-indigo-400 hover:text-indigo-300 truncate max-w-xs"><?= e(mb_strimwidth($ck['url']??'',0,50,'…')) ?></a>
            <span class="text-slate-500 ml-2"><?= timeAgo($ck['clicked_at']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>
SERVER['REQUEST_URI']); }
    $db->prepare("UPDATE subscribers SET first_name=?,last_name=?,phone=?,status=?,updated_at=NOW() WHERE id=?")
       ->execute([trim($_POST['first_name']??''), trim($_POST['last_name']??''), trim($_POST['phone']??''), $_POST['status']??'active', $id]);
    flash('success','Subscriber updated.');
    sc_redirect('/admin/subscriber_view.php?id='.$id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!Auth::checkCsrf()) { flash('error', 'Invalid security token (CSRF). Please try again.'); sc_redirect(<?php
/**
 * admin/subscriber_view.php — Single subscriber profile
 * PHP 8.5+
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/core/Auth.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { flash('error','Invalid subscriber.'); sc_redirect('/admin/subscribers.php'); }
$pageTitle = 'Subscriber Profile';
require_once __DIR__ . '/../includes/header.php';

$st = $db->prepare("SELECT * FROM subscribers WHERE id=?");
$st->execute([$id]);
$sub = $st->fetch();
if (!$sub) { flash('error','Subscriber not found.'); sc_redirect('/admin/subscribers.php'); }

// Handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $db->prepare("UPDATE subscribers SET first_name=?,last_name=?,phone=?,status=?,updated_at=NOW() WHERE id=?")
       ->execute([trim($_POST['first_name']??''), trim($_POST['last_name']??''), trim($_POST['phone']??''), $_POST['status']??'active', $id]);
    flash('success','Subscriber updated.');
    sc_redirect('/admin/subscriber_view.php?id='.$id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        $deleted = deleteSubscriber($db, $id);
        flash($deleted ? 'success' : 'error', $deleted ? 'Subscriber deleted.' : 'Subscriber not found.');
        sc_redirect('/admin/subscribers.php');
    } catch (Throwable $e) {
        flash('error', 'Could not delete subscriber: ' . $e->getMessage());
        sc_redirect('/admin/subscriber_view.php?id='.$id);
    }
}

$subLists   = (function() use ($db,$id) { $s=$db->prepare("SELECT l.*,sl.status as sub_status,sl.subscribed_at FROM subscriber_lists sl JOIN lists l ON l.id=sl.list_id WHERE sl.subscriber_id=?"); $s->execute([$id]); return $s->fetchAll(); })();
$opens      = (function() use ($db,$id) { $s=$db->prepare("SELECT o.*,c.name as campaign_name FROM campaign_opens o JOIN campaigns c ON c.id=o.campaign_id WHERE o.subscriber_id=? ORDER BY o.opened_at DESC LIMIT 10"); $s->execute([$id]); return $s->fetchAll(); })();
$clicks     = (function() use ($db,$id) { $s=$db->prepare("SELECT ck.*,c.name as campaign_name FROM campaign_clicks ck JOIN campaigns c ON c.id=ck.campaign_id WHERE ck.subscriber_id=? ORDER BY ck.clicked_at DESC LIMIT 10"); $s->execute([$id]); return $s->fetchAll(); })();
$attrs      = json_decode($sub['attributes'] ?? '{}', true) ?: [];
$tags       = json_decode($sub['tags'] ?? '[]', true) ?: [];

$statusColors = ['active'=>'text-emerald-400 bg-emerald-900/30','unsubscribed'=>'text-amber-400 bg-amber-900/30','bounced'=>'text-red-400 bg-red-900/30','complained'=>'text-violet-400 bg-violet-900/30'];
?>

<div class="p-6 space-y-5">
  <div class="flex items-center gap-4">
    <a href="/admin/subscribers.php" class="text-slate-400 hover:text-white">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    </a>
    <h1 class="text-2xl font-bold text-white">Subscriber Profile</h1>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    <!-- Left: Profile -->
    <div class="xl:col-span-1 space-y-4">
      <div class="card p-6 text-center">
        <div class="w-20 h-20 rounded-full flex items-center justify-center text-2xl font-black text-white mx-auto mb-4" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
          <?= strtoupper(substr($sub['first_name']?:$sub['email'],0,1)) ?>
        </div>
        <h2 class="text-xl font-bold text-white mb-1"><?= e(trim($sub['first_name'].' '.$sub['last_name']) ?: '(no name)') ?></h2>
        <p class="text-sm text-slate-400 mb-3"><?= e($sub['email']) ?></p>
        <span class="px-3 py-1 rounded-full text-sm font-bold <?= $statusColors[$sub['status']] ?? 'text-slate-400 bg-slate-800' ?>"><?= ucfirst(e($sub['status'])) ?></span>
        <div class="mt-4 pt-4 border-t border-white/5 text-xs text-slate-500 space-y-1">
          <div>Joined: <?= date('M j, Y', strtotime($sub['created_at'])) ?></div>
          <div>Source: <?= e($sub['source']) ?></div>
          <?php if ($sub['ip_address']): ?><div>IP: <?= e($sub['ip_address']) ?></div><?php endif; ?>
        </div>
      </div>

      <!-- Tags -->
      <?php if ($tags): ?>
      <div class="card p-5">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">Tags</h3>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($tags as $tag): ?>
          <span class="px-2.5 py-1 rounded-full text-xs font-semibold text-indigo-300 bg-indigo-900/30 border border-indigo-700/30"><?= e($tag) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Lists -->
      <div class="card p-5">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">List Memberships</h3>
        <?php if (empty($subLists)): ?>
        <p class="text-xs text-slate-500">Not on any lists.</p>
        <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($subLists as $sl): ?>
          <div class="flex justify-between items-center">
            <span class="text-sm text-slate-200"><?= e($sl['name']) ?></span>
            <span class="text-xs text-slate-500 <?= $sl['sub_status']==='confirmed'?'text-emerald-400':'' ?>"><?= e($sl['sub_status']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="card p-5">
        <h3 class="text-sm font-bold text-red-400 uppercase tracking-wider mb-3">Danger Zone</h3>
        <form method="post" onsubmit="return confirm('Delete this subscriber permanently?')">
          <input type="hidden" name="action" value="delete">
          <button class="btn btn-danger w-full justify-center" type="submit">Delete Subscriber</button>
        </form>
      </div>
    </div>

    <!-- Right: Edit form + activity -->
    <div class="xl:col-span-2 space-y-4">
      <div class="card p-6">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4">Edit Details</h3>
        <form method="post" class="grid grid-cols-2 gap-4">
          <input type="hidden" name="action" value="update">
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">First Name</label>
            <input type="text" name="first_name" value="<?= e($sub['first_name']) ?>" class="form-input w-full">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Last Name</label>
            <input type="text" name="last_name" value="<?= e($sub['last_name']) ?>" class="form-input w-full">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Email (read-only)</label>
            <input type="email" value="<?= e($sub['email']) ?>" class="form-input w-full opacity-50" disabled>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Phone</label>
            <input type="text" name="phone" value="<?= e($sub['phone']) ?>" class="form-input w-full">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Status</label>
            <select name="status" class="form-input w-full">
              <?php foreach (['active','unsubscribed','bounced','complained'] as $st): ?>
              <option value="<?= $st ?>" <?= $sub['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="flex items-end">
            <button type="submit" class="btn btn-primary w-full justify-center">Save Changes</button>
          </div>
        </form>
      </div>

      <!-- Campaign opens -->
      <div class="card p-5">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">Recent Opens</h3>
        <?php if (empty($opens)): ?>
        <p class="text-xs text-slate-500">No opens recorded.</p>
        <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($opens as $o): ?>
          <div class="flex justify-between text-xs py-1 border-b border-white/5 last:border-0">
            <span class="text-slate-200"><?= e(mb_strimwidth($o['campaign_name'],0,40,'…')) ?></span>
            <span class="text-slate-500"><?= timeAgo($o['opened_at']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Clicks -->
      <div class="card p-5">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">Recent Clicks</h3>
        <?php if (empty($clicks)): ?>
        <p class="text-xs text-slate-500">No clicks recorded.</p>
        <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($clicks as $ck): ?>
          <div class="flex justify-between text-xs py-1 border-b border-white/5 last:border-0">
            <a href="<?= e($ck['url']) ?>" target="_blank" class="text-indigo-400 hover:text-indigo-300 truncate max-w-xs"><?= e(mb_strimwidth($ck['url']??'',0,50,'…')) ?></a>
            <span class="text-slate-500 ml-2"><?= timeAgo($ck['clicked_at']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>
SERVER['REQUEST_URI']); }
    try {
        $deleted = deleteSubscriber($db, $id);
        flash($deleted ? 'success' : 'error', $deleted ? 'Subscriber deleted.' : 'Subscriber not found.');
        sc_redirect('/admin/subscribers.php');
    } catch (Throwable $e) {
        flash('error', 'Could not delete subscriber: ' . $e->getMessage());
        sc_redirect('/admin/subscriber_view.php?id='.$id);
    }
}

$subLists   = (function() use ($db,$id) { $s=$db->prepare("SELECT l.*,sl.status as sub_status,sl.subscribed_at FROM subscriber_lists sl JOIN lists l ON l.id=sl.list_id WHERE sl.subscriber_id=?"); $s->execute([$id]); return $s->fetchAll(); })();
$opens      = (function() use ($db,$id) { $s=$db->prepare("SELECT o.*,c.name as campaign_name FROM campaign_opens o JOIN campaigns c ON c.id=o.campaign_id WHERE o.subscriber_id=? ORDER BY o.opened_at DESC LIMIT 10"); $s->execute([$id]); return $s->fetchAll(); })();
$clicks     = (function() use ($db,$id) { $s=$db->prepare("SELECT ck.*,c.name as campaign_name FROM campaign_clicks ck JOIN campaigns c ON c.id=ck.campaign_id WHERE ck.subscriber_id=? ORDER BY ck.clicked_at DESC LIMIT 10"); $s->execute([$id]); return $s->fetchAll(); })();
$attrs      = json_decode($sub['attributes'] ?? '{}', true) ?: [];
$tags       = json_decode($sub['tags'] ?? '[]', true) ?: [];

$statusColors = ['active'=>'text-emerald-400 bg-emerald-900/30','unsubscribed'=>'text-amber-400 bg-amber-900/30','bounced'=>'text-red-400 bg-red-900/30','complained'=>'text-violet-400 bg-violet-900/30'];
?>

<div class="p-6 space-y-5">
  <div class="flex items-center gap-4">
    <a href="/admin/subscribers.php" class="text-slate-400 hover:text-white">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    </a>
    <h1 class="text-2xl font-bold text-white">Subscriber Profile</h1>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    <!-- Left: Profile -->
    <div class="xl:col-span-1 space-y-4">
      <div class="card p-6 text-center">
        <div class="w-20 h-20 rounded-full flex items-center justify-center text-2xl font-black text-white mx-auto mb-4" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
          <?= strtoupper(substr($sub['first_name']?:$sub['email'],0,1)) ?>
        </div>
        <h2 class="text-xl font-bold text-white mb-1"><?= e(trim($sub['first_name'].' '.$sub['last_name']) ?: '(no name)') ?></h2>
        <p class="text-sm text-slate-400 mb-3"><?= e($sub['email']) ?></p>
        <span class="px-3 py-1 rounded-full text-sm font-bold <?= $statusColors[$sub['status']] ?? 'text-slate-400 bg-slate-800' ?>"><?= ucfirst(e($sub['status'])) ?></span>
        <div class="mt-4 pt-4 border-t border-white/5 text-xs text-slate-500 space-y-1">
          <div>Joined: <?= date('M j, Y', strtotime($sub['created_at'])) ?></div>
          <div>Source: <?= e($sub['source']) ?></div>
          <?php if ($sub['ip_address']): ?><div>IP: <?= e($sub['ip_address']) ?></div><?php endif; ?>
        </div>
      </div>

      <!-- Tags -->
      <?php if ($tags): ?>
      <div class="card p-5">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">Tags</h3>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($tags as $tag): ?>
          <span class="px-2.5 py-1 rounded-full text-xs font-semibold text-indigo-300 bg-indigo-900/30 border border-indigo-700/30"><?= e($tag) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Lists -->
      <div class="card p-5">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">List Memberships</h3>
        <?php if (empty($subLists)): ?>
        <p class="text-xs text-slate-500">Not on any lists.</p>
        <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($subLists as $sl): ?>
          <div class="flex justify-between items-center">
            <span class="text-sm text-slate-200"><?= e($sl['name']) ?></span>
            <span class="text-xs text-slate-500 <?= $sl['sub_status']==='confirmed'?'text-emerald-400':'' ?>"><?= e($sl['sub_status']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="card p-5">
        <h3 class="text-sm font-bold text-red-400 uppercase tracking-wider mb-3">Danger Zone</h3>
        <form method="post" onsubmit="return confirm('Delete this subscriber permanently?')">
          <input type="hidden" name="action" value="delete">
          <button class="btn btn-danger w-full justify-center" type="submit">Delete Subscriber</button>
        </form>
      </div>
    </div>

    <!-- Right: Edit form + activity -->
    <div class="xl:col-span-2 space-y-4">
      <div class="card p-6">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4">Edit Details</h3>
        <form method="post" class="grid grid-cols-2 gap-4">
          <input type="hidden" name="action" value="update">
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">First Name</label>
            <input type="text" name="first_name" value="<?= e($sub['first_name']) ?>" class="form-input w-full">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Last Name</label>
            <input type="text" name="last_name" value="<?= e($sub['last_name']) ?>" class="form-input w-full">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Email (read-only)</label>
            <input type="email" value="<?= e($sub['email']) ?>" class="form-input w-full opacity-50" disabled>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Phone</label>
            <input type="text" name="phone" value="<?= e($sub['phone']) ?>" class="form-input w-full">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Status</label>
            <select name="status" class="form-input w-full">
              <?php foreach (['active','unsubscribed','bounced','complained'] as $st): ?>
              <option value="<?= $st ?>" <?= $sub['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="flex items-end">
            <button type="submit" class="btn btn-primary w-full justify-center">Save Changes</button>
          </div>
        </form>
      </div>

      <!-- Campaign opens -->
      <div class="card p-5">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">Recent Opens</h3>
        <?php if (empty($opens)): ?>
        <p class="text-xs text-slate-500">No opens recorded.</p>
        <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($opens as $o): ?>
          <div class="flex justify-between text-xs py-1 border-b border-white/5 last:border-0">
            <span class="text-slate-200"><?= e(mb_strimwidth($o['campaign_name'],0,40,'…')) ?></span>
            <span class="text-slate-500"><?= timeAgo($o['opened_at']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Clicks -->
      <div class="card p-5">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">Recent Clicks</h3>
        <?php if (empty($clicks)): ?>
        <p class="text-xs text-slate-500">No clicks recorded.</p>
        <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($clicks as $ck): ?>
          <div class="flex justify-between text-xs py-1 border-b border-white/5 last:border-0">
            <a href="<?= e($ck['url']) ?>" target="_blank" class="text-indigo-400 hover:text-indigo-300 truncate max-w-xs"><?= e(mb_strimwidth($ck['url']??'',0,50,'…')) ?></a>
            <span class="text-slate-500 ml-2"><?= timeAgo($ck['clicked_at']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>
