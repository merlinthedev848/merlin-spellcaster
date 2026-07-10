<?php
/**
 * survey.php — Public survey response page
 * PHP 8.5+ — standalone, no auth required
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

$token  = trim($_GET['token'] ?? '');
if (!$token) { http_response_code(404); die('Survey not found.'); }

$st = $db->prepare("SELECT * FROM surveys WHERE access_token=? AND status='active'");
$st->execute([$token]);
$survey = $st->fetch();
if (!$survey) {
    http_response_code(404);
    die('<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:60px;background:#0b0f19;color:#fff"><h2>Survey not found or closed.</h2></body></html>');
}

$sq = $db->prepare("SELECT * FROM survey_questions WHERE survey_id=? ORDER BY order_num");
$sq->execute([$survey['id']]);
$questions = $sq->fetchAll();

$submitted = false;
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required questions
    foreach ($questions as $q) {
        if ($q['required'] && empty($_POST['q_'.$q['id']])) {
            $errors[] = 'Question ' . ($q['order_num']) . ' is required.';
        }
    }
    if (empty($errors)) {
        $db->prepare("INSERT INTO survey_responses (survey_id,ip_address) VALUES (?,?)")
           ->execute([$survey['id'], $_SERVER['REMOTE_ADDR'] ?? '']);
        $responseId = (int)$db->lastInsertId();
        $stAns = $db->prepare("INSERT INTO survey_answers (response_id,question_id,answer) VALUES (?,?,?)");
        foreach ($questions as $q) {
            $ans = trim($_POST['q_'.$q['id']] ?? '');
            if ($ans !== '') {
                $stAns->execute([$responseId, $q['id'], $ans]);
            }
        }
        $submitted = true;
    }
}

$appName = getSetting('app_name','Merlin Spellcaster');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($survey['name']) ?> — <?= e($appName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#0B0F19;min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:32px 16px}
.card{background:#111827;border:1px solid rgba(148,163,184,0.08);border-radius:16px;padding:32px;width:100%;max-width:600px}
h1{color:#fff;font-size:22px;font-weight:800;margin:0 0 8px}
p.desc{color:#94a3b8;font-size:14px;margin:0 0 28px;line-height:1.7}
.question{margin-bottom:24px}
label.q{display:block;color:#e2e8f0;font-size:14px;font-weight:600;margin-bottom:8px}
.required::after{content:'*';color:#f87171;margin-left:3px}
input[type="text"],input[type="email"],textarea,select{background:rgba(255,255,255,0.03);border:1px solid rgba(148,163,184,0.12);color:#e2e8f0;border-radius:10px;padding:10px 14px;width:100%;font-size:14px;outline:none;transition:border-color 0.2s;font-family:inherit}
input:focus,textarea:focus,select:focus{border-color:#6366f1}
input::placeholder,textarea::placeholder{color:#475569}
textarea{resize:vertical;min-height:80px}
.radio-group,.check-group{display:flex;flex-direction:column;gap:8px}
.radio-opt{display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid rgba(148,163,184,0.1);border-radius:10px;cursor:pointer;transition:all 0.15s}
.radio-opt:hover{border-color:rgba(99,102,241,0.4);background:rgba(99,102,241,0.05)}
.radio-opt input{accent-color:#6366f1}
.nps-grid{display:flex;gap:6px;flex-wrap:wrap}
.nps-btn{width:48px;height:48px;border-radius:10px;border:1px solid rgba(148,163,184,0.12);background:rgba(255,255,255,0.03);color:#94a3b8;font-weight:700;cursor:pointer;transition:all 0.15s;font-size:14px}
.nps-btn:hover,.nps-btn.active{background:#6366f1;color:#fff;border-color:#6366f1}
.rating-stars{display:flex;gap:6px}
.star-btn{font-size:28px;cursor:pointer;transition:transform 0.1s;background:none;border:none;padding:0}
.star-btn:hover{transform:scale(1.15)}
.btn-submit{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;font-weight:700;padding:14px 32px;border-radius:10px;border:none;cursor:pointer;font-size:15px;width:100%;transition:all 0.2s;box-shadow:0 4px 20px rgba(99,102,241,0.3)}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 8px 28px rgba(99,102,241,0.5)}
.error{color:#f87171;font-size:13px;margin-bottom:16px;padding:12px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);border-radius:10px}
.success-box{text-align:center;padding:48px 0}
.success-box .check{font-size:64px;margin-bottom:16px}
.success-box h2{color:#fff;font-size:22px;font-weight:800;margin:0 0 8px}
.success-box p{color:#94a3b8;font-size:14px}
.poweredby{text-align:center;margin-top:16px;font-size:11px;color:#334155}
.poweredby a{color:#6366f1;text-decoration:none}
</style>
</head>
<body>
<div>
  <div class="card">
    <?php if ($submitted): ?>
    <div class="success-box">
      <div class="check">✅</div>
      <h2>Thank you!</h2>
      <p>Your response has been recorded. We appreciate your feedback.</p>
    </div>
    <?php else: ?>
    <h1><?= e($survey['name']) ?></h1>
    <?php if ($survey['description']): ?><p class="desc"><?= nl2br(e($survey['description'])) ?></p><?php endif; ?>

    <?php if ($errors): ?>
    <div class="error"><?= implode('<br>',array_map('htmlspecialchars',$errors)) ?></div>
    <?php endif; ?>

    <form method="post">
      <?php foreach ($questions as $qi => $q):
        $name = 'q_'.$q['id'];
        $opts = $q['options'] ? json_decode($q['options'],true) : [];
        $posted = $_POST[$name] ?? '';
      ?>
      <div class="question">
        <label class="q <?= $q['required']?'required':'' ?>"><?= ($qi+1) . '. ' . e($q['question']) ?></label>

        <?php if ($q['type'] === 'text' || $q['type'] === 'email'): ?>
          <input type="<?= $q['type'] ?>" name="<?= $name ?>" value="<?= e($posted) ?>" <?= $q['required']?'required':'' ?>>

        <?php elseif ($q['type'] === 'textarea'): ?>
          <textarea name="<?= $name ?>" <?= $q['required']?'required':'' ?>><?= e($posted) ?></textarea>

        <?php elseif ($q['type'] === 'mc'): ?>
          <div class="radio-group">
            <?php foreach ($opts as $opt): ?>
            <label class="radio-opt">
              <input type="radio" name="<?= $name ?>" value="<?= e($opt) ?>" <?= $posted===e($opt)?'checked':'' ?> <?= $q['required']?'required':'' ?>>
              <?= e($opt) ?>
            </label>
            <?php endforeach; ?>
          </div>

        <?php elseif ($q['type'] === 'dropdown'): ?>
          <select name="<?= $name ?>" <?= $q['required']?'required':'' ?>>
            <option value="">Select an option…</option>
            <?php foreach ($opts as $opt): ?><option value="<?= e($opt) ?>" <?= $posted===e($opt)?'selected':'' ?>><?= e($opt) ?></option><?php endforeach; ?>
          </select>

        <?php elseif ($q['type'] === 'yesno'): ?>
          <div class="radio-group" style="flex-direction:row;gap:12px">
            <label class="radio-opt" style="flex:1"><input type="radio" name="<?= $name ?>" value="Yes" <?= $posted==='Yes'?'checked':'' ?>> Yes</label>
            <label class="radio-opt" style="flex:1"><input type="radio" name="<?= $name ?>" value="No" <?= $posted==='No'?'checked':'' ?>> No</label>
          </div>

        <?php elseif ($q['type'] === 'rating'): ?>
          <div class="rating-stars" id="stars_<?= $q['id'] ?>">
            <?php for ($s=1;$s<=5;$s++): ?>
            <button type="button" class="star-btn" onclick="setRating(<?= $q['id'] ?>,<?= $s ?>)" data-val="<?= $s ?>">☆</button>
            <?php endfor; ?>
          </div>
          <input type="hidden" name="<?= $name ?>" id="rating_<?= $q['id'] ?>" value="<?= e($posted) ?>">

        <?php elseif ($q['type'] === 'nps'): ?>
          <div>
            <div class="nps-grid" id="nps_<?= $q['id'] ?>">
              <?php for ($n=0;$n<=10;$n++): ?>
              <button type="button" class="nps-btn <?= $posted==(string)$n?'active':'' ?>" onclick="setNps(<?= $q['id'] ?>,<?= $n ?>)"><?= $n ?></button>
              <?php endfor; ?>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:6px">
              <span style="color:#94a3b8;font-size:11px">Not at all likely</span>
              <span style="color:#94a3b8;font-size:11px">Extremely likely</span>
            </div>
          </div>
          <input type="hidden" name="<?= $name ?>" id="nps_val_<?= $q['id'] ?>" value="<?= e($posted) ?>">
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <button type="submit" class="btn-submit">Submit Response →</button>
    </form>
    <?php endif; ?>
  </div>
  <div class="poweredby">Powered by <a href="<?= e(getSetting('app_url')) ?>"><?= e($appName) ?></a></div>
</div>

<script>
function setRating(id,val){
  document.getElementById('rating_'+id).value=val;
  const stars=document.querySelectorAll('#stars_'+id+' .star-btn');
  stars.forEach((s,i)=>s.textContent=i<val?'⭐':'☆');
}
function setNps(id,val){
  document.getElementById('nps_val_'+id).value=val;
  document.querySelectorAll('#nps_'+id+' .nps-btn').forEach(b=>{b.classList.toggle('active',parseInt(b.dataset.val??b.textContent)===val);});
}
// Re-apply rating stars for preloaded values
document.querySelectorAll('[id^="rating_"]').forEach(inp=>{
  if(inp.value) setRating(inp.id.replace('rating_',''),parseInt(inp.value));
});
</script>
</body>
</html>
