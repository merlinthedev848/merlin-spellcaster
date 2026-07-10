<?php
$pageTitle = 'Market Research';
require_once __DIR__ . '/../includes/header.php';

// Create tables if not exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS surveys (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, status VARCHAR(20) DEFAULT 'draft', list_id INT DEFAULT NULL, campaign_id INT DEFAULT NULL, response_count INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS survey_questions (id INT AUTO_INCREMENT PRIMARY KEY, survey_id INT NOT NULL, question_order INT DEFAULT 0, type VARCHAR(50) NOT NULL, question_text TEXT NOT NULL, is_required TINYINT(1) DEFAULT 0, options TEXT DEFAULT NULL, FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS survey_responses (id INT AUTO_INCREMENT PRIMARY KEY, survey_id INT NOT NULL, subscriber_id INT DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, completed_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("CREATE TABLE IF NOT EXISTS survey_answers (id INT AUTO_INCREMENT PRIMARY KEY, response_id INT NOT NULL, question_id INT NOT NULL, answer_text TEXT DEFAULT NULL, FOREIGN KEY (response_id) REFERENCES survey_responses(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::checkCsrf()) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        $db->prepare("DELETE FROM surveys WHERE id=?")->execute([$id]);
        flash('success', 'Survey deleted.');
    } elseif ($action === 'duplicate' && $id) {
        $s = $db->prepare("SELECT * FROM surveys WHERE id=?");
        $s->execute([$id]);
        $orig = $s->fetch();
        if ($orig) {
            $db->prepare("INSERT INTO surveys (name,description,status,list_id) VALUES (?,?,'draft',?)")
               ->execute([$orig['name'] . ' (Copy)', $orig['description'], $orig['list_id']]);
            $newId = $db->lastInsertId();
            $qs = $db->prepare("SELECT * FROM survey_questions WHERE survey_id=? ORDER BY question_order");
            $qs->execute([$id]);
            foreach ($qs->fetchAll() as $q) {
                $db->prepare("INSERT INTO survey_questions (survey_id,question_order,type,question_text,is_required,options) VALUES (?,?,?,?,?,?)")
                   ->execute([$newId, $q['question_order'], $q['type'], $q['question_text'], $q['is_required'], $q['options']]);
            }
            flash('success', 'Survey duplicated.');
        }
    } elseif ($action === 'change_status' && $id) {
        $newStatus = $_POST['new_status'] ?? 'draft';
        if (in_array($newStatus, ['draft', 'active', 'closed', 'archived'])) {
            $db->prepare("UPDATE surveys SET status=? WHERE id=?")->execute([$newStatus, $id]);
            flash('success', 'Status updated.');
        }
    } elseif ($action === 'distribute' && $id) {
        $s = $db->prepare("SELECT * FROM surveys WHERE id=?");
        $s->execute([$id]);
        $survey = $s->fetch();
        $listId = (int)($_POST['dist_list_id'] ?? 0);
        if ($survey && $listId) {
            $appUrl = getSetting('app_url', 'http://localhost');
            $surveyUrl = $appUrl . '/survey.php?id=' . $id . '&s={{subscriber_id}}&t={{token}}';
            $bodyHtml = '<p>Hi {{first_name}},</p><p>We\'d love to hear your thoughts! Please take a few minutes to complete our survey:</p><p><a href="' . $surveyUrl . '" style="display:inline-block;background:#6366f1;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:600">Take Survey</a></p><p>It only takes a few minutes and your feedback is invaluable to us.</p>';
            $db->prepare("INSERT INTO campaigns (name,subject,from_name,from_email,body_html,status,type) VALUES (?,?,?,?,?,'draft','regular')")
               ->execute(['Survey: ' . $survey['name'], "We'd love your feedback: " . $survey['name'], getSetting('smtp_from_name'), getSetting('smtp_from_email'), $bodyHtml]);
            $cid = $db->lastInsertId();
            $db->prepare("INSERT INTO campaign_lists (campaign_id,list_id) VALUES (?,?)")->execute([$cid, $listId]);
            $db->prepare("UPDATE surveys SET campaign_id=?,list_id=? WHERE id=?")->execute([$cid, $listId, $id]);
            flash('success', 'Distribution campaign created! <a href="/admin/campaign_view.php?id=' . $cid . '" class="underline font-semibold">View Campaign →</a>');
        } else {
            flash('error', 'Please select a mailing list.');
        }
    }
    sc_redirect('/admin/research.php');
}

