<?php
/**
 * admin/dashboard.php — Main dashboard
 * PHP 8.5+ compatible
 */
declare(strict_types=1);
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';

// ── Fetch all data ───────────────────────────────────────────────────────────
try {
    $totalSubs      = (int)$db->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();
    $activeSubs     = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE status='active'")->fetchColumn();
    $campaignsSent  = (int)$db->query("SELECT COUNT(*) FROM campaigns WHERE status='sent'")->fetchColumn();
    $avgOpen        = (float)($db->query("SELECT ROUND(SUM(open_count)/NULLIF(SUM(send_count),0)*100,1) FROM campaigns WHERE status='sent'")->fetchColumn() ?? 0);
    $avgClick       = (float)($db->query("SELECT ROUND(SUM(click_count)/NULLIF(SUM(send_count),0)*100,1) FROM campaigns WHERE status='sent'")->fetchColumn() ?? 0);
    $subsThisMonth  = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn();
    $subsLastMonth  = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE created_at >= DATE_FORMAT(DATE_SUB(NOW(),INTERVAL 1 MONTH),'%Y-%m-01') AND created_at < DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn();
    $growthData     = $db->query("SELECT DATE_FORMAT(created_at,'%Y-%m-%d') as day, COUNT(*) as cnt FROM subscribers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY day ORDER BY day")->fetchAll();
    $activity       = $db->query("SELECT a.*, u.name as user_name FROM activity_log a LEFT JOIN users u ON a.user_id=u.id ORDER BY a.created_at DESC LIMIT 15")->fetchAll();
    $recentCampaigns= $db->query("SELECT c.*, GROUP_CONCAT(l.name SEPARATOR ', ') as list_names FROM campaigns c LEFT JOIN campaign_lists cl ON c.id=cl.campaign_id LEFT JOIN lists l ON cl.list_id=l.id GROUP BY c.id ORDER BY c.created_at DESC LIMIT 5")->fetchAll();
    $topLists       = $db->query("SELECT * FROM lists ORDER BY subscriber_count DESC LIMIT 8")->fetchAll();
} catch (Throwable $e) {
    $totalSubs = $activeSubs = $campaignsSent = 0;
    $avgOpen = $avgClick = 0.0;
    $subsThisMonth = $subsLastMonth = 0;
    $growthData = $activity = $recentCampaigns = $topLists = [];
}

$subDiff    = $subsThisMonth - $subsLastMonth;
$subChange  = $subsLastMonth > 0 ? round($subDiff / $subsLastMonth * 100, 1) : 0;
$maxListSubs = max(array_column($topLists, 'subscriber_count') + [0]);

$growthLabels = json_encode(array_column($growthData, 'day'));
$growthCounts = json_encode(array_column($growthData, 'cnt'));

$statusColors = [
    'draft'     => 'text-slate-400 bg-slate-800',
    'scheduled' => 'text-amber-400 bg-amber-900/40',
    'sending'   => 'text-blue-400 bg-blue-900/40',
    'sent'      => 'text-emerald-400 bg-emerald-900/40',
    'paused'    => 'text-orange-400 bg-orange-900/40',
    'cancelled' => 'text-red-400 bg-red-900/40',
];
?>

