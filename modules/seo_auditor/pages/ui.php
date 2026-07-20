<?php
declare(strict_types=1);

$db = Database::getConnection();

// Seed initial directories if none exist
$check = $db->query("SELECT COUNT(*) FROM backlink_submissions")->fetchColumn();
if ($check == 0) {
    $defaultDirs = [
        ['Product Hunt', 'https://producthunt.com'],
        ['BetaList', 'https://betalist.com'],
        ['Hacker News', 'https://news.ycombinator.com'],
        ['Indie Hackers', 'https://indiehackers.com'],
        ['SaaSHub', 'https://saashub.com'],
        ['AlternativeTo', 'https://alternativeto.net'],
        ['Capterra', 'https://capterra.com'],
        ['G2', 'https://g2.com'],
        ['Trustpilot', 'https://trustpilot.com'],
        ['Clutch', 'https://clutch.co']
    ];
    
    $st = $db->prepare("INSERT INTO backlink_submissions (directory_name, directory_url, target_url) VALUES (?, ?, '')");
    foreach ($defaultDirs as $dir) {
        $st->execute([$dir[0], $dir[1]]);
    }
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::checkCsrf()) { flash('error', 'Invalid security token'); sc_redirect($_SERVER['REQUEST_URI']); }

    $action = $_POST['action'] ?? '';

    // Handle SEO Audit Form
    if ($action === 'audit') {
        $url = filter_var($_POST['url'] ?? '', FILTER_VALIDATE_URL);
        if (!$url) {
            flash('error', 'Please enter a valid URL including http:// or https://');
            sc_redirect('/seo-auditor');
        }

        // Fetch URL Content
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!$html || $httpCode >= 400) {
            flash('error', 'Failed to fetch URL. Ensure it is accessible.');
            sc_redirect('/seo-auditor');
        }

        // Parse HTML using DOMDocument
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        
        $title = '';
        $nodes = $dom->getElementsByTagName('title');
        if ($nodes->length > 0) $title = $nodes->item(0)->nodeValue;

        $metaDesc = '';
        $metas = $dom->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            if (strtolower($meta->getAttribute('name')) === 'description') {
                $metaDesc = $meta->getAttribute('content');
                break;
            }
        }

        $h1s = [];
        $h1Nodes = $dom->getElementsByTagName('h1');
        foreach ($h1Nodes as $h1) {
            $h1s[] = trim($h1->nodeValue);
        }

        // Calculate basic word count from body text
        $body = $dom->getElementsByTagName('body');
        $wordCount = 0;
        if ($body->length > 0) {
            $text = strip_tags($body->item(0)->nodeValue);
            $wordCount = str_word_count($text);
        }

        // AI Scoring Logic
        require_once dirname(__DIR__, 3) . '/core/AI.php';
        
        $systemPrompt = "You are an expert SEO auditor. The user will provide a JSON block with scraped elements of a webpage (Title, Meta Description, H1 tags, Word Count). Analyze this data and provide an SEO score from 0-100, and a list of specific, actionable recommendations to improve the page. Your recommendations should be highly specific based on the provided text (e.g. quote the title). Output strictly in JSON format: {\"score\": 85, \"recommendations\": [\"rec 1\", \"rec 2\"]}";
        $userPrompt = json_encode([
            'title' => $title,
            'meta_description' => $metaDesc,
            'h1_tags' => $h1s,
            'word_count' => $wordCount
        ]);
        
        try {
            $aiResponse = AI::generate($systemPrompt, $userPrompt, 'json_object');
            $aiData = json_decode($aiResponse, true);
            $score = $aiData['score'] ?? 50;
            $recs = $aiData['recommendations'] ?? [];
        } catch (Throwable $e) {
            // Fallback
            $score = 50;
            $recs = ['AI Analysis failed. Make sure your AI Provider settings are configured: ' . $e->getMessage()];
        }

        // Save Report
        $st = $db->prepare("INSERT INTO seo_reports (url, score, title, meta_description, h1_tags, word_count, recommendations) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $st->execute([
            $url, 
            max(0, $score), 
            substr($title, 0, 255), 
            $metaDesc, 
            json_encode($h1s), 
            $wordCount, 
            json_encode($recs)
        ]);

        flash('success', "SEO Audit complete for $url!");
        sc_redirect('/seo-auditor');
    }

    // Handle Backlink Update
    if ($action === 'update_backlink') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        $target = $_POST['target_url'] ?? '';
        
        $db->prepare("UPDATE backlink_submissions SET status = ?, target_url = ?, updated_at = NOW() WHERE id = ?")->execute([$status, $target, $id]);
        flash('success', "Backlink directory updated!");
        sc_redirect('/seo-auditor?tab=backlinks');
    }
}

// Fetch Data
$reports = $db->query("SELECT * FROM seo_reports ORDER BY created_at DESC LIMIT 20")->fetchAll();
$backlinks = $db->query("SELECT * FROM backlink_submissions ORDER BY id ASC")->fetchAll();

$tab = $_GET['tab'] ?? 'seo';
?>

<div class="flex items-center gap-4 mb-8">
  <div class="p-2 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl shadow-lg shadow-green-500/20 text-white">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M2 20h20"></path><path d="M5 20v-5"></path><path d="M10 20v-10"></path><path d="M15 20v-15"></path><path d="M20 20V8"></path></svg>
  </div>
  <h1 class="text-2xl font-bold text-white">SEO Auditor & Backlinks</h1>
</div>

