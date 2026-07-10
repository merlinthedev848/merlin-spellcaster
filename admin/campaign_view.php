<?php
/**
 * admin/campaign_view.php — Campaign details & analytics
 * PHP 8.5+
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/core/Auth.php';
Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { flash('error','Invalid campaign.'); sc_redirect('/admin/campaigns.php'); }

$st = $db->prepare("SELECT * FROM campaigns WHERE id = ?");
$st->execute([$id]);
$campaign = $st->fetch();
if (!$campaign) { flash('error','Campaign not found.'); sc_redirect('/admin/campaigns.php'); }

$pageTitle = 'Campaign: ' . $campaign['name'];
require_once __DIR__ . '/../includes/header.php';

$lists     = $db->prepare("SELECT l.* FROM lists l JOIN campaign_lists cl ON l.id=cl.list_id WHERE cl.campaign_id=?")->execute([$id]) ? (function() use ($db,$id) {
    $s = $db->prepare("SELECT l.* FROM lists l JOIN campaign_lists cl ON l.id=cl.list_id WHERE cl.campaign_id=?"); $s->execute([$id]); return $s->fetchAll();
})() : [];
$topClicks = (function() use ($db,$id) {
    $s = $db->prepare("SELECT url,COUNT(*) as c,COUNT(DISTINCT subscriber_id) as u FROM campaign_clicks WHERE campaign_id=? AND url IS NOT NULL GROUP BY url ORDER BY c DESC LIMIT 10");
    $s->execute([$id]); return $s->fetchAll();
})();
$opensByDay = (function() use ($db,$id) {
    $s = $db->prepare("SELECT DATE(opened_at) as day, COUNT(*) as cnt FROM campaign_opens WHERE campaign_id=? GROUP BY day ORDER BY day");
    $s->execute([$id]); return $s->fetchAll();
})();
$recentOpens = (function() use ($db,$id) {
    $s = $db->prepare("SELECT s.email,s.first_name,o.opened_at FROM campaign_opens o JOIN subscribers s ON s.id=o.subscriber_id WHERE o.campaign_id=? ORDER BY o.opened_at DESC LIMIT 15");
    $s->execute([$id]); return $s->fetchAll();
})();

$openRate  = $campaign['send_count'] > 0 ? round($campaign['open_count']/$campaign['send_count']*100,1) : 0;
$clickRate = $campaign['send_count'] > 0 ? round($campaign['click_count']/$campaign['send_count']*100,1) : 0;
$bounceRate= $campaign['send_count'] > 0 ? round($campaign['bounce_count']/$campaign['send_count']*100,1) : 0;
$unsubRate = $campaign['send_count'] > 0 ? round($campaign['unsub_count']/$campaign['send_count']*100,1) : 0;

$statusColors = [
    'draft'=>'text-slate-400 bg-slate-800','scheduled'=>'text-amber-400 bg-amber-900/30',
    'sending'=>'text-blue-400 bg-blue-900/30','sent'=>'text-emerald-400 bg-emerald-900/30',
    'paused'=>'text-orange-400 bg-orange-900/30','cancelled'=>'text-red-400 bg-red-900/30',
];
$openDayLabels = json_encode(array_column($opensByDay,'day'));
$openDayCounts = json_encode(array_column($opensByDay,'cnt'));
?>

<div class="p-6 space-y-5">

  <!-- Header -->
  <div class="flex items-start justify-between">
    <div class="flex items-center gap-4">
      <a href="/admin/campaigns.php" class="text-slate-400 hover:text-white">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
      </a>
      <div>
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-bold text-white"><?= e($campaign['name']) ?></h1>
          <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $statusColors[$campaign['status']] ?>"><?= ucfirst(e($campaign['status'])) ?></span>
        </div>
        <p class="text-sm text-slate-400 mt-0.5"><?= e($campaign['subject']) ?></p>
      </div>
    </div>
    <div class="flex gap-2">
      <?php if (in_array($campaign['status'],['draft','scheduled'])): ?>
      <form method="post" action="/admin/campaigns.php" onsubmit="return confirm('Start sending now?')">
        <input type="hidden" name="action" value="send">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-primary">🚀 Send Now</button>
      </form>
      <?php endif; ?>
      <a href="/admin/campaign_create.php?id=<?= $id ?>" class="btn btn-secondary">✏️ Edit</a>
    </div>
  </div>

  <!-- Stats -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <?php foreach ([
      ['Sent',       number_format((int)$campaign['send_count']), 'slate', '—'],
      ['Opens',      number_format((int)$campaign['open_count']).' ('.$openRate.'%)',  'indigo', $openRate.'%'],
      ['Clicks',     number_format((int)$campaign['click_count']).' ('.$clickRate.'%)', 'violet', $clickRate.'%'],
      ['Unsubscribes',number_format((int)$campaign['unsub_count']).' ('.$unsubRate.'%)', 'amber', $unsubRate.'%'],
    ] as [$label,$val,$color,$rate]): ?>
    <div class="card p-4">
      <p class="text-xs text-slate-500 uppercase tracking-wider font-semibold mb-1"><?= $label ?></p>
      <p class="text-xl font-black text-white"><?= $val ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Charts & Details Row -->
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

    <!-- Opens over time -->
    <div class="card p-5 xl:col-span-2">
      <h2 class="font-bold text-white mb-4">Opens Over Time</h2>
      <canvas id="opensChart" height="160"></canvas>
    </div>

    <!-- Campaign Info -->
    <div class="card p-5 space-y-4">
      <h2 class="font-bold text-white">Campaign Info</h2>
      <div class="space-y-3 text-sm">
        <div class="flex justify-between"><span class="text-slate-500">From</span><span class="text-slate-200 text-right"><?= e($campaign['from_name']) ?><br><span class="text-xs text-slate-500"><?= e($campaign['from_email']) ?></span></span></div>
        <div class="flex justify-between"><span class="text-slate-500">Lists</span><span class="text-slate-200 text-right text-xs"><?= e(implode(', ', array_column($lists,'name')) ?: '—') ?></span></div>
        <div class="flex justify-between"><span class="text-slate-500">Created</span><span class="text-slate-200"><?= date('M j, Y', strtotime($campaign['created_at'])) ?></span></div>
        <?php if ($campaign['sent_at']): ?><div class="flex justify-between"><span class="text-slate-500">Sent At</span><span class="text-slate-200"><?= date('M j, Y H:i', strtotime($campaign['sent_at'])) ?></span></div><?php endif; ?>
        <div class="flex justify-between"><span class="text-slate-500">Bounces</span><span class="text-<?= $bounceRate > 5 ? 'red' : 'slate' ?>-400"><?= number_format((int)$campaign['bounce_count']) ?> (<?= $bounceRate ?>%)</span></div>
      </div>
      <!-- Funnel -->
      <div class="pt-3 border-t border-white/5 space-y-1.5">
        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Funnel</p>
        <?php
        $funnelSteps = [
          ['Sent',100,'#64748b'],
          ['Opened',$openRate,'#6366f1'],
          ['Clicked',$clickRate,'#8b5cf6'],
        ];
        foreach ($funnelSteps as [$fl, $fpct, $fcolor]):
        ?>
        <div class="flex items-center gap-2">
          <span class="text-xs text-slate-500 w-14"><?= $fl ?></span>
          <div class="flex-1 h-2 bg-slate-800 rounded-full overflow-hidden">
            <div class="h-full rounded-full" style="width:<?= min(100,$fpct) ?>%;background:<?= $fcolor ?>;"></div>
          </div>
          <span class="text-xs font-bold text-slate-300 w-10 text-right"><?= $fpct ?>%</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Bottom Row -->
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

    <!-- Top clicked URLs -->
    <div class="card p-5">
      <h2 class="font-bold text-white mb-4">Top Clicked Links</h2>
      <?php if (empty($topClicks)): ?>
      <p class="text-slate-500 text-sm text-center py-6">No clicks tracked yet.</p>
      <?php else: ?>
      <div class="space-y-2">
        <?php $totalClicks = array_sum(array_column($topClicks,'c')); ?>
        <?php foreach ($topClicks as $click): ?>
        <div>
          <div class="flex justify-between text-xs mb-1">
            <a href="<?= e($click['url']) ?>" target="_blank" class="text-indigo-400 hover:text-indigo-300 truncate max-w-xs" title="<?= e($click['url']) ?>"><?= e(mb_strimwidth($click['url'],0,55,'…')) ?></a>
            <span class="text-slate-400 ml-2"><?= $click['c'] ?> clicks</span>
          </div>
          <div class="h-1 bg-slate-800 rounded-full"><div class="h-full rounded-full bg-indigo-500" style="width:<?= $totalClicks>0?round($click['c']/$totalClicks*100):0 ?>%"></div></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Recent opens -->
    <div class="card p-5">
      <h2 class="font-bold text-white mb-4">Recent Opens</h2>
      <?php if (empty($recentOpens)): ?>
      <p class="text-slate-500 text-sm text-center py-6">No opens tracked yet.</p>
      <?php else: ?>
      <div class="space-y-2">
        <?php foreach ($recentOpens as $o): ?>
        <div class="flex items-center justify-between py-1 border-b border-white/5 last:border-0">
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)"><?= strtoupper(substr($o['first_name']?:$o['email'],0,1)) ?></div>
            <span class="text-xs text-slate-200"><?= e($o['email']) ?></span>
          </div>
          <span class="text-xs text-slate-500"><?= timeAgo($o['opened_at']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
(function() {
  const ctx = document.getElementById('opensChart')?.getContext('2d');
  if (!ctx) return;
  new Chart(ctx, {
    type:'bar',
    data:{
      labels: <?= $openDayLabels ?>,
      datasets:[{label:'Opens',data:<?= $openDayCounts ?>,backgroundColor:'rgba(99,102,241,0.6)',borderRadius:4}]
    },
    options:{
      responsive:true,maintainAspectRatio:true,
      plugins:{legend:{display:false}},
      scales:{
        x:{grid:{color:'rgba(255,255,255,0.04)'},ticks:{color:'#64748b',font:{size:10}}},
        y:{grid:{color:'rgba(255,255,255,0.04)'},ticks:{color:'#64748b',font:{size:10}},beginAtZero:true}
      }
    }
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
