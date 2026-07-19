<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Dashboard</h1>
        <p>
            Get a real-time overview of your marketing metrics.
            <?php
            $lastCron = getSetting('last_cron_run', '');
            if ($lastCron !== ''):
                $lastCronTime = strtotime($lastCron);
                $isCronActive = (time() - $lastCronTime) < 900; // active in last 15 minutes
            ?>
                <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 12px; margin-left: 8px; vertical-align: middle; background-color: <?= $isCronActive ? 'rgba(74,222,128,0.15)' : 'rgba(239,68,68,0.15)' ?>; color: <?= $isCronActive ? '#15803d' : '#b91c1c' ?>;">
                    <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background-color: <?= $isCronActive ? '#22c55e' : '#ef4444' ?>;"></span>
                    Cron Run: <?= date('M j, H:i', $lastCronTime) ?>
                </span>
            <?php else: ?>
                <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 12px; margin-left: 8px; vertical-align: middle; background-color: rgba(100,116,139,0.15); color: #475569;">
                    Cron status: Not Run Yet
                </span>
            <?php endif; ?>
        </p>
    </div>
    <div>
        <a href="<?= e(getSetting('app_url')) ?>/campaigns/create" class="btn btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            New Campaign
        </a>
    </div>
</div>

<style>
@keyframes scPulse {
    0% { transform: scale(0.9); box-shadow: 0 0 0 0 rgba(99, 91, 255, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(99, 91, 255, 0); }
    100% { transform: scale(0.9); box-shadow: 0 0 0 0 rgba(99, 91, 255, 0); }
}
</style>

<?php if ($pendingEmailsCount > 0): ?>
    <div style="background-color: #f5f6ff; border: 1px solid rgba(99,91,255,0.15); border-radius: 8px; padding: 14px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
        <div style="display: flex; gap: 12px; align-items: center;">
            <div style="background-color: var(--theme-blurple); color: white; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; position: relative;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                <span style="position: absolute; top: -1px; right: -1px; width: 8px; height: 8px; border-radius: 50%; background-color: #00d4b2; border: 2px solid #f5f6ff; display: inline-block; animation: scPulse 2s infinite;"></span>
            </div>
            <div>
                <span style="font-weight: 700; font-size: 14px; color: var(--theme-dark); display: block; margin-bottom: 2px;">Active Campaign Send in Progress</span>
                <span style="font-size: 12px; color: var(--theme-dark-slate);">There are <strong><?= number_format($pendingEmailsCount) ?></strong> emails pending in the outgoing delivery queue.</span>
            </div>
        </div>
        <div>
            <a href="<?= e(getSetting('app_url')) ?>/diagnostics" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px; font-weight: 600;">
                Monitor Delivery & Speed →
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- theme Stats Grid -->
<div class="grid grid-4">
    <div class="card">
        <div class="stat-group">
            <span class="stat-label">Total Emails Sent</span>
            <span class="stat-value"><?= number_format($totalSent) ?></span>
            <span class="stat-meta">
                <span style="font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 4px; background-color: var(--theme-bg); color: var(--theme-dark-slate); border: 1px solid var(--theme-border);">● Broadcast</span>
                <span style="color: var(--theme-dark-slate);">delivery attempts total</span>
            </span>
        </div>
    </div>

    <div class="card">
        <div class="stat-group">
            <span class="stat-label">Active Subscribers</span>
            <span class="stat-value"><?= number_format($subCount) ?></span>
            <span class="stat-meta">
                <span class="stat-trend-up">✓ Live</span>
                <span style="color: var(--theme-dark-slate);">contacts receiving campaigns</span>
            </span>
        </div>
    </div>
    
    <div class="card">
        <div class="stat-group">
            <span class="stat-label">Average Open Rate</span>
            <span class="stat-value"><?= e($openRate) ?>%</span>
            <span class="stat-meta">
                <span class="stat-trend-up">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="display:inline; vertical-align:middle; margin-right:2px;"><polyline points="18 15 12 9 6 15"></polyline></svg>
                    Opens
                </span>
                <span style="color: var(--theme-dark-slate);"><?= number_format($totalOpens) ?> total views logged</span>
            </span>
        </div>
    </div>

    <div class="card">
        <div class="stat-group">
            <span class="stat-label">Average Click Rate</span>
            <span class="stat-value"><?= e($clickRate) ?>%</span>
            <span class="stat-meta">
                <span class="stat-trend-up">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="display:inline; vertical-align:middle; margin-right:2px;"><polyline points="18 15 12 9 6 15"></polyline></svg>
                    Clicks
                </span>
                <span style="color: var(--theme-dark-slate);"><?= number_format($totalClicks) ?> total redirects tracked</span>
            </span>
        </div>
    </div>
</div>

<!-- Chart Section -->
<div class="grid" style="grid-template-columns: 2fr 1fr;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Campaign Performance</span>
            <span style="font-size: 12px; color: var(--theme-dark-slate);">Last 10 Sent Campaigns</span>
        </div>
        <div style="position: relative; height: 300px; width: 100%;">
            <canvas id="campaignChart"></canvas>
        </div>
    </div>
    
    <!-- Quick Actions Card -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Quick Actions</span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 12px; flex-grow: 1; justify-content: center;">
            <a href="<?= e(getSetting('app_url')) ?>/contacts?action=add" class="btn btn-secondary" style="justify-content: flex-start; padding: 12px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>
                Add Single Contact
            </a>
            <a href="<?= e(getSetting('app_url')) ?>/contacts?action=import" class="btn btn-secondary" style="justify-content: flex-start; padding: 12px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                Import Contacts CSV
            </a>
            <a href="<?= e(getSetting('app_url')) ?>/settings" class="btn btn-secondary" style="justify-content: flex-start; padding: 12px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                Configure SMTP Mailer
            </a>
            <form action="<?= e(getSetting('app_url')) ?>/cron" method="POST">
                <?= Auth::csrfField() ?>
                <button type="submit" class="btn btn-primary" style="width:100%; justify-content: flex-start; padding: 12px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                    Trigger Queue Process
                </button>
            </form>
        </div>
    </div>
</div>

<!-- CRM Log Feed Section -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Live CRM Activity Log</span>
        <span style="font-size: 12px; color: var(--theme-dark-slate);">Real-time Subscriber Action Feed</span>
    </div>
    
    <?php if (empty($activities)): ?>
        <p style="text-align: center; color: var(--theme-dark-slate); padding: 40px;">No recent subscriber activity logged.</p>
    <?php else: ?>
        <ul class="timeline">
            <?php foreach ($activities as $act): 
                $class = '';
                if ($act['activity_type'] === 'bounce') $class = 'bounce';
                if ($act['activity_type'] === 'unsub') $class = 'unsub';
                if ($act['activity_type'] === 'open' || $act['activity_type'] === 'click') $class = 'open';
            ?>
                <li class="timeline-item <?= $class ?>">
                    <div class="timeline-time"><?= date('M j, Y H:i:s', strtotime($act['created_at'])) ?></div>
                    <div class="timeline-content">
                        <a href="<?= e(getSetting('app_url')) ?>/contacts/view?id=<?= e($act['subscriber_id']) ?>" style="font-weight: 600; color: var(--theme-blurple); text-decoration: none;">
                            <?= e(trim($act['first_name'] . ' ' . $act['last_name'])) !== '' ? e(trim($act['first_name'] . ' ' . $act['last_name'])) : e($act['email']) ?>
                        </a>
                        <span style="color: var(--theme-dark-slate); font-weight: 400;">
                            <?= e($act['description']) ?>
                        </span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<!-- Chart Script -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('campaignChart').getContext('2d');
        
        const labels = <?= json_encode(array_column($chartCampaigns, 'name'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const sentData = <?= json_encode(array_column($chartCampaigns, 'send_count'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const openData = <?= json_encode(array_column($chartCampaigns, 'open_count'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const clickData = <?= json_encode(array_column($chartCampaigns, 'click_count'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const notOpenedData = sentData.map((sent, i) => Math.max(0, sent - openData[i]));

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Sent',
                        data: sentData,
                        backgroundColor: '#e3e8ee',
                        borderRadius: 4,
                        barPercentage: 0.8
                    },
                    {
                        label: 'Opened',
                        data: openData,
                        backgroundColor: '#635bff',
                        borderRadius: 4,
                        barPercentage: 0.8
                    },
                    {
                        label: 'Not Opened',
                        data: notOpenedData,
                        backgroundColor: '#ffb3c6',
                        borderRadius: 4,
                        barPercentage: 0.8
                    },
                    {
                        label: 'Clicks',
                        data: clickData,
                        backgroundColor: '#00d4b2',
                        borderRadius: 4,
                        barPercentage: 0.8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { family: 'Inter', size: 12 },
                            color: '#4f5b76'
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', size: 11 }, color: '#4f5b76' }
                    },
                    y: {
                        grid: { color: '#e3e8ee', drawBorder: false },
                        ticks: { font: { family: 'Inter', size: 11 }, color: '#4f5b76' }
                    }
                }
            }
        });
    });
</script>