<!-- Tabs -->
<div class="flex border-b border-slate-700 mb-6 gap-6">
    <a href="?tab=seo" class="pb-3 text-sm font-semibold border-b-2 transition-colors <?= $tab === 'seo' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-slate-400 hover:text-slate-300' ?>">SEO Audits</a>
    <a href="?tab=backlinks" class="pb-3 text-sm font-semibold border-b-2 transition-colors <?= $tab === 'backlinks' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-slate-400 hover:text-slate-300' ?>">Backlink Manager</a>
</div>

<?php if ($tab === 'seo'): ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="card p-6 border-t-4 border-emerald-500 lg:col-span-1">
        <h2 class="text-lg font-bold text-white mb-4">Run New SEO Audit</h2>
        <p class="text-slate-400 text-sm mb-6">Enter a public URL to perform an instant On-Page SEO Analysis.</p>
        
        <form method="post">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="audit">
            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-400 mb-1.5">Website URL</label>
                <input type="url" name="url" class="form-input w-full" placeholder="https://yourdomain.com" required>
            </div>
            <button type="submit" class="btn btn-primary bg-emerald-600 hover:bg-emerald-500 w-full justify-center">Run Full Audit</button>
        </form>
    </div>

    <div class="lg:col-span-2 flex flex-col gap-4">
        <h2 class="text-lg font-bold text-white">Recent Audits</h2>
        <?php if (empty($reports)): ?>
            <div class="card p-12 text-center text-slate-400">No audits run yet.</div>
        <?php else: ?>
            <?php foreach ($reports as $r): 
                $recs = json_decode($r['recommendations'], true) ?: [];
                $h1s = json_decode($r['h1_tags'], true) ?: [];
            ?>
            <div class="card p-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="font-bold text-white text-lg"><a href="<?= e($r['url']) ?>" target="_blank" class="text-emerald-400 hover:underline"><?= e($r['url']) ?></a></h3>
                        <p class="text-slate-400 text-sm">Audited on <?= date('M j, Y H:i', strtotime($r['created_at'])) ?></p>
                    </div>
                    <div class="text-right">
                        <span class="text-3xl font-bold <?= $r['score'] >= 80 ? 'text-emerald-500' : ($r['score'] >= 50 ? 'text-yellow-500' : 'text-red-500') ?>"><?= $r['score'] ?></span><span class="text-slate-500">/100</span>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
                    <div class="bg-slate-800 p-3 rounded border border-slate-700">
                        <span class="block text-slate-400 text-xs uppercase font-semibold mb-1">Page Title</span>
                        <span class="text-white font-medium"><?= e($r['title'] ?: 'Missing') ?></span>
                    </div>
                    <div class="bg-slate-800 p-3 rounded border border-slate-700">
                        <span class="block text-slate-400 text-xs uppercase font-semibold mb-1">Meta Description</span>
                        <span class="text-white font-medium break-words"><?= e($r['meta_description'] ?: 'Missing') ?></span>
                    </div>
                </div>
                
                <?php if (!empty($recs)): ?>
                <div class="bg-red-500/10 border border-red-500/20 rounded-lg p-4">
                    <h4 class="text-red-400 font-bold text-sm mb-2 flex items-center gap-2">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        Critical Issues Found
                    </h4>
                    <ul class="list-disc list-inside text-red-200 text-sm space-y-1">
                        <?php foreach ($recs as $rec): ?>
                            <li><?= e($rec) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php else: ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-lg p-4 text-emerald-400 text-sm font-semibold flex items-center gap-2">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    Perfect On-Page SEO score! No critical issues found.
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'backlinks'): ?>
<div class="card p-0 overflow-hidden border-t-4 border-emerald-500">
    <div class="p-6 border-b border-slate-700">
        <h2 class="text-lg font-bold text-white mb-2">Backlink Directory Submissions</h2>
        <p class="text-slate-400 text-sm">Manually submit your project to these high Domain Authority (DA) directories and track your status. Automated bot submissions often fail due to Captchas, so manual submission is highly recommended for SEO impact.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-800 text-slate-400 text-xs uppercase font-semibold">
                <tr>
                    <th class="px-6 py-4 border-b border-slate-700">Directory</th>
                    <th class="px-6 py-4 border-b border-slate-700">Submit Link</th>
                    <th class="px-6 py-4 border-b border-slate-700">Your URL</th>
                    <th class="px-6 py-4 border-b border-slate-700">Status</th>
                    <th class="px-6 py-4 border-b border-slate-700 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                <?php foreach ($backlinks as $b): ?>
                <tr class="hover:bg-slate-800/30 transition-colors">
                    <td class="px-6 py-4">
                        <span class="font-bold text-white"><?= e($b['directory_name']) ?></span>
                    </td>
                    <td class="px-6 py-4">
                        <a href="<?= e($b['directory_url']) ?>" target="_blank" class="text-emerald-400 hover:underline flex items-center gap-1">
                            Visit Site <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                        </a>
                    </td>
                    <form method="post">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="action" value="update_backlink">
                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                        <td class="px-6 py-4">
                            <input type="url" name="target_url" value="<?= e($b['target_url']) ?>" class="form-input text-xs px-2 py-1" placeholder="Your URL">
                        </td>
                        <td class="px-6 py-4">
                            <select name="status" class="form-input text-xs px-2 py-1">
                                <option value="pending" <?= $b['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="submitted" <?= $b['status'] === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                                <option value="live" <?= $b['status'] === 'live' ? 'selected' : '' ?>>Live / Approved</option>
                                <option value="rejected" <?= $b['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button type="submit" class="btn btn-secondary text-xs px-3 py-1">Save</button>
                        </td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
