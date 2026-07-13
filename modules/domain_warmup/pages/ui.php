<?php
declare(strict_types=1);
$pageTitle = 'Domain Warm-Up Engine';
require_once dirname(__DIR__, 3) . '/includes/header.php';
?>

<div class="flex items-center gap-4 mb-8">
  <h1 class="text-2xl font-bold text-white">Domain Warm-Up Engine</h1>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
  <div class="xl:col-span-1 space-y-6">
    <div class="card p-6 border-t-4 border-amber-500">
      <h2 class="text-lg font-bold text-white mb-4">Warm-Up Status</h2>
      <div class="flex justify-between items-center mb-6">
          <span class="text-slate-400 font-semibold">Status</span>
          <span class="badge badge-warning">Inactive</span>
      </div>
      <div class="flex justify-between items-center mb-6">
          <span class="text-slate-400 font-semibold">Current Day</span>
          <span class="text-white font-mono">Day 0 / 30</span>
      </div>
      <div class="flex justify-between items-center mb-6">
          <span class="text-slate-400 font-semibold">Volume Today</span>
          <span class="text-white font-mono">0 emails</span>
      </div>
      <button class="btn btn-primary w-full justify-center" onclick="alert('Warm-up engine requires a dedicated Seed List to activate.')">Activate Engine</button>
    </div>
  </div>

  <div class="xl:col-span-2">
    <div class="card p-6 bg-amber-500/5 border border-amber-500/20">
      <h2 class="text-xl font-bold text-amber-400 mb-2">Why Warm Up Your Domain?</h2>
      <p class="text-slate-300 text-sm mb-4 leading-relaxed">
          When you register a new domain or set up a new SMTP server, your IP reputation is neutral. If you immediately blast 10,000 emails, spam filters (like Gmail and Outlook) will instantly flag you as a spammer, ruining your deliverability forever.
      </p>
      <p class="text-slate-300 text-sm mb-4 leading-relaxed">
          The Warm-Up Engine prevents this by mathematically throttling your sends to a "Seed List" of trusted accounts, slowly ramping up volume over 30 days.
      </p>
      
      <div class="bg-slate-900/80 rounded-xl p-4 mt-6">
          <h3 class="font-bold text-slate-200 text-sm mb-2 uppercase tracking-wider">Example Schedule</h3>
          <div class="grid grid-cols-5 gap-2 text-center text-xs text-slate-400">
              <div class="bg-slate-800 p-2 rounded">Day 1<br><span class="text-white font-bold">5</span></div>
              <div class="bg-slate-800 p-2 rounded">Day 2<br><span class="text-white font-bold">10</span></div>
              <div class="bg-slate-800 p-2 rounded">Day 3<br><span class="text-white font-bold">15</span></div>
              <div class="bg-slate-800 p-2 rounded">Day 4<br><span class="text-white font-bold">25</span></div>
              <div class="bg-slate-800 p-2 rounded">Day 5<br><span class="text-white font-bold">40</span></div>
          </div>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
