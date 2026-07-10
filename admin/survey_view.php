<?php
/**
 * admin/survey_view.php — Survey results & analytics
 * PHP 8.5+
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/core/Auth.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) sc_redirect('/admin/research.php');
$pageTitle = 'Survey Results';
require_once __DIR__ . '/../includes/header.php';

$st = $db->prepare("SELECT * FROM surveys WHERE id=?"); $st->execute([$id]); $survey = $st->fetch();
if (!$survey) { flash('error','Survey not found.'); sc_redirect('/admin/research.php'); }

$questions  = (function() use ($db,$id) { $s=$db->prepare("SELECT * FROM survey_questions WHERE survey_id=? ORDER BY order_num"); $s->execute([$id]); return $s->fetchAll(); })();
$totalResps = (int)$db->prepare("SELECT COUNT(*) FROM survey_responses WHERE survey_id=?")->execute([$id]) ? (function() use ($db,$id) { $s=$db->prepare("SELECT COUNT(*) FROM survey_responses WHERE survey_id=?"); $s->execute([$id]); return (int)$s->fetchColumn(); })() : 0;

// Handle export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="survey_'.$id.'_results.csv"');
    $out = fopen('php://output','w');
    // Header row
    $header = ['Response ID','Date'];
    foreach ($questions as $q) $header[] = $q['question'];
    fputcsv($out,$header);
    $responses = (function() use ($db,$id) { $s=$db->prepare("SELECT * FROM survey_responses WHERE survey_id=? ORDER BY created_at DESC"); $s->execute([$id]); return $s->fetchAll(); })();
    foreach ($responses as $r) {
        $row = [$r['id'], $r['created_at']];
        foreach ($questions as $q) {
            $ans = $db->prepare("SELECT answer FROM survey_answers WHERE response_id=? AND question_id=?"); $ans->execute([$r['id'],$q['id']]); $row[] = $ans->fetchColumn() ?: '';
        }
        fputcsv($out,$row);
    }
    fclose($out);
    exit;
}

// Per-question analytics
$analytics = [];
foreach ($questions as $q) {
    $answers = (function() use ($db,$q) {
        $s=$db->prepare("SELECT answer FROM survey_answers WHERE question_id=? AND answer IS NOT NULL AND answer!=''");
        $s->execute([$q['id']]); return $s->fetchAll(PDO::FETCH_COLUMN);
    })();
    $data = ['question'=>$q,'answers'=>$answers,'count'=>count($answers)];
    if (in_array($q['type'],['mc','dropdown'])) {
        $counts = array_count_values($answers);
        $data['distribution'] = $counts;
    } elseif ($q['type'] === 'rating') {
        $nums = array_filter($answers,fn($v)=>is_numeric($v));
        $data['avg'] = $nums ? round(array_sum($nums)/count($nums),1) : 0;
        $data['distribution'] = array_count_values($nums);
    } elseif ($q['type'] === 'nps') {
        $nums = array_filter($answers,fn($v)=>is_numeric($v));
        $promoters   = count(array_filter($nums,fn($v)=>$v>=9));
        $detractors  = count(array_filter($nums,fn($v)=>$v<=6));
        $total       = count($nums);
        $data['nps'] = $total > 0 ? round(($promoters-$detractors)/$total*100) : 0;
        $data['promoters']   = $promoters;
        $data['passives']    = $total - $promoters - $detractors;
        $data['detractors']  = $detractors;
        $data['total']       = $total;
    } elseif ($q['type'] === 'yesno') {
        $yes = count(array_filter($answers,fn($v)=>strtolower($v)==='yes'));
        $no  = count(array_filter($answers,fn($v)=>strtolower($v)==='no'));
        $data['yes'] = $yes; $data['no'] = $no;
    }
    $analytics[] = $data;
}
?>

<div class="p-6 space-y-5">
  <div class="flex items-start justify-between">
    <div class="flex items-center gap-4">
      <a href="/admin/research.php" class="text-slate-400 hover:text-white">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-white"><?= e($survey['name']) ?></h1>
        <p class="text-sm text-slate-400 mt-0.5"><?= number_format($totalResps) ?> response<?= $totalResps!==1?'s':'' ?></p>
      </div>
    </div>
    <div class="flex gap-2">
      <a href="?id=<?= $id ?>&export=csv" class="btn btn-secondary text-sm">📥 Export CSV</a>
      <a href="/admin/survey_create.php?id=<?= $id ?>" class="btn btn-secondary text-sm">✏️ Edit</a>
    </div>
  </div>

  <?php if ($totalResps === 0): ?>
  <div class="card p-12 text-center">
    <div class="text-5xl mb-4">📭</div>
    <h3 class="text-white font-bold mb-2">No responses yet</h3>
    <p class="text-slate-400 text-sm">Share the survey link to start collecting responses.</p>
    <?php if ($survey['status']==='active'): ?>
    <div class="mt-6 flex items-center gap-2 max-w-md mx-auto">
      <input type="text" value="<?= e(getSetting('app_url').'/survey.php?token='.$survey['access_token']) ?>" readonly class="text-xs bg-slate-900 border border-white/10 rounded-lg px-3 py-2 flex-1 text-slate-400 focus:outline-none">
      <button onclick="navigator.clipboard.writeText(document.querySelector('[readonly]').value)" class="btn btn-primary text-xs">Copy Link</button>
    </div>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <!-- Question results -->
  <div class="space-y-5">
    <?php foreach ($analytics as $qi => $a):
      $q = $a['question'];
    ?>
    <div class="card p-5">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
          <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-black text-white flex-shrink-0" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)"><?= $qi+1 ?></div>
          <div>
            <h3 class="font-semibold text-white"><?= e($q['question']) ?></h3>
            <span class="text-xs text-slate-500"><?= $a['count'] ?> responses · <?= e($q['type']) ?></span>
          </div>
        </div>
      </div>

      <?php if ($q['type'] === 'nps'): ?>
      <!-- NPS Score -->
      <div class="grid grid-cols-4 gap-3 mb-4">
        <div class="card-sm p-3 text-center">
          <p class="text-2xl font-black <?= ($a['nps']??0)>=50?'text-emerald-400':(($a['nps']??0)>=0?'text-amber-400':'text-red-400') ?>"><?= $a['nps'] ?? 0 ?></p>
          <p class="text-xs text-slate-500">NPS Score</p>
        </div>
        <div class="card-sm p-3 text-center"><p class="text-xl font-black text-emerald-400"><?= $a['promoters'] ?? 0 ?></p><p class="text-xs text-slate-500">Promoters</p></div>
        <div class="card-sm p-3 text-center"><p class="text-xl font-black text-amber-400"><?= $a['passives'] ?? 0 ?></p><p class="text-xs text-slate-500">Passives</p></div>
        <div class="card-sm p-3 text-center"><p class="text-xl font-black text-red-400"><?= $a['detractors'] ?? 0 ?></p><p class="text-xs text-slate-500">Detractors</p></div>
      </div>

      <?php elseif (in_array($q['type'],['mc','dropdown']) && !empty($a['distribution'])): ?>
      <!-- Distribution bar chart -->
      <div class="space-y-2">
        <?php foreach ($a['distribution'] as $opt => $cnt): ?>
        <div class="flex items-center gap-3">
          <span class="text-xs text-slate-300 w-32 truncate"><?= e($opt) ?></span>
          <div class="flex-1 h-2 bg-slate-800 rounded-full overflow-hidden">
            <div class="h-full rounded-full bg-indigo-500" style="width:<?= $a['count']>0?round($cnt/$a['count']*100):0 ?>%"></div>
          </div>
          <span class="text-xs text-slate-400 w-12 text-right"><?= $cnt ?> (<?= $a['count']>0?round($cnt/$a['count']*100):0 ?>%)</span>
        </div>
        <?php endforeach; ?>
      </div>

      <?php elseif ($q['type'] === 'rating'): ?>
      <div class="flex items-center gap-4 mb-3">
        <div class="text-3xl font-black text-amber-400"><?= $a['avg'] ?? 0 ?> <span class="text-slate-500 text-lg font-normal">/ 5</span></div>
        <div class="flex gap-1"><?php for ($i=1;$i<=5;$i++): ?><span class="text-2xl"><?= $i<=round($a['avg']??0)?'⭐':'☆' ?></span><?php endfor; ?></div>
      </div>
      <div class="space-y-1">
        <?php for ($r=5;$r>=1;$r--): $cnt=$a['distribution'][$r]??0; ?>
        <div class="flex items-center gap-2">
          <span class="text-xs text-slate-500 w-4"><?= $r ?>★</span>
          <div class="flex-1 h-1.5 bg-slate-800 rounded-full"><div class="h-full rounded-full bg-amber-400" style="width:<?= $a['count']>0?round($cnt/$a['count']*100):0 ?>%"></div></div>
          <span class="text-xs text-slate-500 w-6"><?= $cnt ?></span>
        </div>
        <?php endfor; ?>
      </div>

      <?php elseif ($q['type'] === 'yesno'): ?>
      <div class="flex gap-6">
        <div class="text-center"><p class="text-2xl font-black text-emerald-400"><?= $a['yes'] ?></p><p class="text-xs text-slate-500">Yes</p></div>
        <div class="text-center"><p class="text-2xl font-black text-red-400"><?= $a['no'] ?></p><p class="text-xs text-slate-500">No</p></div>
      </div>

      <?php else: ?>
      <!-- Text responses -->
      <div class="space-y-2 max-h-48 overflow-y-auto">
        <?php foreach (array_slice($a['answers'],0,20) as $ans): ?>
        <div class="text-sm text-slate-300 p-2 rounded-lg bg-white/3 border border-white/5"><?= e($ans) ?></div>
        <?php endforeach; ?>
        <?php if ($a['count'] > 20): ?><p class="text-xs text-slate-500">… and <?= $a['count']-20 ?> more. Export CSV for full list.</p><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
