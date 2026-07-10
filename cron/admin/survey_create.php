<?php
$id = (int)($_GET['id'] ?? 0);
$survey = null;
$questions = [];

if ($id) {
    $s = $db->prepare("SELECT * FROM surveys WHERE id=?");
    $s->execute([$id]);
    $survey = $s->fetch();
    if (!$survey) {
        flash('error', 'Survey not found.');
        sc_redirect('/admin/research.php');
    }
    $pageTitle = 'Edit Survey: ' . $survey['name'];
    $sq = $db->prepare("SELECT * FROM survey_questions WHERE survey_id=? ORDER BY question_order");
    $sq->execute([$id]);
    $questions = $sq->fetchAll();
} else {
    $pageTitle = 'Create Survey';
}

require_once __DIR__ . '/../includes/header.php';

// POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::checkCsrf()) {
    $name   = trim($_POST['name'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'draft';

    if (!in_array($status, ['draft', 'active', 'closed', 'archived'])) $status = 'draft';

    if (empty($name)) {
        flash('error', 'Survey name is required.');
        sc_redirect($_SERVER['PHP_SELF'] . ($id ? '?id=' . $id : ''));
    }

    if ($id) {
        $db->prepare("UPDATE surveys SET name=?,description=?,status=?,updated_at=NOW() WHERE id=?")
           ->execute([$name, $desc, $status, $id]);
    } else {
        $db->prepare("INSERT INTO surveys (name,description,status) VALUES (?,?,?)")
           ->execute([$name, $desc, $status]);
        $id = (int)$db->lastInsertId();
    }

    // Rebuild questions
    $db->prepare("DELETE FROM survey_questions WHERE survey_id=?")->execute([$id]);
    $qTypes    = $_POST['q_type']     ?? [];
    $qTexts    = $_POST['q_text']     ?? [];
    $qRequired = $_POST['q_required'] ?? [];
    $qOptions  = $_POST['q_options']  ?? [];

    foreach ($qTypes as $i => $type) {
        $text = trim($qTexts[$i] ?? '');
        if (empty($text)) continue;

        $opts = null;
        if (in_array($type, ['multiple_choice', 'checkboxes', 'dropdown'])) {
            $rawOpts = array_filter(array_map('trim', explode("\n", $qOptions[$i] ?? '')));
            $opts = json_encode(array_values($rawOpts));
        } elseif ($type === 'rating') {
            $scale = (int)($qOptions[$i] ?? 5);
            if (!in_array($scale, [5, 10])) $scale = 5;
            $opts = json_encode(['scale' => $scale]);
        } elseif ($type === 'nps') {
            $opts = json_encode(['min' => 0, 'max' => 10]);
        } elseif ($type === 'yes_no') {
            $opts = json_encode(['Yes', 'No']);
        }

        $db->prepare("INSERT INTO survey_questions (survey_id,question_order,type,question_text,is_required,options) VALUES (?,?,?,?,?,?)")
           ->execute([$id, $i, $type, $text, isset($qRequired[$i]) ? 1 : 0, $opts]);
    }

    flash('success', 'Survey saved successfully.');
    sc_redirect('/admin/research.php');
}

$flash = getFlash();

// Prepare Alpine-compatible question data
$alpineQuestions = array_map(function ($q) {
    $opts = null;
    if ($q['options']) {
        $decoded = json_decode($q['options'], true);
        if ($q['type'] === 'rating') {
            $opts = $decoded['scale'] ?? 5;
        } elseif ($q['type'] === 'nps') {
            $opts = null;
        } else {
            $opts = is_array($decoded) ? implode("\n", $decoded) : '';
        }
    }
    return [
        'type'     => $q['type'],
        'text'     => $q['question_text'],
        'required' => (bool)$q['is_required'],
        'options'  => $opts,
    ];
}, $questions);

$questionTypeDefs = [
    ['type' => 'multiple_choice', 'label' => 'Multiple Choice',  'icon' => 'radio',   'desc' => 'Single answer from options'],
    ['type' => 'checkboxes',      'label' => 'Checkboxes',       'icon' => 'check',   'desc' => 'Multiple answers allowed'],
    ['type' => 'rating',          'label' => 'Rating Scale',     'icon' => 'star',    'desc' => 'Star rating 1–5 or 1–10'],
    ['type' => 'short_text',      'label' => 'Short Text',       'icon' => 'text-s',  'desc' => 'Single line text response'],
    ['type' => 'long_text',       'label' => 'Long Text',        'icon' => 'text-l',  'desc' => 'Multi-line paragraph response'],
    ['type' => 'yes_no',          'label' => 'Yes / No',         'icon' => 'yesno',   'desc' => 'Simple binary question'],
    ['type' => 'nps',             'label' => 'NPS Score',        'icon' => 'nps',     'desc' => '0–10 Net Promoter Score'],
    ['type' => 'dropdown',        'label' => 'Dropdown',         'icon' => 'dropdown','desc' => 'Select from a dropdown list'],
    ['type' => 'email',           'label' => 'Email Address',    'icon' => 'email',   'desc' => 'Validated email input'],
    ['type' => 'date',            'label' => 'Date',             'icon' => 'date',    'desc' => 'Date picker input'],
];
?>

<?php if ($flash): ?>
<div class="mb-6 px-4 py-3 rounded-xl border text-sm font-medium
    <?= $flash['type'] === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-red-500/10 border-red-500/30 text-red-400' ?>">
    <?= $flash['message'] ?>
</div>
<?php endif; ?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-slate-500 mb-6">
    <a href="/admin/research.php" class="hover:text-slate-300 transition-colors flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Market Research
    </a>
    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-slate-300"><?= $id ? 'Edit Survey' : 'Create Survey' ?></span>
</div>

<!-- Page heading -->
<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-bold text-slate-100"><?= $id ? 'Edit Survey' : 'Create New Survey' ?></h1>
        <p class="text-slate-500 text-sm mt-0.5">Build your survey by adding questions below, then save to publish.</p>
    </div>
</div>

<!-- Main Alpine Component -->
<div x-data="{
    questions: <?= json_encode($alpineQuestions) ?>,
    showTypePicker: false,
    activeTab: 'builder',
    addQuestion(type) {
        var defaultOpts = null;
        if (type === 'multiple_choice' || type === 'checkboxes' || type === 'dropdown') defaultOpts = 'Option 1\nOption 2\nOption 3';
        if (type === 'rating') defaultOpts = 5;
        this.questions.push({ type: type, text: '', required: false, options: defaultOpts });
        this.showTypePicker = false;
    },
    removeQuestion(i) { this.questions.splice(i, 1); },
    moveUp(i) { if (i > 0) { var t = this.questions.splice(i, 1)[0]; this.questions.splice(i - 1, 0, t); } },
    moveDown(i) { if (i < this.questions.length - 1) { var t = this.questions.splice(i, 1)[0]; this.questions.splice(i + 1, 0, t); } },
    typeLabel(type) {
        var labels = { multiple_choice: 'Multiple Choice', checkboxes: 'Checkboxes', rating: 'Rating Scale', short_text: 'Short Text', long_text: 'Long Text', yes_no: 'Yes / No', nps: 'NPS Score', dropdown: 'Dropdown', email: 'Email', date: 'Date' };
        return labels[type] || type;
    },
    typeColor(type) {
        var colors = { multiple_choice: 'text-indigo-400 bg-indigo-500/15', checkboxes: 'text-violet-400 bg-violet-500/15', rating: 'text-amber-400 bg-amber-500/15', short_text: 'text-cyan-400 bg-cyan-500/15', long_text: 'text-cyan-400 bg-cyan-500/15', yes_no: 'text-emerald-400 bg-emerald-500/15', nps: 'text-rose-400 bg-rose-500/15', dropdown: 'text-blue-400 bg-blue-500/15', email: 'text-purple-400 bg-purple-500/15', date: 'text-orange-400 bg-orange-500/15' };
        return colors[type] || 'text-slate-400 bg-slate-700/40';
    },
    hasOptions(type) { return ['multiple_choice','checkboxes','dropdown'].includes(type); },
    isRating(type) { return type === 'rating'; },
    isNPS(type) { return type === 'nps'; }
}" class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    <!-- Left Column: Survey Details + Builder -->
    <div class="xl:col-span-2 space-y-5">
        <form id="surveyForm" method="POST">
            <?= Auth::csrfField() ?>

            <!-- Survey Details Card -->
            <div class="bg-[#111827] border border-slate-800/60 rounded-2xl p-6 mb-5">
                <h2 class="text-base font-semibold text-slate-200 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Survey Details
                </h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Survey Name <span class="text-red-400">*</span></label>
                        <input type="text" name="name" value="<?= e($survey['name'] ?? '') ?>" placeholder="e.g. Customer Satisfaction Q4 2025" required
                               class="w-full bg-slate-800/80 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 text-sm placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Description <span class="text-slate-600 font-normal">(optional)</span></label>
                        <textarea name="description" rows="2" placeholder="Brief description shown to respondents"
                                  class="w-full bg-slate-800/80 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 text-sm placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors resize-none"><?= e($survey['description'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Status</label>
                        <select name="status" class="bg-slate-800/80 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors">
                            <?php foreach (['draft' => 'Draft (not visible)', 'active' => 'Active (accepting responses)', 'closed' => 'Closed (no new responses)', 'archived' => 'Archived'] as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= ($survey['status'] ?? 'draft') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Question Builder -->
            <div class="bg-[#111827] border border-slate-800/60 rounded-2xl p-6">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-base font-semibold text-slate-200 flex items-center gap-2">
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Questions
                        <span class="text-slate-500 text-xs font-normal" x-text="'(' + questions.length + ')'"></span>
                    </h2>
                    <button type="button" @click="showTypePicker=!showTypePicker"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Question
                    </button>
                </div>

                <!-- Type Picker Panel -->
                <div x-show="showTypePicker" x-transition class="mb-5 p-4 bg-slate-800/60 border border-slate-700/60 rounded-xl" style="display:none">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Choose Question Type</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                        <?php foreach ($questionTypeDefs as $qtd): ?>
                        <button type="button" @click="addQuestion('<?= $qtd['type'] ?>')"
                                class="flex flex-col items-center gap-1.5 p-3 rounded-xl border border-slate-700/60 hover:border-indigo-500/50 hover:bg-indigo-500/10 text-slate-400 hover:text-indigo-300 transition-all text-center group">
                            <?php
                            $icons = [
                                'radio'    => '<circle cx="12" cy="12" r="9" stroke-width="1.5"/><circle cx="12" cy="12" r="3" fill="currentColor"/>',
                                'check'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                                'star'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>',
                                'text-s'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h8"/>',
                                'text-l'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h10"/>',
                                'yesno'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
                                'nps'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
                                'dropdown' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>',
                                'email'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
                                'date'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
                            ];
                            ?>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $icons[$qtd['icon']] ?></svg>
                            <span class="text-xs font-medium leading-tight"><?= $qtd['label'] ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" @click="showTypePicker=false" class="mt-3 text-xs text-slate-600 hover:text-slate-400 transition-colors">✕ Cancel</button>
                </div>

                <!-- Question List -->
                <div class="space-y-4">
                    <!-- Empty state -->
                    <div x-show="questions.length === 0" class="py-12 text-center border-2 border-dashed border-slate-700/60 rounded-xl">
                        <svg class="w-10 h-10 text-slate-700 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-slate-600 text-sm">No questions yet. Click <strong class="text-slate-500">Add Question</strong> to get started.</p>
                    </div>

                    <!-- Questions loop -->
                    <template x-for="(q, i) in questions" :key="i">
                        <div class="border border-slate-700/60 rounded-xl overflow-hidden bg-slate-800/30 hover:border-indigo-500/30 transition-colors">
                            <!-- Question header bar -->
                            <div class="flex items-center gap-3 px-4 py-2.5 border-b border-slate-700/40 bg-slate-800/50">
                                <div class="flex flex-col gap-0.5">
                                    <button type="button" @click="moveUp(i)" class="text-slate-600 hover:text-slate-300 transition-colors leading-none" :disabled="i===0">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                                    </button>
                                    <button type="button" @click="moveDown(i)" class="text-slate-600 hover:text-slate-300 transition-colors leading-none" :disabled="i===questions.length-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </div>
                                <span class="text-slate-500 text-xs font-mono" x-text="'Q' + (i+1)"></span>
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="typeColor(q.type)" x-text="typeLabel(q.type)"></span>
                                <div class="ml-auto flex items-center gap-2">
                                    <label class="flex items-center gap-1.5 text-xs text-slate-500 cursor-pointer">
                                        <input type="checkbox" :name="'q_required['+i+']'" x-model="q.required"
                                               class="rounded border-slate-600 bg-slate-700 text-indigo-500 focus:ring-indigo-500 focus:ring-offset-slate-900">
                                        Required
                                    </label>
                                    <button type="button" @click="removeQuestion(i)" class="p-1 rounded hover:bg-red-900/30 text-slate-600 hover:text-red-400 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                                <!-- Hidden inputs for type and required -->
                                <input type="hidden" :name="'q_type['+i+']'" :value="q.type">
                            </div>

                            <!-- Question body -->
                            <div class="p-4 space-y-3">
                                <div>
                                    <input type="text" :name="'q_text['+i+']'" x-model="q.text"
                                           placeholder="Enter your question here…" :required="q.required"
                                           class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-slate-200 text-sm placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors">
                                </div>

                                <!-- Options for MC / Checkboxes / Dropdown -->
                                <div x-show="hasOptions(q.type)">
                                    <label class="block text-xs text-slate-500 mb-1.5">Answer options <span class="text-slate-600">(one per line)</span></label>
                                    <textarea :name="'q_options['+i+']'" x-model="q.options" rows="4"
                                              placeholder="Option 1&#10;Option 2&#10;Option 3"
                                              class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-slate-200 text-sm placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors font-mono resize-none"></textarea>
                                </div>

                                <!-- Rating scale selector -->
                                <div x-show="isRating(q.type)">
                                    <label class="block text-xs text-slate-500 mb-1.5">Rating scale</label>
                                    <select :name="'q_options['+i+']'" x-model="q.options"
                                            class="bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-slate-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                        <option value="5">1 – 5 stars</option>
                                        <option value="10">1 – 10 stars</option>
                                    </select>
                                </div>

                                <!-- NPS info -->
                                <div x-show="isNPS(q.type)" class="px-3 py-2 rounded-lg bg-slate-900/40 border border-slate-700/40 text-xs text-slate-500">
                                    NPS question: respondents select a score from 0 (Not at all likely) to 10 (Extremely likely).
                                    <input type="hidden" :name="'q_options['+i+']'" value="">
                                </div>

                                <!-- Other types: no extra options needed -->
                                <div x-show="!hasOptions(q.type) && !isRating(q.type) && !isNPS(q.type)">
                                    <input type="hidden" :name="'q_options['+i+']'" value="">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Add another question button (bottom) -->
                <div class="mt-5 text-center" x-show="questions.length > 0">
                    <button type="button" @click="showTypePicker=!showTypePicker"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-xl border border-dashed border-slate-700 text-slate-500 hover:border-indigo-500/50 hover:text-indigo-400 text-sm transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Another Question
                    </button>
                </div>
            </div>

            <!-- Save buttons (within form) -->
            <div class="flex items-center justify-between mt-5 bg-[#111827] border border-slate-800/60 rounded-2xl px-6 py-4">
                <a href="/admin/research.php" class="text-slate-500 hover:text-slate-300 text-sm flex items-center gap-1 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Cancel
                </a>
                <div class="flex items-center gap-3">
                    <button type="submit" name="save_draft" class="px-5 py-2.5 rounded-xl border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700/50 transition-colors" onclick="document.querySelector('select[name=status]').value='draft'">
                        Save as Draft
                    </button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold transition-colors flex items-center gap-2 shadow-lg shadow-indigo-900/30">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Save Survey
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Right Column: Preview -->
    <div class="xl:col-span-1">
        <div class="sticky top-6 space-y-4">
            <!-- Preview card -->
            <div class="bg-[#111827] border border-slate-800/60 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-800/60 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-300 flex items-center gap-2">
                        <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Live Preview
                    </h3>
                    <span class="text-xs text-slate-600">Respondent view</span>
                </div>

                <div class="p-5 max-h-[calc(100vh-16rem)] overflow-y-auto">
                    <!-- Survey meta -->
                    <div class="mb-4 pb-4 border-b border-slate-800/40">
                        <h4 class="text-sm font-bold text-slate-200 mb-1">Survey Name</h4>
                        <p class="text-slate-600 text-xs">Description appears here</p>
                    </div>

                    <!-- Questions preview -->
                    <div class="space-y-5">
                        <template x-for="(q, i) in questions" :key="'prev-'+i">
                            <div class="pb-4 border-b border-slate-800/30 last:border-0 last:pb-0">
                                <p class="text-xs font-semibold text-slate-300 mb-2">
                                    <span x-text="(i+1) + '. ' + (q.text || 'Your question here…')"></span>
                                    <span x-show="q.required" class="text-red-400 ml-0.5">*</span>
                                </p>

                                <!-- Multiple choice preview -->
                                <div x-show="q.type === 'multiple_choice'" class="space-y-1.5">
                                    <template x-if="q.options">
                                        <template x-for="opt in (q.options || '').split('\n').filter(o => o.trim())" :key="opt">
                                            <div class="flex items-center gap-2">
                                                <div class="w-3.5 h-3.5 rounded-full border border-slate-600 bg-slate-800 flex-shrink-0"></div>
                                                <span class="text-xs text-slate-400" x-text="opt.trim()"></span>
                                            </div>
                                        </template>
                                    </template>
                                    <template x-if="!q.options || !q.options.trim()">
                                        <p class="text-xs text-slate-600 italic">Add options above…</p>
                                    </template>
                                </div>

                                <!-- Checkboxes preview -->
                                <div x-show="q.type === 'checkboxes'" class="space-y-1.5">
                                    <template x-if="q.options">
                                        <template x-for="opt in (q.options || '').split('\n').filter(o => o.trim())" :key="opt">
                                            <div class="flex items-center gap-2">
                                                <div class="w-3.5 h-3.5 rounded border border-slate-600 bg-slate-800 flex-shrink-0"></div>
                                                <span class="text-xs text-slate-400" x-text="opt.trim()"></span>
                                            </div>
                                        </template>
                                    </template>
                                </div>

                                <!-- Dropdown preview -->
                                <div x-show="q.type === 'dropdown'">
                                    <div class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-slate-500 flex items-center justify-between">
                                        <span>Select an option…</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </div>
                                </div>

                                <!-- Rating preview -->
                                <div x-show="q.type === 'rating'" class="flex gap-1">
                                    <template x-for="s in parseInt(q.options || 5)" :key="s">
                                        <span class="text-xl text-slate-700">★</span>
                                    </template>
                                </div>

                                <!-- NPS preview -->
                                <div x-show="q.type === 'nps'" class="flex gap-0.5 flex-wrap">
                                    <template x-for="n in 11" :key="n">
                                        <div class="w-6 h-6 rounded text-center text-xs flex items-center justify-center border border-slate-700 text-slate-500" :style="'background: hsl(' + (n*12) + ', 60%, 20%)'">
                                            <span x-text="n-1"></span>
                                        </div>
                                    </template>
                                </div>

                                <!-- Yes/No preview -->
                                <div x-show="q.type === 'yes_no'" class="flex gap-2">
                                    <div class="px-4 py-1.5 rounded-lg border border-emerald-600/40 text-emerald-400 text-xs font-medium">Yes</div>
                                    <div class="px-4 py-1.5 rounded-lg border border-red-600/40 text-red-400 text-xs font-medium">No</div>
                                </div>

                                <!-- Short text preview -->
                                <div x-show="q.type === 'short_text'">
                                    <div class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-slate-600">Your answer…</div>
                                </div>

                                <!-- Long text preview -->
                                <div x-show="q.type === 'long_text'">
                                    <div class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-xs text-slate-600 h-12">Your answer…</div>
                                </div>

                                <!-- Email preview -->
                                <div x-show="q.type === 'email'">
                                    <div class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-slate-600">email@example.com</div>
                                </div>

                                <!-- Date preview -->
                                <div x-show="q.type === 'date'">
                                    <div class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-slate-600">YYYY-MM-DD</div>
                                </div>
                            </div>
                        </template>

                        <div x-show="questions.length === 0" class="py-6 text-center">
                            <p class="text-slate-700 text-xs">Questions will appear here as you add them</p>
                        </div>
                    </div>

                    <!-- Submit button preview -->
                    <div x-show="questions.length > 0" class="mt-5 pt-4 border-t border-slate-800/40">
                        <div class="w-full py-2 rounded-xl text-center text-xs font-semibold text-white" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">Submit Survey</div>
                    </div>
                </div>
            </div>

            <!-- Tips Card -->
            <div class="bg-[#111827] border border-slate-800/60 rounded-xl p-4">
                <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Survey Tips</h4>
                <ul class="space-y-2 text-xs text-slate-500">
                    <li class="flex gap-2"><span class="text-indigo-400">→</span> Keep surveys under 10 questions for best completion rates.</li>
                    <li class="flex gap-2"><span class="text-indigo-400">→</span> Use NPS for loyalty tracking and Rating for satisfaction.</li>
                    <li class="flex gap-2"><span class="text-indigo-400">→</span> Add 1–2 open text questions for qualitative insights.</li>
                    <li class="flex gap-2"><span class="text-indigo-400">→</span> Mark only critical questions as Required.</li>
                </ul>
            </div>
        </div>
    </div>

</div><!-- end x-data -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
