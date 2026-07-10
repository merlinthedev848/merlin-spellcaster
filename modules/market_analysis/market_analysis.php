<?php
// modules/market_analysis/market_analysis.php
// This module contains the market analysis (analytics) view.
// It expects $db (PDO) and other globals to be defined by the caller.

$range       = (int)($_GET['range'] ?? 30);
$validRanges = [7, 30, 90, 365, 0];
if (!in_array($range, $validRanges)) $range = 30;
$dateClause  = $range > 0 ? "AND sent_at >= DATE_SUB(NOW(), INTERVAL {$range} DAY)" : '';

try {
    $stats = $db->query("SELECT SUM(send_count) as sent, SUM(open_count) as opens, SUM(click_count) as clicks, SUM(unsub_count) as unsubs, SUM(bounce_count) as bounces FROM campaigns WHERE status='sent' {$dateClause}")->fetch();
    $chartCampaigns = $db->query("SELECT name, send_count, open_count, click_count FROM campaigns WHERE status='sent' ORDER BY sent_at DESC LIMIT 10")->fetchAll();
    $allCampaigns   = $db->query("SELECT name, send_count, open_count, click_count, bounce_count, unsub_count, sent_at, ROUND(open_count/NULLIF(send_count,0)*100,1) as open_rate, ROUND(click_count/NULLIF(send_count,0)*100,1) as click_rate FROM campaigns WHERE status='sent' ORDER BY sent_at DESC")->fetchAll();
    $subStatus      = $db->query("SELECT status, COUNT(*) as cnt FROM subscribers GROUP BY status")->fetchAll();
    $topUrls        = $db->query("SELECT url, COUNT(*) as clicks, COUNT(DISTINCT subscriber_id) as unique_subs FROM campaign_clicks WHERE url IS NOT NULL AND url != '' GROUP BY url ORDER BY clicks DESC LIMIT 20")->fetchAll();
    $growthByMonth  = $db->query("SELECT DATE_FORMAT(created_at,'%Y-%m') as month, COUNT(*) as cnt FROM subscribers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY month ORDER BY month")->fetchAll();
} catch (Throwable $e) {
    $stats=$chartCampaigns=$allCampaigns=$subStatus=$topUrls=$growthByMonth=[];
    $stats=['sent'=>0,'opens'=>0,'clicks'=>0,'unsubs'=>0,'bounces'=>0];
}

$totalClicksAll  = array_sum(array_column($topUrls,'clicks'));
$sent    = (int)($stats['sent']   ?? 0);
$opens   = (int)($stats['opens']  ?? 0);
$clicks  = (int)($stats['clicks'] ?? 0);
$unsubs  = (int)($stats['unsubs'] ?? 0);
$bounces = (int)($stats['bounces']?? 0);
$openRate  = $sent > 0 ? round($opens/$sent*100,1) : 0;
$clickRate = $sent > 0 ? round($clicks/$sent*100,1) : 0;

$chartNames  = json_encode(array_map(fn($c) => mb_strimwidth($c['name'],0,20,'…'), array_reverse($chartCampaigns)));
$chartSent   = json_encode(array_column(array_reverse($chartCampaigns),'send_count'));
$chartOpens  = json_encode(array_column(array_reverse($chartCampaigns),'open_count'));
$chartClicks = json_encode(array_column(array_reverse($chartCampaigns),'click_count'));

$statusColorMap = ['active'=>'#10b981','unsubscribed'=>'#f59e0b','bounced'=>'#ef4444','complained'=>'#8b5cf6'];
$doughnutLabels = json_encode(array_column($subStatus,'status'));
$doughnutCounts = json_encode(array_column($subStatus,'cnt'));
$doughnutColors = json_encode(array_map(fn($r) => $statusColorMap[$r['status']] ?? '#64748b', $subStatus));

