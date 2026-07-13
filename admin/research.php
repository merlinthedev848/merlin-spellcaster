<?php
/**
 * admin/research.php — Market Research hub
 * PHP 8.5+ — Schema: surveys, survey_questions, survey_responses, survey_answers
 */
declare(strict_types=1);
$pageTitle = 'Market Research';
require_once __DIR__ . '/../includes/header.php';

// Bootstrap survey tables
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `surveys` (
        `id`          INT NOT NULL AUTO_INCREMENT,
        `name`        VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `status`      VARCHAR(20) NOT NULL DEFAULT 'draft',
        `access_token`VARCHAR(64) NOT NULL DEFAULT '',
        `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS `survey_questions` (
        `id`          INT NOT NULL AUTO_INCREMENT,
        `survey_id`   INT NOT NULL,
        `question`    TEXT NOT NULL,
        `type`        VARCHAR(30) NOT NULL DEFAULT 'text',
        `options`     TEXT DEFAULT NULL COMMENT 'JSON array for mc/dropdown',
        `required`    TINYINT(1) NOT NULL DEFAULT 0,
        `order_num`   INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_survey` (`survey_id`),
        CONSTRAINT `fk_sq_survey` FOREIGN KEY (`survey_id`) REFERENCES `surveys`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS `survey_responses` (
        `id`          INT NOT NULL AUTO_INCREMENT,
        `survey_id`   INT NOT NULL,
        `subscriber_id` INT DEFAULT NULL,
        `ip_address`  VARCHAR(45) DEFAULT NULL,
        `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_survey` (`survey_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS `survey_answers` (
        `id`          INT NOT NULL AUTO_INCREMENT,
        `response_id` INT NOT NULL,
        `question_id` INT NOT NULL,
        `answer`      TEXT DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_response` (`response_id`),
        CONSTRAINT `fk_sa_response` FOREIGN KEY (`response_id`) REFERENCES `survey_responses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable) {}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::checkCsrf()) { flash('error', 'Invalid security token (CSRF). Please try again.'); sc_redirect(<?php
/**
 * admin/research.php — Market Research hub
 * PHP 8.5+ — Schema: surveys, survey_questions, survey_responses, survey_answers
 */
declare(strict_types=1);
$pageTitle = 'Market Research';
require_once __DIR__ . '/../includes/header.php';

// Bootstrap survey tables
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `surveys` (
        `id`          INT NOT NULL AUTO_INCREMENT,
        `name`        VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `status`      VARCHAR(20) NOT NULL DEFAULT 'draft',
        `access_token`VARCHAR(64) NOT NULL DEFAULT '',
        `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS `survey_questions` (
        `id`          INT NOT NULL AUTO_INCREMENT,
        `survey_id`   INT NOT NULL,
        `question`    TEXT NOT NULL,
        `type`        VARCHAR(30) NOT NULL DEFAULT 'text',
        `options`     TEXT DEFAULT NULL COMMENT 'JSON array for mc/dropdown',
        `required`    TINYINT(1) NOT NULL DEFAULT 0,
        `order_num`   INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_survey` (`survey_id`),
        CONSTRAINT `fk_sq_survey` FOREIGN KEY (`survey_id`) REFERENCES `surveys`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS `survey_responses` (
        `id`          INT NOT NULL AUTO_INCREMENT,
        `survey_id`   INT NOT NULL,
        `subscriber_id` INT DEFAULT NULL,
        `ip_address`  VARCHAR(45) DEFAULT NULL,
        `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_survey` (`survey_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS `survey_answers` (
        `id`          INT NOT NULL AUTO_INCREMENT,
        `response_id` INT NOT NULL,
        `question_id` INT NOT NULL,
        `answer`      TEXT DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_response` (`response_id`),
        CONSTRAINT `fk_sa_response` FOREIGN KEY (`response_id`) REFERENCES `survey_responses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable) {}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id) {
        $db->prepare("DELETE FROM surveys WHERE id=?")->execute([$id]);
        flash('success','Survey deleted.');
    }
    if ($action === 'toggle' && $id) {
        $cur = $db->prepare("SELECT status FROM surveys WHERE id=?"); $cur->execute([$id]); $row = $cur->fetch();
        $ns  = ($row['status']??'draft') === 'active' ? 'closed' : 'active';
        $db->prepare("UPDATE surveys SET status=? WHERE id=?")->execute([$ns,$id]);
        flash('success','Survey '.($ns==='active'?'activated':'closed').'.');
    }
    sc_redirect('/admin/research.php');
}

$surveys = $db->query("SELECT s.*, COUNT(DISTINCT r.id) as response_count FROM surveys s LEFT JOIN survey_responses r ON r.survey_id=s.id GROUP BY s.id ORDER BY s.created_at DESC")->fetchAll();
$appUrl  = getSetting('app_url');
$statusColors = ['draft'=>'text-slate-400 bg-slate-800','active'=>'text-emerald-400 bg-emerald-900/30','closed'=>'text-red-400 bg-red-900/30'];
?>

<div class="p-6 space-y-5">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-white">Market Research</h1>
      <p class="text-sm text-slate-400 mt-0.5">Surveys, NPS & audience insights</p>
    </div>
    <a href="/admin/survey_create.php" class="btn btn-primary">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      New Survey
    </a>
  </div>

  <!-- Overview stats -->
  <?php
  $totalResponses = (int)$db->query("SELECT COUNT(*) FROM survey_responses")->fetchColumn();
  $activeSurveys  = (int)$db->query("SELECT COUNT(*) FROM surveys WHERE status='active'")->fetchColumn();
  ?>
  <div class="grid grid-cols-3 gap-4 max-w-lg">
    <div class="card p-4 text-center"><p class="text-xl font-black text-white"><?= count($surveys) ?></p><p class="text-xs text-slate-500 mt-0.5">Total Surveys</p></div>
    <div class="card p-4 text-center"><p class="text-xl font-black text-emerald-400"><?= $activeSurveys ?></p><p class="text-xs text-slate-500 mt-0.5">Active</p></div>
    <div class="card p-4 text-center"><p class="text-xl font-black text-white"><?= number_format($totalResponses) ?></p><p class="text-xs text-slate-500 mt-0.5">Responses</p></div>
  </div>

  <!-- Survey cards -->
  <?php if (empty($surveys)): ?>
  <div class="card text-center py-16">
    <div class="text-5xl mb-4">🔬</div>
    <h3 class="text-white font-bold mb-2">No surveys yet</h3>
    <p class="text-slate-400 text-sm mb-6">Create your first survey to gather audience insights.</p>
    <a href="/admin/survey_create.php" class="btn btn-primary">Create Survey</a>
  </div>
  <?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($surveys as $survey): ?>
    <div class="card p-5 flex flex-col gap-4 group">
      <div class="flex items-start justify-between">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <h3 class="font-bold text-white"><?= e($survey['name']) ?></h3>
            <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $statusColors[$survey['status']] ?? '' ?>"><?= ucfirst(e($survey['status'])) ?></span>
          </div>
          <?php if ($survey['description']): ?><p class="text-xs text-slate-500"><?= e(mb_strimwidth($survey['description'],0,70,'…')) ?></p><?php endif; ?>
        </div>
      </div>

      <div class="flex gap-4 text-center">
        <div><p class="text-lg font-black text-white"><?= number_format((int)$survey['response_count']) ?></p><p class="text-xs text-slate-500">Responses</p></div>
        <div><p class="text-lg font-black text-white"><?= date('M j', strtotime($survey['created_at'])) ?></p><p class="text-xs text-slate-500">Created</p></div>
      </div>

      <div class="flex gap-2">
        <a href="/admin/survey_view.php?id=<?= $survey['id'] ?>" class="btn btn-secondary text-xs flex-1 justify-center">📊 Results</a>
        <a href="/admin/survey_create.php?id=<?= $survey['id'] ?>" class="btn btn-secondary text-xs justify-center">✏️</a>
        <form method="post" style="display:inline">
    <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= $survey['id'] ?>">
          <button class="btn text-xs <?= $survey['status']==='active'?'text-amber-400':'text-emerald-400' ?> bg-white/5 border border-white/10" title="<?= $survey['status']==='active'?'Close':'Activate' ?>">
            <?= $survey['status']==='active'?'⏸':'▶' ?>
          </button>
        </form>
      </div>

      <!-- Survey public link -->
      <?php if ($survey['status'] === 'active'): ?>
      <div class="pt-2 border-t border-white/5">
        <p class="text-xs text-slate-500 mb-1">Public Survey Link</p>
        <div class="flex items-center gap-2">
          <input type="text" value="<?= e($appUrl.'/survey.php?token='.$survey['access_token']) ?>" readonly class="text-xs bg-slate-900 border border-white/10 rounded-lg px-2 py-1.5 flex-1 text-slate-400 focus:outline-none truncate">
          <button type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)" class="text-xs text-indigo-400 px-2 py-1.5 rounded-lg bg-indigo-900/20 border border-indigo-700/20">Copy</button>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
SERVER['REQUEST_URI']); }
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id) {
        $db->prepare("DELETE FROM surveys WHERE id=?")->execute([$id]);
        flash('success','Survey deleted.');
    }
    if ($action === 'toggle' && $id) {
        $cur = $db->prepare("SELECT status FROM surveys WHERE id=?"); $cur->execute([$id]); $row = $cur->fetch();
        $ns  = ($row['status']??'draft') === 'active' ? 'closed' : 'active';
        $db->prepare("UPDATE surveys SET status=? WHERE id=?")->execute([$ns,$id]);
        flash('success','Survey '.($ns==='active'?'activated':'closed').'.');
    }
    sc_redirect('/admin/research.php');
}

