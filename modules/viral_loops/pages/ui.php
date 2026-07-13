<?php
declare(strict_types=1);
$pageTitle = 'Viral Loops';
require_once dirname(__DIR__, 3) . '/includes/header.php';

$topReferrers = $db->query("SELECT email, first_name, referral_count FROM subscribers WHERE referral_count > 0 ORDER BY referral_count DESC LIMIT 10")->fetchAll();
$appUrl = rtrim(getSetting('app_url', 'http://localhost'), '/');
?>

<div class="flex items-center gap-4 mb-8">
  <h1 class="text-2xl font-bold text-white">Viral Growth Engine</h1>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
  <div class="card p-6 border-t-4 border-fuchsia-500">
    <h2 class="text-lg font-bold text-white mb-4">How to Use Referral Links</h2>
    <p class="text-slate-300 text-sm mb-4 leading-relaxed">
        Every subscriber automatically gets a unique referral code. You can include their personalized referral link in any campaign by using the macro:
    </p>
    <div class="bg-slate-900 rounded p-3 mb-4 font-mono text-fuchsia-400 text-sm">
        {{referral_link}}
    </div>
    <p class="text-slate-300 text-sm mb-4 leading-relaxed">
        When they share this link and someone subscribes, their `referral_count` goes up.
    </p>
    <div class="bg-fuchsia-500/10 border border-fuchsia-500/20 rounded p-3 text-xs text-fuchsia-300">
        Note: To track referrals, ensure your subscribe form reads the `?ref=CODE` URL parameter and passes it to the API.
    </div>
  </div>
  
  <div class="card p-6">
    <h2 class="text-lg font-bold text-white mb-4">Top Referrers (Leaderboard)</h2>
    <table class="data-table w-full">
        <thead>
            <tr>
                <th>Subscriber</th>
                <th>Referrals</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$topReferrers): ?>
                <tr><td colspan="2" class="text-center text-slate-500 py-4">No referrals yet.</td></tr>
            <?php endif; ?>
            <?php foreach($topReferrers as $sub): ?>
                <tr>
                    <td class="text-sm">
                        <div class="font-semibold text-slate-200"><?= e($sub['first_name'] ?: 'Unknown') ?></div>
                        <div class="text-xs text-slate-400"><?= e($sub['email']) ?></div>
                    </td>
                    <td>
                        <span class="badge badge-success px-3 py-1 text-sm"><?= $sub['referral_count'] ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
  </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