// Load data
$surveys = $db->query("SELECT s.*, l.name as list_name, (SELECT COUNT(*) FROM survey_questions WHERE survey_id=s.id) as q_count FROM surveys s LEFT JOIN lists l ON s.list_id=l.id ORDER BY s.created_at DESC")->fetchAll();
$totalSurveys = (int)$db->query("SELECT COUNT(*) FROM surveys")->fetchColumn();
$totalResponses = (int)$db->query("SELECT COALESCE(SUM(response_count),0) FROM surveys")->fetchColumn();
$activeSurveys = (int)$db->query("SELECT COUNT(*) FROM surveys WHERE status='active'")->fetchColumn();
$avgResponses = $totalSurveys > 0 ? round($totalResponses / $totalSurveys, 1) : 0;
$allLists = $db->query("SELECT * FROM lists ORDER BY name")->fetchAll();

// Insights data – active/closed surveys with MC questions
$insightSurveys = [];
foreach ($surveys as $sv) {
    if (!in_array($sv['status'], ['active', 'closed'])) continue;
    $mcQ = $db->prepare("SELECT * FROM survey_questions WHERE survey_id=? AND type IN ('multiple_choice','yes_no','dropdown') ORDER BY question_order LIMIT 1");
    $mcQ->execute([$sv['id']]);
    $mcQuestion = $mcQ->fetch();

    $textQ = $db->prepare("SELECT sa.answer_text, sr.completed_at FROM survey_answers sa JOIN survey_responses sr ON sa.response_id=sr.id JOIN survey_questions sq ON sa.question_id=sq.id WHERE sq.survey_id=? AND sq.type IN ('short_text','long_text') AND sa.answer_text IS NOT NULL AND sa.answer_text != '' ORDER BY sr.completed_at DESC LIMIT 5");
    $textQ->execute([$sv['id']]);
    $textAnswers = $textQ->fetchAll();

    $insightSurveys[] = [
        'survey'      => $sv,
        'mcQuestion'  => $mcQuestion ?: null,
        'textAnswers' => $textAnswers,
    ];
}

$flash = getFlash();
?>

<!-- Flash message -->
<?php if ($flash): ?>
<div class="mb-6 px-4 py-3 rounded-xl border text-sm font-medium
    <?= $flash['type'] === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-red-500/10 border-red-500/30 text-red-400' ?>">
    <?= $flash['message'] ?>
</div>
<?php endif; ?>

