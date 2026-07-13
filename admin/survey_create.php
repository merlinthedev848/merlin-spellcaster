<?php
/**
 * admin/survey_create.php — Create/Edit survey with question builder
 * PHP 8.5+
 */
declare(strict_types=1);
$id       = (int)($_GET['id'] ?? 0);
$survey   = null;
$questions= [];
$pageTitle= $id ? 'Edit Survey' : 'New Survey';
require_once __DIR__ . '/../includes/header.php';

if ($id) {
    $st = $db->prepare("SELECT * FROM surveys WHERE id=?"); $st->execute([$id]); $survey = $st->fetch();
    if (!$survey) { flash('error','Survey not found.'); sc_redirect('/admin/research.php'); }
    $sq = $db->prepare("SELECT * FROM survey_questions WHERE survey_id=? ORDER BY order_num"); $sq->execute([$id]); $questions = $sq->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_survey') {
        $name  = trim($_POST['name'] ?? '');
        $desc  = trim($_POST['desc'] ?? '');
        if (!$name) { flash('error','Survey name required.'); sc_redirect("/admin/survey_create.php?id={$id}"); }
        if ($id) {
            $db->prepare("UPDATE surveys SET name=?,description=? WHERE id=?")->execute([$name,$desc,$id]);
        } else {
            $token = bin2hex(random_bytes(16));
            $db->prepare("INSERT INTO surveys (name,description,status,access_token) VALUES (?,?,'draft',?)")->execute([$name,$desc,$token]);
            $id = (int)$db->lastInsertId();
        }
        flash('success','Survey saved.');
        sc_redirect("/admin/survey_create.php?id={$id}");
    }

    if ($action === 'add_question') {
        $question = trim($_POST['question'] ?? '');
        $type     = $_POST['type'] ?? 'text';
        $options  = trim($_POST['options'] ?? '');
        $required = isset($_POST['required']) ? 1 : 0;
        if ($question && $id) {
            $maxOrder = (int)$db->prepare("SELECT COALESCE(MAX(order_num),0) FROM survey_questions WHERE survey_id=?")->execute([$id]) ? (function() use ($db,$id) {
                $s=$db->prepare("SELECT COALESCE(MAX(order_num),0) FROM survey_questions WHERE survey_id=?"); $s->execute([$id]); return (int)$s->fetchColumn();
            })() : 0;
            $optArr = $options ? json_encode(array_map('trim', explode("\n", $options))) : null;
            $db->prepare("INSERT INTO survey_questions (survey_id,question,type,options,required,order_num) VALUES (?,?,?,?,?,?)")
               ->execute([$id,$question,$type,$optArr,$required,$maxOrder+1]);
            flash('success','Question added.');
        }
        sc_redirect("/admin/survey_create.php?id={$id}");
    }

    if ($action === 'delete_question') {
        $qid = (int)($_POST['qid'] ?? 0);
        if ($qid) $db->prepare("DELETE FROM survey_questions WHERE id=?")->execute([$qid]);
        sc_redirect("/admin/survey_create.php?id={$id}");
    }

    if ($action === 'activate') {
        $db->prepare("UPDATE surveys SET status='active' WHERE id=?")->execute([$id]);
        flash('success','Survey is now live!');
        sc_redirect("/admin/survey_create.php?id={$id}");
    }
}

$questionTypes = ['text'=>'Text','textarea'=>'Long Text','mc'=>'Multiple Choice','rating'=>'Rating (1-5)','nps'=>'NPS (0-10)','yesno'=>'Yes/No','dropdown'=>'Dropdown','email'=>'Email'];
?>

