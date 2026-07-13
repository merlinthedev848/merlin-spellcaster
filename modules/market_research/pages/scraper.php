<?php
declare(strict_types=1);
$pageTitle = 'Lead Scraper';
require_once dirname(__DIR__, 3) . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Auth::checkCsrf()) { flash('error', 'Invalid security token'); sc_redirect(<?php
declare(strict_types=1);
$pageTitle = 'Lead Scraper';
require_once dirname(__DIR__, 3) . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'scrape') {
        $keyword = trim($_POST['keyword'] ?? '');
        if ($keyword) {
            $db->prepare("INSERT INTO mr_jobs (keyword) VALUES (?)")->execute([$keyword]);
            flash('success', 'Scraping job queued! It will run in the background.');
            sc_redirect('/modules/market_research/pages/scraper.php');
        }
    } elseif ($_POST['action'] === 'delete_job') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM mr_jobs WHERE id=?")->execute([$id]);
        flash('success', 'Job deleted.');
        sc_redirect('/modules/market_research/pages/scraper.php');
    }
}

$jobs = $db->query("SELECT * FROM mr_jobs ORDER BY created_at DESC LIMIT 20")->fetchAll();
$leads = $db->query("SELECT * FROM mr_leads ORDER BY created_at DESC LIMIT 50")->fetchAll();
$lists = $db->query("SELECT id, name FROM lists ORDER BY name")->fetchAll();
?>

<div class="flex items-center gap-4 mb-8">
  <a href="/modules/market_research/pages/hub.php" class="text-slate-400 hover:text-white transition-colors">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
  </a>
  <h1 class="text-2xl font-bold text-white">Lead Scraper</h1>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
  
  <div class="xl:col-span-1 space-y-6">
    <div class="card p-6">
      <h2 class="text-lg font-bold text-white mb-4">Start New Scrape</h2>
      <form method="post">
    <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="scrape">
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Keyword / Niche *</label>
        <input type="text" name="keyword" class="form-input w-full mb-4" placeholder="e.g. 'plumbers in london'" required>
        <button type="submit" class="btn btn-primary w-full justify-center">Queue Job</button>
      </form>
      <p class="text-xs text-slate-500 mt-4 leading-relaxed">
        The scraper searches the web for your keyword and extracts public email addresses from the results. It runs via cron to prevent timeouts.
      </p>
    </div>

    <div class="card overflow-hidden">
      <div class="p-4 border-b border-slate-700/50 bg-slate-800/50">
        <h3 class="font-bold text-slate-200">Recent Jobs</h3>
      </div>
      <div class="max-h-[400px] overflow-y-auto">
        <?php if (!$jobs): ?>
          <div class="p-6 text-center text-slate-500 text-sm">No jobs yet.</div>
        <?php else: ?>
          <?php foreach ($jobs as $job): ?>
            <div class="p-4 border-b border-slate-700/50 last:border-0 hover:bg-slate-800/30 transition-colors">
              <div class="flex justify-between items-start mb-1">
                <div class="font-semibold text-white text-sm"><?= e($job['keyword']) ?></div>
                <span class="badge <?= $job['status'] === 'completed' ? 'badge-active' : ($job['status'] === 'pending' ? 'badge-draft' : 'badge-warning') ?>">
                  <?= ucfirst($job['status']) ?>
                </span>
              </div>
              <div class="flex justify-between items-center mt-2 text-xs text-slate-400">
                <span><?= $job['emails_found'] ?> emails found</span>
                <form method="post" class="inline" onsubmit="return confirm('Delete this job?');">
    <?= Auth::csrfField() ?>
                  <input type="hidden" name="action" value="delete_job">
                  <input type="hidden" name="id" value="<?= $job['id'] ?>">
                  <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="xl:col-span-2">
    <div class="card overflow-hidden">
      <div class="p-5 border-b border-slate-700/50 bg-slate-800/50 flex justify-between items-center">
        <h3 class="font-bold text-slate-200">Discovered Leads (Latest 50)</h3>
        <a href="#" class="btn btn-secondary btn-sm" onclick="alert('Import functionality coming soon via segment importer.'); return false;">Export/Import</a>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>Email</th>
            <th>Source</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($leads as $lead): ?>
            <tr>
              <td class="font-semibold text-slate-200"><?= e($lead['email']) ?></td>
              <td class="text-xs truncate max-w-[200px]" title="<?= e($lead['source_url'] ?? '') ?>">
                <a href="<?= e($lead['source_url'] ?? '#') ?>" target="_blank" class="text-indigo-400 hover:underline">
                  <?= e($lead['source_url'] ?? 'Unknown') ?>
                </a>
              </td>
              <td class="text-slate-400 text-sm"><?= date('M j', strtotime($lead['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$leads): ?>
            <tr><td colspan="3" class="text-center text-slate-500 py-8">No leads discovered yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
SERVER['REQUEST_URI']); }
    if ($_POST['action'] === 'scrape') {
        $keyword = trim($_POST['keyword'] ?? '');
        if ($keyword) {
            $db->prepare("INSERT INTO mr_jobs (keyword) VALUES (?)")->execute([$keyword]);
            flash('success', 'Scraping job queued! It will run in the background.');
            sc_redirect('/modules/market_research/pages/scraper.php');
        }
    } elseif ($_POST['action'] === 'delete_job') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM mr_jobs WHERE id=?")->execute([$id]);
        flash('success', 'Job deleted.');
        sc_redirect('/modules/market_research/pages/scraper.php');
    }
}