<!-- Hero Banner -->
<div class="relative rounded-2xl overflow-hidden mb-8" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4c1d95 100%);">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-64 h-64 rounded-full bg-violet-400 -translate-y-1/2 translate-x-1/4 blur-3xl"></div>
        <div class="absolute bottom-0 left-0 w-48 h-48 rounded-full bg-indigo-400 translate-y-1/2 -translate-x-1/4 blur-3xl"></div>
    </div>
    <div class="relative px-8 py-10 flex flex-col md:flex-row items-start md:items-center gap-6">
        <div class="flex-shrink-0 w-16 h-16 rounded-2xl flex items-center justify-center" style="background: rgba(99,102,241,0.3);">
            <svg class="w-9 h-9 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
            </svg>
        </div>
        <div class="flex-1">
            <h1 class="text-3xl font-bold text-white mb-1">Market Research</h1>
            <p class="text-indigo-200 text-base">Build surveys, distribute to your audience, and unlock insights from real subscriber data.</p>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
            <a href="#insights" class="px-4 py-2.5 rounded-xl border border-indigo-400/40 text-indigo-200 text-sm font-medium hover:bg-indigo-500/20 transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                View Analytics
            </a>
            <a href="/admin/survey_create.php" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold transition-colors flex items-center gap-2 shadow-lg shadow-indigo-900/40">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create New Survey
            </a>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <?php
    $stats = [
        ['label' => 'Total Surveys',       'value' => $totalSurveys,  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>', 'color' => 'indigo'],
        ['label' => 'Total Responses',     'value' => number_format($totalResponses), 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>', 'color' => 'violet'],
        ['label' => 'Active Surveys',      'value' => $activeSurveys, 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728M12 12a3 3 0 100-6 3 3 0 000 6z"/>', 'color' => 'emerald'],
        ['label' => 'Avg Responses/Survey','value' => $avgResponses,  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>', 'color' => 'cyan'],
    ];
    $colorMap = ['indigo'=>['bg'=>'bg-indigo-500/10','text'=>'text-indigo-400','border'=>'border-indigo-500/20'], 'violet'=>['bg'=>'bg-violet-500/10','text'=>'text-violet-400','border'=>'border-violet-500/20'], 'emerald'=>['bg'=>'bg-emerald-500/10','text'=>'text-emerald-400','border'=>'border-emerald-500/20'], 'cyan'=>['bg'=>'bg-cyan-500/10','text'=>'text-cyan-400','border'=>'border-cyan-500/20']];
    foreach ($stats as $stat):
        $c = $colorMap[$stat['color']];
    ?>
    <div class="bg-[#111827] border border-slate-800/60 rounded-xl p-5">
        <div class="flex items-center justify-between mb-3">
            <span class="text-slate-500 text-sm"><?= $stat['label'] ?></span>
            <div class="w-8 h-8 rounded-lg <?= $c['bg'] ?> <?= $c['border'] ?> border flex items-center justify-center">
                <svg class="w-4 h-4 <?= $c['text'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $stat['icon'] ?></svg>
            </div>
        </div>
        <div class="text-2xl font-bold text-slate-100"><?= $stat['value'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Surveys Grid -->
<div x-data="{ distributeId: null, distributeSurveyName: '', showDeleteId: null }">

    <?php if (empty($surveys)): ?>
    <div class="bg-[#111827] border border-slate-800/60 rounded-2xl p-16 text-center">
        <div class="w-20 h-20 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center mx-auto mb-5">
            <svg class="w-10 h-10 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
        </div>
        <h3 class="text-xl font-semibold text-slate-200 mb-2">No surveys yet</h3>
        <p class="text-slate-500 mb-6 max-w-md mx-auto">Create your first survey to start gathering insights from your subscribers.</p>
        <a href="/admin/survey_create.php" class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-semibold transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Create Your First Survey
        </a>
    </div>
    <?php else: ?>

    <div class="flex items-center justify-between mb-5">
        <h2 class="text-lg font-semibold text-slate-200">All Surveys <span class="text-slate-500 font-normal text-sm ml-1">(<?= $totalSurveys ?>)</span></h2>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-10">
        <?php foreach ($surveys as $sv):
            $statusConfig = [
                'draft'    => ['label' => 'Draft',    'bg' => 'bg-slate-700/60',   'text' => 'text-slate-300',  'dot' => 'bg-slate-400'],
                'active'   => ['label' => 'Active',   'bg' => 'bg-emerald-500/15', 'text' => 'text-emerald-400','dot' => 'bg-emerald-400'],
                'closed'   => ['label' => 'Closed',   'bg' => 'bg-amber-500/15',   'text' => 'text-amber-400',  'dot' => 'bg-amber-400'],
                'archived' => ['label' => 'Archived', 'bg' => 'bg-slate-700/40',   'text' => 'text-slate-500',  'dot' => 'bg-slate-500'],
            ];
            $sc = $statusConfig[$sv['status']] ?? $statusConfig['draft'];
            $maxResp = max((int)$sv['response_count'], 100);
            $pct = $maxResp > 0 ? min(100, round((int)$sv['response_count'] / $maxResp * 100)) : 0;
        ?>
        <div class="bg-[#111827] border border-slate-800/60 rounded-2xl p-6 flex flex-col gap-4 hover:border-indigo-500/30 transition-colors group">
            <!-- Header -->
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium <?= $sc['bg'] ?> <?= $sc['text'] ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?>"></span>
                            <?= $sc['label'] ?>
                        </span>
                        <?php if ($sv['response_count'] > 0): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                            <?= number_format($sv['response_count']) ?> responses
                        </span>
                        <?php endif; ?>
                    </div>
                    <h3 class="text-slate-100 font-semibold text-base leading-tight truncate"><?= e($sv['name']) ?></h3>
                    <?php if ($sv['description']): ?>
                    <p class="text-slate-500 text-sm mt-1 line-clamp-2"><?= e($sv['description']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Meta -->
            <div class="flex items-center gap-4 text-xs text-slate-500">
                <span class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?= $sv['q_count'] ?> questions
                </span>
                <?php if ($sv['list_name']): ?>
                <span class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    <?= e($sv['list_name']) ?>
                </span>
                <?php endif; ?>
                <span class="flex items-center gap-1 ml-auto">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?= timeAgo($sv['created_at']) ?>
                </span>
            </div>

            <!-- Progress bar -->
            <div>
                <div class="flex items-center justify-between text-xs text-slate-500 mb-1.5">
                    <span>Response progress</span>
                    <span><?= number_format($sv['response_count']) ?> / 100+ target</span>
                </div>
                <div class="h-1.5 bg-slate-800 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500" style="width: <?= $pct ?>%; background: linear-gradient(90deg, #6366f1, #8b5cf6);"></div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 flex-wrap pt-1 border-t border-slate-800/60">
                <a href="/admin/survey_view.php?id=<?= $sv['id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-400 text-xs font-medium transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    View Results
                </a>
                <a href="/admin/survey_create.php?id=<?= $sv['id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700/40 hover:bg-slate-700/80 text-slate-300 text-xs font-medium transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    Edit
                </a>
                <button @click="distributeId=<?= $sv['id'] ?>; distributeSurveyName='<?= addslashes(e($sv['name'])) ?>'" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-600/20 hover:bg-violet-600/40 text-violet-400 text-xs font-medium transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Distribute
                </button>

                <!-- Status change dropdown -->
                <div x-data="{ open: false }" class="relative ml-auto">
                    <button @click="open=!open" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 text-xs font-medium transition-colors">
                        Status
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" @click.outside="open=false" x-transition class="absolute right-0 top-full mt-1 w-36 bg-[#1a2235] border border-slate-700 rounded-xl shadow-xl z-20 overflow-hidden">
                        <?php foreach (['draft','active','closed','archived'] as $ns): ?>
                        <form method="POST">
                            <?= Auth::csrfField() ?>
                            <input type="hidden" name="action" value="change_status">
                            <input type="hidden" name="id" value="<?= $sv['id'] ?>">
                            <input type="hidden" name="new_status" value="<?= $ns ?>">
                            <button type="submit" class="w-full text-left px-3 py-2 text-xs text-slate-300 hover:bg-slate-700/60 transition-colors <?= $sv['status'] === $ns ? 'text-indigo-400 font-semibold' : '' ?>">
                                <?= ucfirst($ns) ?>
                                <?= $sv['status'] === $ns ? ' ✓' : '' ?>
                            </button>
                        </form>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Duplicate -->
                <form method="POST" class="inline">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="duplicate">
                    <input type="hidden" name="id" value="<?= $sv['id'] ?>">
                    <button type="submit" title="Duplicate" class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-slate-200 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                </form>

                <!-- Delete -->
                <button @click="showDeleteId=<?= $sv['id'] ?>" title="Delete" class="p-1.5 rounded-lg bg-slate-800 hover:bg-red-900/40 text-slate-400 hover:text-red-400 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Distribute Modal -->
    <div x-show="distributeId !== null" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="distributeId=null"></div>
        <div class="relative bg-[#111827] border border-slate-700 rounded-2xl shadow-2xl w-full max-w-md p-6" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-violet-500/15 border border-violet-500/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-slate-100">Distribute Survey</h3>
                        <p class="text-slate-500 text-xs">Create an email campaign for this survey</p>
                    </div>
                </div>
                <button @click="distributeId=null" class="p-1.5 rounded-lg hover:bg-slate-700 text-slate-400 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="mb-4 p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/20">
                <p class="text-indigo-300 text-sm font-medium" x-text="'\"' + distributeSurveyName + '\"'"></p>
            </div>
            <form method="POST">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="distribute">
                <input type="hidden" name="id" :value="distributeId">
                <div class="mb-5">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Select Mailing List</label>
                    <?php if (empty($allLists)): ?>
                    <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-sm">
                        No mailing lists found. <a href="/admin/lists.php" class="underline">Create a list first.</a>
                    </div>
                    <?php else: ?>
                    <select name="dist_list_id" required class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-slate-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <option value="">— Select a list —</option>
                        <?php foreach ($allLists as $list): ?>
                        <option value="<?= $list['id'] ?>"><?= e($list['name']) ?> (<?= number_format($list['subscriber_count'] ?? 0) ?> subscribers)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>
                <div class="flex gap-3">
                    <button type="button" @click="distributeId=null" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700/50 transition-colors">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-500 text-white text-sm font-semibold transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        Create Campaign
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div x-show="showDeleteId !== null" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="showDeleteId=null"></div>
        <div class="relative bg-[#111827] border border-red-500/30 rounded-2xl shadow-2xl w-full max-w-sm p-6" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-red-500/15 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h3 class="text-base font-semibold text-slate-100">Delete Survey?</h3>
            </div>
            <p class="text-slate-400 text-sm mb-6">This will permanently delete the survey and all its responses. This action cannot be undone.</p>
            <div class="flex gap-3">
                <button @click="showDeleteId=null" class="flex-1 px-4 py-2 rounded-xl border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700/50 transition-colors">Cancel</button>
                <form method="POST" class="flex-1">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" :value="showDeleteId">
                    <button type="submit" class="w-full px-4 py-2 rounded-xl bg-red-600 hover:bg-red-500 text-white text-sm font-semibold transition-colors">Delete</button>
                </form>
            </div>
        </div>
    </div>

</div><!-- end x-data -->

<!-- Aggregate Insights Section -->
<div id="insights" class="mt-10">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-slate-100">Research Insights</h2>
            <p class="text-slate-500 text-sm mt-0.5">Aggregated results from active and closed surveys</p>
        </div>
    </div>

    <?php if (empty($insightSurveys)): ?>
    <div class="bg-[#111827] border border-slate-800/60 rounded-2xl p-10 text-center">
        <svg class="w-10 h-10 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        <p class="text-slate-500 text-sm">No active or closed surveys to show insights for yet.</p>
    </div>
    <?php else: ?>

    <div class="space-y-6">
        <?php foreach ($insightSurveys as $insight):
            $sv = $insight['survey'];
            $mcQ = $insight['mcQuestion'];
            $textAnswers = $insight['textAnswers'];
        ?>
        <div class="bg-[#111827] border border-slate-800/60 rounded-2xl p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-base font-semibold text-slate-200"><?= e($sv['name']) ?></h3>
                    <p class="text-slate-500 text-xs mt-0.5"><?= number_format($sv['response_count']) ?> total responses</p>
                </div>
                <a href="/admin/survey_view.php?id=<?= $sv['id'] ?>" class="text-xs text-indigo-400 hover:text-indigo-300 flex items-center gap-1">
                    Full Results
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <?php if ($mcQ): ?>
            <?php
                $opts = json_decode($mcQ['options'] ?? '[]', true) ?: [];
                if ($mcQ['type'] === 'yes_no') $opts = ['Yes', 'No'];
                $answerCounts = [];
                foreach ((array)$opts as $opt) {
                    $c = $db->prepare("SELECT COUNT(*) FROM survey_answers WHERE question_id=? AND answer_text=?");
                    $c->execute([$mcQ['id'], $opt]);
                    $answerCounts[$opt] = (int)$c->fetchColumn();
                }
                $totalAns = array_sum($answerCounts);
                $barColors = ['#6366f1','#8b5cf6','#22d3ee','#34d399','#f59e0b','#f87171'];
            ?>
            <div class="mb-5">
                <p class="text-sm font-medium text-slate-300 mb-3"><?= e($mcQ['question_text']) ?></p>
                <div class="space-y-2.5">
                    <?php foreach ($answerCounts as $opt => $cnt):
                        $pctBar = $totalAns > 0 ? round($cnt / $totalAns * 100) : 0;
                        $colorIdx = array_search($opt, array_keys($answerCounts)) % count($barColors);
                        $barColor = $barColors[$colorIdx];
                    ?>
                    <div>
                        <div class="flex items-center justify-between text-xs text-slate-400 mb-1">
                            <span><?= e($opt) ?></span>
                            <span class="font-medium text-slate-300"><?= $cnt ?> <span class="text-slate-500">(<?= $pctBar ?>%)</span></span>
                        </div>
                        <div class="h-2 bg-slate-800 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-700" style="width: <?= $pctBar ?>%; background-color: <?= $barColor ?>;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($answerCounts)): ?>
                    <p class="text-slate-600 text-xs italic">No responses yet for this question.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($textAnswers)): ?>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Recent Text Responses</p>
                <div class="space-y-2">
                    <?php foreach ($textAnswers as $ta): ?>
                    <div class="flex gap-3 p-3 rounded-xl bg-slate-800/40 border border-slate-700/40">
                        <svg class="w-4 h-4 text-indigo-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/></svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-slate-300 text-sm leading-relaxed"><?= e(mb_strimwidth($ta['answer_text'], 0, 200, '…')) ?></p>
                            <p class="text-slate-600 text-xs mt-1"><?= timeAgo($ta['completed_at']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!$mcQ && empty($textAnswers)): ?>
            <p class="text-slate-600 text-sm italic">No responses to display insights for yet.</p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