$monthLabels = json_encode(array_column($growthByMonth,'month'));
$monthCounts = json_encode(array_column($growthByMonth,'cnt'));
?>
<div class="p-6 space-y-5">
  <!-- Header -->
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-white">Analytics</h1>
      <p class="text-sm text-slate-400 mt-0.5">Performance overview across all campaigns</p>
    </div>
    <!-- Date range -->
    <div class="flex gap-1 bg-slate-900 rounded-xl p-1 border border-white/5">
      <?php foreach ([7=>'7D',30=>'30D',90=>'90D',365=>'1Y',0=>'All'] as $v=>$l): ?>
      <a href="?range=<?= $v ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all <?= $range===$v?'bg-indigo-600 text-white':'text-slate-400 hover:text-white' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <!-- Metric Cards -->
  <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
    <?php foreach ([
      ['Total Sent',    formatNumber($sent),    'slate'],
      ['Total Opens',   formatNumber($opens).' ('.$openRate.'%)',   'indigo'],
      ['Total Clicks',  formatNumber($clicks).' ('.$clickRate.'%)', 'violet'],
      ['Unsubscribes',  formatNumber($unsubs),  'amber'],
      ['Bounces',       formatNumber($bounces), 'red'],
    ] as [$label,$val,$color]): ?>
    <div class="card p-4">
      <p class="text-xs text-slate-500 uppercase tracking-wider font-semibold mb-1"><?= $label ?></p>
      <p class="text-xl font-black text-white"><?= $val ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <!-- Charts Row -->
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    <!-- Grouped bar -->
    <div class="card p-5 xl:col-span-2">
      <h2 class="font-bold text-white mb-4">Campaign Performance (Last 10 Sent)</h2>
      <?php if (empty($chartCampaigns)): ?>
      <p class="text-slate-500 text-center py-10 text-sm">No sent campaigns yet.</p>
      <?php else: ?>
      <canvas id="barChart" height="180"></canvas>
      <?php endif; ?>
    </div>
    <!-- Doughnut -->
    <div class="card p-5">
      <h2 class="font-bold text-white mb-4">Subscriber Status</h2>
      <canvas id="doughnutChart" height="160"></canvas>
      <div class="mt-4 space-y-2">
        <?php foreach ($subStatus as $s): ?>
        <div class="flex justify-between text-xs">
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full" style="background:<?= $statusColorMap[$s['status']] ?? '#64748b' ?>"></div>
            <span class="text-slate-300 capitalize"><?= e($s['status']) ?></span>
          </div>
          <span class="text-slate-400 font-semibold"><?= number_format((int)$s['cnt']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <!-- Subscriber Growth (12 months) -->
  <div class="card p-5">
    <h2 class="font-bold text-white mb-4">Subscriber Growth (12 Months)</h2>
    <canvas id="growthChart" height="100"></canvas>
  </div>
  <!-- Funnel -->
  <div class="card p-5">
    <h2 class="font-bold text-white mb-4">Engagement Funnel</h2>
    <div class="flex flex-col gap-2 max-w-lg mx-auto">
      <?php
      $funnelData = [
        ['Sent',       $sent,                                         '#64748b', 100],
        ['Delivered',  (int)round($sent * 0.97),                     '#6366f1', 97],
        ['Opened',     $opens,                                        '#8b5cf6', $openRate],
        ['Clicked',    $clicks,                                       '#22d3ee', $clickRate],
      ];
      foreach ($funnelData as $fi => [$flabel, $fcount, $fcolor, $fpct]):
        $width = max(30, 100 - ($fi * 15));
      ?>
      <div class="flex items-center gap-4">
        <div class="w-20 text-right text-xs text-slate-400 font-semibold flex-shrink-0"><?= $flabel ?></div>
        <div class="flex-1 flex justify-center">
          <div class="h-10 rounded-lg flex items-center justify-center text-xs font-bold text-white transition-all" style="width:<?= $width ?>%;background:<?= $fcolor ?>;min-width:120px;">
            <?= number_format($fcount) ?> (<?= $fpct ?>%)
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <!-- Top URLs -->
  <?php if (!empty($topUrls)): ?>
  <div class="card overflow-hidden">
    <div class="p-5 border-b border-white/5">
      <h2 class="font-bold text-white">Top Clicked URLs</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-white/5">
            <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-5 py-3">URL</th>
            <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Clicks</th>
            <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Unique</th>
            <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-5 py-3">% of Total</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <?php foreach ($topUrls as $url): ?>
          <tr class="hover:bg-white/2">
            <td class="px-5 py-3 max-w-xs">
              <a href="<?= e($url['url']) ?>" target="_blank" title="<?= e($url['url']) ?>" class="text-indigo-400 hover:text-indigo-300 text-xs truncate block">
                <?= e(mb_strimwidth($url['url'],0,70,'…')) ?>
              </a>
            </td>
            <td class="px-4 py-3 text-right text-slate-200 font-semibold"><?= number_format((int)$url['clicks']) ?></td>
            <td class="px-4 py-3 text-right text-slate-400"><?= number_format((int)$url['unique_subs']) ?></td>
            <td class="px-5 py-3 text-right text-slate-400"><?= $totalClicksAll>0?round($url['clicks']/$totalClicksAll*100,1):0 ?>%</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
  <!-- Campaign Table -->
  <?php if (!empty($allCampaigns)): ?>
  <div class="card overflow-hidden">
    <div class="p-5 border-b border-white/5">
      <h2 class="font-bold text-white">All Campaigns Performance</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-xs">
        <thead>
          <tr class="border-b border-white/5">
            <th class="text-left text-slate-500 font-semibold px-5 py-3">Name</th>
            <th class="text-right text-slate-500 font-semibold px-4 py-3">Sent</th>
            <th class="text-right text-slate-500 font-semibold px-4 py-3">Opens</th>
            <th class="text-right text-slate-500 font-semibold px-4 py-3">Open%</th>
            <th class="text-right text-slate-500 font-semibold px-4 py-3">Clicks</th>
            <th class="text-right text-slate-500 font-semibold px-4 py-3">Click%</th>
            <th class="text-right text-slate-500 font-semibold px-4 py-3">Unsubs</th>
            <th class="text-right text-slate-500 font-semibold px-4 py-3">Bounces</th>
            <th class="text-right text-slate-500 font-semibold px-5 py-3">Date</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <?php foreach ($allCampaigns as $c): ?>
          <tr class="hover:bg-white/2">
            <td class="px-5 py-2.5 text-slate-200"><?= e(mb_strimwidth($c['name'],0,40,'…')) ?></td>
            <td class="px-4 py-2.5 text-right text-slate-400 font-mono"><?= number_format((int)$c['send_count']) ?></td>
            <td class="px-4 py-2.5 text-right text-slate-400 font-mono"><?= number_format((int)$c['open_count']) ?></td>
            <td class="px-4 py-2.5 text-right font-bold <?= ($c['open_rate']??0)>=30?'text-emerald-400':(($c['open_rate']??0)>=15?'text-amber-400':'text-red-400') ?>"><?= $c['open_rate']??'—' ?>%</td>
            <td class="px-4 py-2.5 text-right text-slate-400 font-mono"><?= number_format((int)$c['click_count']) ?></td>
            <td class="px-4 py-2.5 text-right font-bold <?= ($c['click_rate']??0)>=5?'text-emerald-400':(($c['click_rate']??0)>=2?'text-amber-400':'text-slate-400') ?>"><?= $c['click_rate']??'—' ?>%</td>
            <td class="px-4 py-2.5 text-right text-slate-400"><?= number_format((int)$c['unsub_count']) ?></td>
            <td class="px-4 py-2.5 text-right text-slate-400"><?= number_format((int)$c['bounce_count']) ?></td>
            <td class="px-5 py-2.5 text-right text-slate-500"><?= $c['sent_at'] ? date('M j, Y', strtotime($c['sent_at'])) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<script>
// Bar chart
(function() {
  const ctx = document.getElementById('barChart')?.getContext('2d');
  if (!ctx) return;
  new Chart(ctx, {
    type:'bar',
    data:{
      labels:<?= $chartNames ?>,
      datasets:[
        {label:'Sent',data:<?= $chartSent ?>,backgroundColor:'rgba(100,116,139,0.5)',borderRadius:3},
        {label:'Opens',data:<?= $chartOpens ?>,backgroundColor:'rgba(99,102,241,0.7)',borderRadius:3},
        {label:'Clicks',data:<?= $chartClicks ?>,backgroundColor:'rgba(139,92,246,0.7)',borderRadius:3},
      ]
    },
    options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{labels:{color:'#94a3b8',font:{size:11}}}},scales:{x:{grid:{color:'rgba(255,255,255,0.04)'},ticks:{color:'#64748b',font:{size:10}}},y:{grid:{color:'rgba(255,255,255,0.04)'},ticks:{color:'#64748b',font:{size:10}},beginAtZero:true}}}
  });
})();
// Doughnut
(function() {
  const ctx = document.getElementById('doughnutChart')?.getContext('2d');
  if (!ctx) return;
  new Chart(ctx, {
    type:'doughnut',
    data:{labels:<?= $doughnutLabels ?>,datasets:[{data:<?= $doughnutCounts ?>,backgroundColor:<?= $doughnutColors ?>,borderWidth:2,borderColor:'#111827'}]},
    options:{responsive:true,maintainAspectRatio:true,cutout:'65%',plugins:{legend:{display:false}}}
  });
})();
// Growth
(function() {
  const ctx = document.getElementById('growthChart')?.getContext('2d');
  if (!ctx) return;
  const gradient = ctx.createLinearGradient(0,0,0,150);
  gradient.addColorStop(0,'rgba(99,102,241,0.3)');
  gradient.addColorStop(1,'rgba(99,102,241,0)');
  new Chart(ctx, {
    type:'line',
    data:{labels:<?= $monthLabels ?>,datasets:[{label:'New Subscribers',data:<?= $monthCounts ?>,borderColor:'#6366f1',backgroundColor:gradient,fill:true,tension:0.4,borderWidth:2,pointRadius:3}]},
    options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{display:false}},scales:{x:{grid:{color:'rgba(255,255,255,0.04)'},ticks:{color:'#64748b',font:{size:11}}},y:{grid:{color:'rgba(255,255,255,0.04)'},ticks:{color:'#64748b',font:{size:11}},beginAtZero:true}}}
  });
})();
</script>
<?php
?>
