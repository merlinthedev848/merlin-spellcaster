<?php
declare(strict_types=1);
$pageTitle = 'Market Research Hub';
require_once dirname(__DIR__, 3) . '/includes/header.php';

// Stats for scraper
$totalLeads = $db->query("SELECT COUNT(*) FROM mr_leads")->fetchColumn();
$newLeads = $db->query("SELECT COUNT(*) FROM mr_leads WHERE status='new'")->fetchColumn();

// Stats for surveys (from core)
$totalSurveys = $db->query("SELECT COUNT(*) FROM surveys")->fetchColumn();
$totalResponses = $db->query("SELECT COUNT(*) FROM survey_responses")->fetchColumn();
?>

<div class="flex items-center justify-between mb-8">
  <div>
    <h1 class="text-2xl font-bold text-white">Market Research</h1>
    <p class="text-slate-500 text-sm mt-1">Discover new leads and manage your surveys.</p>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
  <!-- Scraper Card -->
  <div class="card p-6">
    <div class="flex justify-between items-start mb-4">
      <div>
        <h2 class="text-lg font-bold text-white">Lead Scraper</h2>
        <p class="text-sm text-slate-400 mt-1">Find emails across the web by keyword niche.</p>
      </div>
      <div class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-400">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-4 mb-6">
      <div class="bg-slate-800/50 p-4 rounded-xl border border-slate-700/50">
        <div class="text-2xl font-bold text-white"><?= number_format((float)$totalLeads) ?></div>
        <div class="text-xs text-slate-500 font-semibold uppercase tracking-wider mt-1">Total Leads</div>
      </div>
      <div class="bg-slate-800/50 p-4 rounded-xl border border-slate-700/50">
        <div class="text-2xl font-bold text-emerald-400"><?= number_format((float)$newLeads) ?></div>
        <div class="text-xs text-slate-500 font-semibold uppercase tracking-wider mt-1">New / Unimported</div>
      </div>
    </div>
    <a href="/modules/market_research/pages/scraper.php" class="btn btn-primary w-full justify-center">Open Lead Scraper</a>
  </div>

  <!-- Surveys Card -->
  <div class="card p-6">
    <div class="flex justify-between items-start mb-4">
      <div>
        <h2 class="text-lg font-bold text-white">Surveys</h2>
        <p class="text-sm text-slate-400 mt-1">Gather direct feedback from your audience.</p>
      </div>
      <div class="w-10 h-10 rounded-xl bg-violet-500/10 flex items-center justify-center text-violet-400">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-4 mb-6">
      <div class="bg-slate-800/50 p-4 rounded-xl border border-slate-700/50">
        <div class="text-2xl font-bold text-white"><?= number_format((float)$totalSurveys) ?></div>
        <div class="text-xs text-slate-500 font-semibold uppercase tracking-wider mt-1">Surveys</div>
      </div>
      <div class="bg-slate-800/50 p-4 rounded-xl border border-slate-700/50">
        <div class="text-2xl font-bold text-violet-400"><?= number_format((float)$totalResponses) ?></div>
        <div class="text-xs text-slate-500 font-semibold uppercase tracking-wider mt-1">Responses</div>
      </div>
    </div>
    <div class="flex gap-2">
      <a href="/admin/research.php?old=1" class="btn btn-secondary w-full justify-center">Manage</a>
      <a href="/admin/survey_create.php" class="btn btn-primary w-full justify-center">New Survey</a>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
