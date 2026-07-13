<?php
/**
 * admin/automation.php — Email automation sequences
 * PHP 8.5+
 */
declare(strict_types=1);
$pageTitle = 'Automation';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'create_sequence') {
        $name    = trim($_POST['name'] ?? '');
        $trigger = $_POST['trigger_event'] ?? 'subscribe';
        $listId  = (int)($_POST['list_id'] ?? 0) ?: null;
        if ($name) {
            $db->prepare("INSERT INTO automation_sequences (name,trigger_event,list_id,status) VALUES (?,?,?,'paused')")->execute([$name,$trigger,$listId]);
            flash('success','Sequence created.');
        }
    }
    if ($action === 'toggle' && $id) {
        $st = $db->prepare("SELECT status FROM automation_sequences WHERE id=?"); $st->execute([$id]); $seq = $st->fetch();
        $newStatus = ($seq['status']??'paused') === 'active' ? 'paused' : 'active';
        $db->prepare("UPDATE automation_sequences SET status=? WHERE id=?")->execute([$newStatus,$id]);
        flash('success','Sequence '.($newStatus==='active'?'activated':'paused').'.');
    }
    if ($action === 'delete' && $id) {
        $db->prepare("DELETE FROM automation_sequences WHERE id=?")->execute([$id]);
        flash('success','Sequence deleted.');
    }
    if ($action === 'add_step') {
        $seqId  = (int)($_POST['seq_id'] ?? 0);
        $type   = $_POST['step_type'] ?? 'email';
        $delay  = (int)($_POST['delay_days'] ?? 0);
        $hours  = (int)($_POST['delay_hours'] ?? 0);
        $subj   = trim($_POST['subject'] ?? '');
        $html   = $_POST['body_html'] ?? '';
        $maxOrder = $db->prepare("SELECT COALESCE(MAX(step_order),0) FROM automation_steps WHERE sequence_id=?"); $maxOrder->execute([$seqId]);
        $nextOrder = (int)$maxOrder->fetchColumn() + 1;
        $db->prepare("INSERT INTO automation_steps (sequence_id,step_order,type,delay_days,delay_hours,subject,body_html) VALUES (?,?,?,?,?,?,?)")->execute([$seqId,$nextOrder,$type,$delay,$hours,$subj,$html]);
        flash('success','Step added.');
    }
    if ($action === 'delete_step') {
        $stepId = (int)($_POST['step_id'] ?? 0);
        if ($stepId) $db->prepare("DELETE FROM automation_steps WHERE id=?")->execute([$stepId]);
        flash('success','Step removed.');
    }
    sc_redirect('/admin/automation.php' . ($_GET['seq'] ? '?seq='.(int)$_GET['seq'] : ''));
}

$sequences = $db->query("SELECT * FROM automation_sequences ORDER BY created_at DESC")->fetchAll();
$lists     = $db->query("SELECT id,name FROM lists ORDER BY name")->fetchAll();
$activeSeqId = (int)($_GET['seq'] ?? 0);
$steps = [];
if ($activeSeqId) {
    $st = $db->prepare("SELECT * FROM automation_steps WHERE sequence_id=? ORDER BY step_order");
    $st->execute([$activeSeqId]); $steps = $st->fetchAll();
}
?>