<div class="p-6 space-y-6">

  <!-- ── Page Header ── -->
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-white">Dashboard</h1>
      <p class="text-sm text-slate-400 mt-0.5">Welcome back, <?= e($user['name'] ?? 'Admin') ?> 👋</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="/admin/campaign_create.php" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        New Campaign
      </a>
    </div>
  </div>

  <!-- ── Stat Cards ── -->
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
    <?php
    $cards = [
      ['Total Subscribers', formatNumber($totalSubs), 'indigo', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0', ($subChange >= 0 ? '+' : '') . $subChange . '% vs last month'],
      ['Active Subscribers', formatNumber($activeSubs), 'emerald', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0', 'of total'],
      ['Campaigns Sent', formatNumber($campaignsSent), 'violet', 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'all time'],
      ['Avg Open Rate', $avgOpen . '%', 'cyan', 'M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7', 'industry avg ~21%'],
      ['Avg Click Rate', $avgClick . '%', 'amber', 'M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122', 'industry avg ~2.5%'],
    ];
    foreach ($cards as [$label, $value, $color, $icon, $sub]):
    ?>
    <div class="card p-5 relative overflow-hidden" style="border-left: 3px solid var(--tw-<?= $color ?>-500, #6366f1);">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1"><?= $label ?></p>
          <p class="text-2xl font-black text-white"><?= $value ?></p>
          <p class="text-xs text-slate-500 mt-1"><?= $sub ?></p>
        </div>
        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" style="background:rgba(<?= match($color){'indigo'=>'99,102,241','emerald'=>'16,185,129','violet'=>'139,92,246','cyan'=>'34,211,238','amber'=>'245,158,11',default=>'99,102,241'} ?>,0.15)">
          <svg class="w-5 h-5 text-<?= $color ?>-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/></svg>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── Charts Row ── -->
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

    <!-- Growth Chart -->
    <div class="card p-5 xl:col-span-2">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold text-white">Subscriber Growth (30 days)</h2>
        <span class="text-xs text-slate-500">Daily new subscribers</span>
      </div>
      <canvas id="growthChart" height="180"></canvas>
    </div>

    <!-- Recent Activity -->
    <div class="card p-5 overflow-hidden">
      <h2 class="font-bold text-white mb-4">Recent Activity</h2>
      <div class="space-y-3 overflow-y-auto" style="max-height:260px">
        <?php if (empty($activity)): ?>
        <p class="text-slate-500 text-sm text-center py-6">No activity yet.</p>
        <?php else: foreach ($activity as $act): ?>
        <div class="flex items-start gap-3">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5" style="background:rgba(99,102,241,0.1)">
            <span class="text-xs"><?= match(true){ str_contains($act['entity_type'],'campaign')=>'📧', str_contains($act['entity_type'],'subscriber')=>'👤', str_contains($act['action'],'login')=>'🔐', str_contains($act['entity_type'],'import')=>'📥', default=>'⚡' } ?></span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-xs text-slate-300 leading-relaxed"><?= e($act['action']) ?> <span class="text-slate-500"><?= e($act['entity_type']) ?></span></p>
            <p class="text-xs text-slate-600"><?= timeAgo($act['created_at']) ?> · <?= e($act['user_name'] ?? 'System') ?></p>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Bottom Row ── -->
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

    <!-- Recent Campaigns -->
    <div class="card p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold text-white">Recent Campaigns</h2>
        <a href="/admin/campaigns.php" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold">View all →</a>
      </div>
      <?php if (empty($recentCampaigns)): ?>
        <p class="text-slate-500 text-sm text-center py-8">No campaigns yet. <a href="/admin/campaign_create.php" class="text-indigo-400 hover:underline">Create one →</a></p>
      <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead><tr class="border-b border-white/5">
            <th class="text-left text-slate-500 font-semibold pb-2">Name</th>
            <th class="text-center text-slate-500 font-semibold pb-2">Status</th>
            <th class="text-right text-slate-500 font-semibold pb-2">Open%</th>
            <th class="text-right text-slate-500 font-semibold pb-2">Click%</th>
          </tr></thead>
          <tbody class="divide-y divide-white/5">
          <?php foreach ($recentCampaigns as $c): ?>
            <tr class="hover:bg-white/2">
              <td class="py-2 pr-2">
                <a href="/admin/campaign_view.php?id=<?= $c['id'] ?>" class="text-slate-200 hover:text-indigo-300 font-medium truncate block max-w-[180px]"><?= e($c['name']) ?></a>
                <span class="text-slate-600"><?= date('M j', strtotime($c['created_at'])) ?></span>
              </td>
              <td class="py-2 text-center">
                <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $statusColors[$c['status']] ?? 'text-slate-400 bg-slate-800' ?>"><?= ucfirst(e($c['status'])) ?></span>
              </td>
              <td class="py-2 text-right text-slate-300"><?= $c['send_count'] > 0 ? round($c['open_count']/$c['send_count']*100,1).'%' : '—' ?></td>
              <td class="py-2 text-right text-slate-300"><?= $c['send_count'] > 0 ? round($c['click_count']/$c['send_count']*100,1).'%' : '—' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Top Lists -->
    <div class="card p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold text-white">Top Lists</h2>
        <a href="/admin/lists.php" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold">View all →</a>
      </div>
      <?php if (empty($topLists)): ?>
        <p class="text-slate-500 text-sm text-center py-8">No lists yet.</p>
      <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($topLists as $list): ?>
        <div>
          <div class="flex justify-between text-xs mb-1">
            <span class="text-slate-300 font-medium"><?= e($list['name']) ?></span>
            <span class="text-slate-500"><?= number_format((int)$list['subscriber_count']) ?></span>
          </div>
          <div class="h-1.5 bg-slate-800 rounded-full overflow-hidden">
            <div class="h-full rounded-full" style="width:<?= $maxListSubs > 0 ? round($list['subscriber_count']/$maxListSubs*100) : 0 ?>%; background:linear-gradient(90deg,#6366f1,#8b5cf6);"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Quick Actions ── -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <?php
    $actions = [
      ['/admin/campaign_create.php', '📧', 'New Campaign',        'indigo'],
      ['/admin/imports.php',         '📥', 'Import Subscribers',  'emerald'],
      ['/admin/forms.php',           '📋', 'Create Form',         'violet'],
      ['/admin/survey_create.php',   '🔬', 'New Survey',          'cyan'],
    ];
    foreach ($actions as [$href, $icon, $label, $color]): ?>
    <a href="<?= $href ?>" class="card p-4 flex items-center gap-3 hover:border-<?= $color ?>-500/30 transition-all group" style="text-decoration:none">
      <span class="text-2xl"><?= $icon ?></span>
      <span class="text-sm font-semibold text-slate-300 group-hover:text-white transition-colors"><?= $label ?></span>
    </a>
    <?php endforeach; ?>
  </div>

</div>

<script>
// Growth chart
(function() {
  const ctx = document.getElementById('growthChart')?.getContext('2d');
  if (!ctx) return;
  const labels = <?= $growthLabels ?>;
  const data   = <?= $growthCounts ?>;
  const gradient = ctx.createLinearGradient(0, 0, 0, 200);
  gradient.addColorStop(0, 'rgba(99,102,241,0.3)');
  gradient.addColorStop(1, 'rgba(99,102,241,0.0)');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'New Subscribers',
        data,
        borderColor: '#6366f1',
        backgroundColor: gradient,
        borderWidth: 2,
        tension: 0.4,
        fill: true,
        pointBackgroundColor: '#6366f1',
        pointRadius: 3,
        pointHoverRadius: 5,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 11 } } },
        y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 11 }, stepSize: 1 }, beginAtZero: true }
      }
    }
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