$surveys = $db->query("SELECT s.*, COUNT(DISTINCT r.id) as response_count FROM surveys s LEFT JOIN survey_responses r ON r.survey_id=s.id GROUP BY s.id ORDER BY s.created_at DESC")->fetchAll();
$appUrl  = getSetting('app_url');
$statusColors = ['draft'=>'text-slate-400 bg-slate-800','active'=>'text-emerald-400 bg-emerald-900/30','closed'=>'text-red-400 bg-red-900/30'];
?>

<div class="p-6 space-y-5">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-white">Market Research</h1>
      <p class="text-sm text-slate-400 mt-0.5">Surveys, NPS & audience insights</p>
    </div>
    <a href="/admin/survey_create.php" class="btn btn-primary">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      New Survey
    </a>
  </div>

  <!-- Overview stats -->
  <?php
  $totalResponses = (int)$db->query("SELECT COUNT(*) FROM survey_responses")->fetchColumn();
  $activeSurveys  = (int)$db->query("SELECT COUNT(*) FROM surveys WHERE status='active'")->fetchColumn();
  ?>
  <div class="grid grid-cols-3 gap-4 max-w-lg">
    <div class="card p-4 text-center"><p class="text-xl font-black text-white"><?= count($surveys) ?></p><p class="text-xs text-slate-500 mt-0.5">Total Surveys</p></div>
    <div class="card p-4 text-center"><p class="text-xl font-black text-emerald-400"><?= $activeSurveys ?></p><p class="text-xs text-slate-500 mt-0.5">Active</p></div>
    <div class="card p-4 text-center"><p class="text-xl font-black text-white"><?= number_format($totalResponses) ?></p><p class="text-xs text-slate-500 mt-0.5">Responses</p></div>
  </div>

  <!-- Survey cards -->
  <?php if (empty($surveys)): ?>
  <div class="card text-center py-16">
    <div class="text-5xl mb-4">🔬</div>
    <h3 class="text-white font-bold mb-2">No surveys yet</h3>
    <p class="text-slate-400 text-sm mb-6">Create your first survey to gather audience insights.</p>
    <a href="/admin/survey_create.php" class="btn btn-primary">Create Survey</a>
  </div>
  <?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($surveys as $survey): ?>
    <div class="card p-5 flex flex-col gap-4 group">
      <div class="flex items-start justify-between">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <h3 class="font-bold text-white"><?= e($survey['name']) ?></h3>
            <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $statusColors[$survey['status']] ?? '' ?>"><?= ucfirst(e($survey['status'])) ?></span>
          </div>
          <?php if ($survey['description']): ?><p class="text-xs text-slate-500"><?= e(mb_strimwidth($survey['description'],0,70,'…')) ?></p><?php endif; ?>
        </div>
      </div>

      <div class="flex gap-4 text-center">
        <div><p class="text-lg font-black text-white"><?= number_format((int)$survey['response_count']) ?></p><p class="text-xs text-slate-500">Responses</p></div>
        <div><p class="text-lg font-black text-white"><?= date('M j', strtotime($survey['created_at'])) ?></p><p class="text-xs text-slate-500">Created</p></div>
      </div>

      <div class="flex gap-2">
        <a href="/admin/survey_view.php?id=<?= $survey['id'] ?>" class="btn btn-secondary text-xs flex-1 justify-center">📊 Results</a>
        <a href="/admin/survey_create.php?id=<?= $survey['id'] ?>" class="btn btn-secondary text-xs justify-center">✏️</a>
        <form method="post" style="display:inline">
    <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= $survey['id'] ?>">
          <button class="btn text-xs <?= $survey['status']==='active'?'text-amber-400':'text-emerald-400' ?> bg-white/5 border border-white/10" title="<?= $survey['status']==='active'?'Close':'Activate' ?>">
            <?= $survey['status']==='active'?'⏸':'▶' ?>
          </button>
        </form>
      </div>

      <!-- Survey public link -->
      <?php if ($survey['status'] === 'active'): ?>
      <div class="pt-2 border-t border-white/5">
        <p class="text-xs text-slate-500 mb-1">Public Survey Link</p>
        <div class="flex items-center gap-2">
          <input type="text" value="<?= e($appUrl.'/survey.php?token='.$survey['access_token']) ?>" readonly class="text-xs bg-slate-900 border border-white/10 rounded-lg px-2 py-1.5 flex-1 text-slate-400 focus:outline-none truncate">
          <button type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)" class="text-xs text-indigo-400 px-2 py-1.5 rounded-lg bg-indigo-900/20 border border-indigo-700/20">Copy</button>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