<div class="p-6 space-y-5">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-white">Automation</h1>
      <p class="text-sm text-slate-400 mt-0.5">Drip campaigns & trigger-based email sequences</p>
    </div>
    <button onclick="document.getElementById('seqModal').showModal()" class="btn btn-primary">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      New Sequence
    </button>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    <!-- Sequences list -->
    <div class="space-y-3">
      <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Sequences</h2>
      <?php if (empty($sequences)): ?>
      <div class="card p-6 text-center">
        <div class="text-4xl mb-3">⚡</div>
        <p class="text-slate-400 text-sm">No sequences yet. Create one to get started.</p>
      </div>
      <?php else: foreach ($sequences as $seq): ?>
      <a href="?seq=<?= $seq['id'] ?>" class="card p-4 flex items-center justify-between group cursor-pointer hover:border-indigo-500/30 transition-all <?= $activeSeqId===$seq['id']?'border-indigo-500/40 bg-indigo-900/10':'' ?>" style="text-decoration:none">
        <div class="min-w-0">
          <p class="font-semibold text-white text-sm truncate"><?= e($seq['name']) ?></p>
          <p class="text-xs text-slate-500 mt-0.5">Trigger: <?= e($seq['trigger_event']) ?> · <?= $seq['enrolled_count'] ?> enrolled</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <span class="w-2 h-2 rounded-full <?= $seq['status']==='active'?'bg-emerald-400':'bg-slate-600' ?>"></span>
          <form method="post" style="display:inline" onclick="event.preventDefault(); event.stopPropagation(); this.submit()">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= $seq['id'] ?>">
            <button class="text-xs font-semibold <?= $seq['status']==='active'?'text-emerald-400':'text-slate-500' ?> hover:text-white transition-colors"><?= $seq['status']==='active'?'LIVE':'PAUSED' ?></button>
          </form>
          <form method="post" style="display:inline" onsubmit="event.stopPropagation(); return confirm('Delete sequence?')" onclick="event.stopPropagation()">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $seq['id'] ?>">
            <button class="p-1 text-slate-600 hover:text-red-400 transition-colors" title="Delete">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </form>
        </div>
      </a>
      <?php endforeach; endif; ?>
    </div>

    <!-- Step builder -->
    <div class="xl:col-span-2">
      <?php if (!$activeSeqId): ?>
      <div class="card p-10 text-center">
        <div class="text-5xl mb-4">👈</div>
        <p class="text-slate-400">Select a sequence to manage its steps.</p>
      </div>
      <?php else:
        $activeSeq = null;
        foreach ($sequences as $sq) { if ($sq['id'] === $activeSeqId) { $activeSeq = $sq; break; } }
      ?>
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-bold text-white"><?= e($activeSeq['name'] ?? '') ?> — Steps</h2>
          <button onclick="document.getElementById('stepModal').showModal()" class="btn btn-secondary text-sm">+ Add Step</button>
        </div>

        <!-- Steps timeline -->
        <?php if (empty($steps)): ?>
        <div class="card p-8 text-center"><p class="text-slate-400">No steps yet. Add your first email step.</p></div>
        <?php else: ?>
        <div class="space-y-3">
          <?php foreach ($steps as $si => $step): ?>
          <div class="card p-4 flex items-start gap-4">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-black flex-shrink-0" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white"><?= $si + 1 ?></div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-bold px-2 py-0.5 rounded-full <?= $step['type']==='email'?'text-indigo-400 bg-indigo-900/30':'text-amber-400 bg-amber-900/30' ?>"><?= strtoupper($step['type']) ?></span>
                <span class="text-xs text-slate-500">Wait <?= $step['delay_days'] ?>d <?= $step['delay_hours'] ?>h before sending</span>
              </div>
              <?php if ($step['subject']): ?><p class="text-sm font-semibold text-slate-200"><?= e($step['subject']) ?></p><?php endif; ?>
              <?php if ($step['body_html']): ?><p class="text-xs text-slate-500 mt-1"><?= e(mb_strimwidth(strip_tags($step['body_html']),0,80,'…')) ?></p><?php endif; ?>
            </div>
            <form method="post" onsubmit="return confirm('Remove step?')">
              <input type="hidden" name="action" value="delete_step">
              <input type="hidden" name="step_id" value="<?= $step['id'] ?>">
              <input type="hidden" name="" value="<?= $activeSeqId ?>">
              <button class="p-1.5 rounded-lg hover:bg-red-500/20 text-slate-500 hover:text-red-400 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </form>
          </div>
          <?php if ($si < count($steps)-1): ?><div class="flex justify-center"><div class="w-0.5 h-6 bg-slate-700"></div></div><?php endif; ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Create Sequence Modal -->
<dialog id="seqModal" class="rounded-2xl border-0 p-0 shadow-2xl w-full max-w-md" style="background:#111827">
  <form method="post" class="p-6 space-y-4">
    <input type="hidden" name="action" value="create_sequence">
    <h2 class="text-lg font-bold text-white">New Automation Sequence</h2>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">Sequence Name *</label>
      <input type="text" name="name" class="form-input w-full" placeholder="Welcome Series" required autofocus>
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">Trigger Event</label>
      <select name="trigger_event" class="form-input w-full">
        <option value="subscribe">On Subscribe</option>
        <option value="confirm">On Confirm (double opt-in)</option>
        <option value="tag">On Tag Applied</option>
      </select>
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">For List (optional)</label>
      <select name="list_id" class="form-input w-full">
        <option value="">Any list</option>
        <?php foreach ($lists as $l): ?><option value="<?= $l['id'] ?>"><?= e($l['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="flex gap-2">
      <button type="submit" class="btn btn-primary flex-1 justify-center">Create</button>
      <button type="button" onclick="document.getElementById('seqModal').close()" class="btn btn-secondary flex-1 justify-center">Cancel</button>
    </div>
  </form>
</dialog>

<!-- Add Step Modal -->
<dialog id="stepModal" class="rounded-2xl border-0 p-0 shadow-2xl w-full max-w-lg" style="background:#111827">
  <form method="post" class="p-6 space-y-4">
    <input type="hidden" name="action" value="add_step">
    <input type="hidden" name="seq_id" value="<?= $activeSeqId ?>">
    <h2 class="text-lg font-bold text-white">Add Step</h2>
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Step Type</label>
        <select name="step_type" class="form-input w-full">
          <option value="email">📧 Send Email</option>
          <option value="wait">⏱ Wait / Delay</option>
          <option value="tag">🏷 Apply Tag</option>
          <option value="webhook">🔗 Webhook</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Delay Before Sending</label>
        <div class="flex gap-2">
          <input type="number" name="delay_days" value="0" min="0" class="form-input w-full text-sm" placeholder="Days">
          <input type="number" name="delay_hours" value="0" min="0" max="23" class="form-input w-full text-sm" placeholder="Hrs">
        </div>
      </div>
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">Subject</label>
      <input type="text" name="subject" class="form-input w-full" placeholder="Welcome to {{app_name}}!">
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">Email Body (HTML)</label>
      <textarea name="body_html" class="form-input w-full font-mono text-xs" rows="6" placeholder="<h2>Welcome!</h2><p>Hello {{first_name}}…</p>"></textarea>
    </div>
    <div class="flex gap-2">
      <button type="submit" class="btn btn-primary flex-1 justify-center">Add Step</button>
      <button type="button" onclick="document.getElementById('stepModal').close()" class="btn btn-secondary flex-1 justify-center">Cancel</button>
    </div>
  </form>
</dialog>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>