<div class="p-6 space-y-5">
  <div class="flex items-center gap-4">
    <a href="/admin/research.php" class="text-slate-400 hover:text-white">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    </a>
    <h1 class="text-2xl font-bold text-white"><?= $pageTitle ?></h1>
    <?php if ($survey): ?>
    <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $survey['status']==='active'?'text-emerald-400 bg-emerald-900/30':'text-slate-400 bg-slate-800' ?>"><?= ucfirst(e($survey['status'])) ?></span>
    <?php endif; ?>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    <!-- Survey details form -->
    <div class="space-y-4">
      <div class="card p-5">
        <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4">Survey Details</h2>
        <form method="post" class="space-y-3">
          <input type="hidden" name="action" value="save_survey">
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Survey Name *</label>
            <input type="text" name="name" value="<?= e($survey['name'] ?? '') ?>" class="form-input w-full" placeholder="Customer Satisfaction Survey" required>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Description</label>
            <textarea name="desc" class="form-input w-full" rows="3" placeholder="What is this survey about?"><?= e($survey['description'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary w-full justify-center">💾 Save Survey</button>
        </form>
      </div>

      <?php if ($survey): ?>
      <!-- Actions -->
      <div class="card p-5 space-y-2">
        <?php if ($survey['status'] === 'draft'): ?>
        <form method="post">
          <input type="hidden" name="action" value="activate">
          <button type="submit" class="btn w-full justify-center" style="background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3)" onclick="return confirm('Make this survey live?')">🚀 Activate Survey</button>
        </form>
        <?php endif; ?>
        <a href="/admin/survey_view.php?id=<?= $id ?>" class="btn btn-secondary w-full justify-center">📊 View Results</a>
        <a href="<?= e(getSetting('app_url').'/survey.php?token='.$survey['access_token']) ?>" target="_blank" class="btn btn-secondary w-full justify-center">🔗 Preview</a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Question builder -->
    <div class="xl:col-span-2 space-y-4">
      <?php if (!$id): ?>
      <div class="card p-8 text-center">
        <p class="text-slate-400">Save the survey details first, then add questions.</p>
      </div>
      <?php else: ?>

      <!-- Existing questions -->
      <?php if ($questions): ?>
      <div class="space-y-3">
        <?php foreach ($questions as $qi => $q): ?>
        <div class="card p-4 flex items-start gap-4 group">
          <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-black text-white flex-shrink-0" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)"><?= $qi+1 ?></div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-xs font-bold px-2 py-0.5 rounded-full text-indigo-400 bg-indigo-900/30"><?= e($questionTypes[$q['type']] ?? $q['type']) ?></span>
              <?php if ($q['required']): ?><span class="text-xs text-red-400">Required</span><?php endif; ?>
            </div>
            <p class="text-sm text-slate-200"><?= e($q['question']) ?></p>
            <?php if ($q['options']): ?>
            <div class="flex flex-wrap gap-1 mt-1">
              <?php foreach (json_decode($q['options'],true)??[] as $opt): ?>
              <span class="text-xs bg-slate-800 px-2 py-0.5 rounded text-slate-400"><?= e($opt) ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <form method="post" onsubmit="return confirm('Remove question?')" style="display:inline">
            <input type="hidden" name="action" value="delete_question">
            <input type="hidden" name="qid" value="<?= $q['id'] ?>">
            <button class="p-1.5 rounded-lg hover:bg-red-500/20 text-slate-600 hover:text-red-400 transition-colors opacity-0 group-hover:opacity-100">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Add question form -->
      <div class="card p-5" x-data="{qtype:'text'}">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4">Add Question</h3>
        <form method="post" class="space-y-3">
          <input type="hidden" name="action" value="add_question">
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Question *</label>
            <input type="text" name="question" class="form-input w-full" placeholder="How satisfied are you with our product?" required>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-400 mb-1.5">Type</label>
              <select name="type" x-model="qtype" class="form-input w-full">
                <?php foreach ($questionTypes as $v=>$l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="flex items-end pb-1">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="required" class="accent-indigo-500 w-4 h-4">
                <span class="text-sm text-slate-300">Required</span>
              </label>
            </div>
          </div>
          <!-- Options (for MC/dropdown) -->
          <div x-show="qtype==='mc'||qtype==='dropdown'" x-cloak>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Options (one per line)</label>
            <textarea name="options" class="form-input w-full font-mono text-xs" rows="4" placeholder="Very satisfied&#10;Satisfied&#10;Neutral&#10;Dissatisfied"></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Add Question</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>