$jobs = $db->query("SELECT * FROM mr_jobs ORDER BY created_at DESC LIMIT 20")->fetchAll();
$leads = $db->query("SELECT * FROM mr_leads ORDER BY created_at DESC LIMIT 50")->fetchAll();
$lists = $db->query("SELECT id, name FROM lists ORDER BY name")->fetchAll();
?>

<div class="flex items-center gap-4 mb-8">
  <a href="/modules/market_research/pages/hub.php" class="text-slate-400 hover:text-white transition-colors">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
  </a>
  <h1 class="text-2xl font-bold text-white">Lead Scraper</h1>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
  
  <div class="xl:col-span-1 space-y-6">
    <div class="card p-6">
      <h2 class="text-lg font-bold text-white mb-4">Start New Scrape</h2>
      <form method="post">
    <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="scrape">
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Keyword / Niche *</label>
        <input type="text" name="keyword" class="form-input w-full mb-4" placeholder="e.g. 'plumbers in london'" required>
        <button type="submit" class="btn btn-primary w-full justify-center">Queue Job</button>
      </form>
      <p class="text-xs text-slate-500 mt-4 leading-relaxed">
        The scraper searches the web for your keyword and extracts public email addresses from the results. It runs via cron to prevent timeouts.
      </p>
    </div>

    <div class="card overflow-hidden">
      <div class="p-4 border-b border-slate-700/50 bg-slate-800/50">
        <h3 class="font-bold text-slate-200">Recent Jobs</h3>
      </div>
      <div class="max-h-[400px] overflow-y-auto">
        <?php if (!$jobs): ?>
          <div class="p-6 text-center text-slate-500 text-sm">No jobs yet.</div>
        <?php else: ?>
          <?php foreach ($jobs as $job): ?>
            <div class="p-4 border-b border-slate-700/50 last:border-0 hover:bg-slate-800/30 transition-colors">
              <div class="flex justify-between items-start mb-1">
                <div class="font-semibold text-white text-sm"><?= e($job['keyword']) ?></div>
                <span class="badge <?= $job['status'] === 'completed' ? 'badge-active' : ($job['status'] === 'pending' ? 'badge-draft' : 'badge-warning') ?>">
                  <?= ucfirst($job['status']) ?>
                </span>
              </div>
              <div class="flex justify-between items-center mt-2 text-xs text-slate-400">
                <span><?= $job['emails_found'] ?> emails found</span>
                <form method="post" class="inline" onsubmit="return confirm('Delete this job?');">
    <?= Auth::csrfField() ?>
                  <input type="hidden" name="action" value="delete_job">
                  <input type="hidden" name="id" value="<?= $job['id'] ?>">
                  <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="xl:col-span-2">
    <div class="card overflow-hidden">
      <div class="p-5 border-b border-slate-700/50 bg-slate-800/50 flex justify-between items-center">
        <h3 class="font-bold text-slate-200">Discovered Leads (Latest 50)</h3>
        <a href="#" class="btn btn-secondary btn-sm" onclick="alert('Import functionality coming soon via segment importer.'); return false;">Export/Import</a>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>Email</th>
            <th>Source</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($leads as $lead): ?>
            <tr>
              <td class="font-semibold text-slate-200"><?= e($lead['email']) ?></td>
              <td class="text-xs truncate max-w-[200px]" title="<?= e($lead['source_url'] ?? '') ?>">
                <a href="<?= e($lead['source_url'] ?? '#') ?>" target="_blank" class="text-indigo-400 hover:underline">
                  <?= e($lead['source_url'] ?? 'Unknown') ?>
                </a>
              </td>
              <td class="text-slate-400 text-sm"><?= date('M j', strtotime($lead['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$leads): ?>
            <tr><td colspan="3" class="text-center text-slate-500 py-8">No leads discovered yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
