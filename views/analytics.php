<?php
declare(strict_types=1);

// Calculate totals across all fetched campaigns
$sumSent = 0;
$sumUniqueOpens = 0;
$sumUniqueClicks = 0;
foreach ($campaigns as $c) {
    $sumSent += (int)$c['send_count'];
    $sumUniqueOpens += (int)$c['unique_opens'];
    $sumUniqueClicks += (int)$c['unique_clicks'];
}
$avgCtor = $sumUniqueOpens > 0 ? round(($sumUniqueClicks / $sumUniqueOpens) * 100, 1) : 0.0;
$avgOpenRate = $sumSent > 0 ? round(($sumUniqueOpens / $sumSent) * 100, 1) : 0.0;
$avgClickRate = $sumSent > 0 ? round(($sumUniqueClicks / $sumSent) * 100, 1) : 0.0;
?>

<div class="header-actions" style="margin-bottom: 20px;">
    <div class="page-title">
        <h1>Campaign Analytics</h1>
        <p>Granular reporting on unique user behaviors, conversion rates, and link popularity metrics.</p>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Row 1: High Level Aggregate Stats Cards & Visual Graph -->
<div class="grid grid-2" style="margin-bottom: 24px;">
    <!-- Chart Container -->
    <div class="card" style="padding: 20px; min-height: 300px; display: flex; flex-direction: column;">
        <h3 style="margin-bottom: 16px; color: var(--theme-dark); font-size: 14px;">Performance Overview</h3>
        <div style="flex-grow: 1; position: relative;">
            <canvas id="campaignChart"></canvas>
        </div>
    </div>
    <!-- Leftover removed -->

    <!-- Aggregate Stats Stacked -->
    <div style="display: flex; flex-direction: column; gap: 16px;">
        <!-- Avg Open Rate -->
        <div class="card" style="padding: 20px; flex: 1;">
            <div class="stat-group">
                <span class="stat-label">Unique Open Rate</span>
                <span class="stat-value" style="color: var(--theme-blurple);"><?= $avgOpenRate ?>%</span>
                <span class="stat-meta">
                    <span class="stat-trend-up" style="background-color: var(--theme-blurple-light); color: var(--theme-blurple);">✓ Active</span>
                    <span style="color: var(--theme-dark-slate);"><?= number_format($sumUniqueOpens) ?> unique readers</span>
                </span>
            </div>
        </div>

        <!-- Avg Click Rate -->
        <div class="card" style="padding: 20px; flex: 1;">
            <div class="stat-group">
                <span class="stat-label">Unique Click-Through Rate</span>
                <span class="stat-value" style="color: #00d4b2;"><?= $avgClickRate ?>%</span>
                <span class="stat-meta">
                    <span class="stat-trend-up" style="background-color: rgba(0,212,178,0.1); color: #09a287;">✓ Engaged</span>
                    <span style="color: var(--theme-dark-slate);"><?= number_format($sumUniqueClicks) ?> unique clickers</span>
                </span>
            </div>
        </div>
        
        <!-- CTOR -->
        <div class="card" style="padding: 20px; flex: 1;">
            <div class="stat-group">
                <span class="stat-label">Click-to-Open Rate (CTOR)</span>
                <span class="stat-value" style="color: var(--theme-dark);"><?= $avgCtor ?>%</span>
                <span class="stat-meta">
                    <span style="color: var(--theme-dark-slate); font-weight: 500;">Ratio of unique clicks to unique opens</span>
                </span>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const campaigns = <?= json_encode(array_reverse($campaigns)) ?>;
        const labels = campaigns.map(c => c.name.length > 20 ? c.name.substring(0, 20) + '...' : c.name);
        
        const openData = campaigns.map(c => c.send_count > 0 ? (c.unique_opens / c.send_count) * 100 : 0);
        const clickData = campaigns.map(c => c.send_count > 0 ? (c.unique_clicks / c.send_count) * 100 : 0);

        const ctx = document.getElementById('campaignChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Open Rate (%)',
                        data: openData,
                        borderColor: '#635bff',
                        backgroundColor: 'rgba(99, 91, 255, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Click Rate (%)',
                        data: clickData,
                        borderColor: '#00d4b2',
                        backgroundColor: 'rgba(0, 212, 178, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { callback: function(value) { return value + '%' } }
                    }
                }
            }
        });
    });
</script>

<!-- Row 2: Tabs Navigation System -->
<div style="display: flex; border-bottom: 1px solid var(--theme-border); margin-bottom: 24px; gap: 24px;">
    <button class="analytics-tab-btn active" onclick="switchTab(event, 'tab-campaigns')" style="background: none; border: none; border-bottom: 2px solid var(--theme-blurple); padding: 12px 4px; font-size: 14px; font-weight: 600; color: var(--theme-blurple); cursor: pointer; transition: all 0.15s ease;">
        Campaign Performance
    </button>
    <button class="analytics-tab-btn" onclick="switchTab(event, 'tab-links')" style="background: none; border: none; border-bottom: 2px solid transparent; padding: 12px 4px; font-size: 14px; font-weight: 600; color: var(--theme-dark-slate); cursor: pointer; transition: all 0.15s ease;">
        Link Clicks Leaderboard
    </button>
    <button class="analytics-tab-btn" onclick="switchTab(event, 'tab-logs')" style="background: none; border: none; border-bottom: 2px solid transparent; padding: 12px 4px; font-size: 14px; font-weight: 600; color: var(--theme-dark-slate); cursor: pointer; transition: all 0.15s ease;">
        Live Clicks Log
    </button>
</div>

<!-- TAB 1: Campaign Performance -->
<div id="tab-campaigns" class="analytics-tab-content">
    <div style="display: flex; flex-direction: column; gap: 16px;">
        <?php if (empty($campaigns)): ?>
            <div class="card" style="text-align: center; color: var(--theme-dark-slate); padding: 48px;">
                No sent campaigns found.
            </div>
        <?php else: ?>
            <?php foreach ($campaigns as $c): 
                $ctor = $c['unique_opens'] > 0 ? round(($c['unique_clicks'] / $c['unique_opens']) * 100, 1) : 0.0;
                $openPct = $c['send_count'] > 0 ? round(($c['unique_opens'] / $c['send_count']) * 100, 1) : 0.0;
                $clickPct = $c['send_count'] > 0 ? round(($c['unique_clicks'] / $c['send_count']) * 100, 1) : 0.0;
            ?>
                <div class="card" style="padding: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
                        <div>
                            <h3 style="margin-bottom: 4px; color: var(--theme-dark); font-size: 16px; font-weight: 700;"><?= e($c['name']) ?></h3>
                            <span style="font-size: 12px; color: var(--theme-dark-slate);">
                                <?php if (!empty($c['sent_at'])): ?>
                                    Sent on <?= date('M j, Y \a\t H:i', strtotime($c['sent_at'])) ?>
                                <?php else: ?>
                                    Active sending in progress...
                                <?php endif; ?>
                                to <strong><?= number_format((int)$c['send_count']) ?></strong> recipients
                            </span>
                        </div>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <div style="text-align: right;">
                                <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 2px;">CTOR</span>
                                <span class="badge badge-active" style="background-color: var(--theme-blurple-light); color: var(--theme-blurple); font-weight: 700; font-size: 13px; padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(99,91,255,0.1);"><?= $ctor ?>%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Metrics Grid -->
                    <div class="grid grid-3" style="gap: 24px; margin-bottom: 0;">
                        <!-- Opens Meter -->
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                                <span style="color: var(--theme-dark);">Unique Opens</span>
                                <span style="color: var(--theme-blurple);"><?= $c['unique_opens'] ?> (<?= $openPct ?>%)</span>
                            </div>
                            <div style="height: 8px; background-color: var(--theme-bg); border-radius: 4px; overflow: hidden; border: 1px solid var(--theme-border);">
                                <div style="width: <?= $openPct ?>%; height: 100%; background-color: var(--theme-blurple); border-radius: 4px;"></div>
                            </div>
                        </div>

                        <!-- Clicks Meter -->
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                                <span style="color: var(--theme-dark);">Unique Clicks</span>
                                <span style="color: #09a287;"><?= $c['unique_clicks'] ?> (<?= $clickPct ?>%)</span>
                            </div>
                            <div style="height: 8px; background-color: var(--theme-bg); border-radius: 4px; overflow: hidden; border: 1px solid var(--theme-border);">
                                <div style="width: <?= $clickPct ?>%; height: 100%; background-color: #00d4b2; border-radius: 4px;"></div>
                            </div>
                        </div>

                        <!-- Stats Overview Box -->
                        <div style="background-color: var(--theme-bg); padding: 12px 16px; border-radius: 6px; border: 1px solid var(--theme-border); display: flex; justify-content: space-around; align-items: center; text-align: center;">
                            <div>
                                <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 2px;">Opens</span>
                                <span style="font-weight: 700; color: var(--theme-dark); font-size: 15px;"><?= $c['unique_opens'] ?></span>
                            </div>
                            <div style="width: 1px; height: 24px; background-color: var(--theme-border);"></div>
                            <div>
                                <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 2px;">Clicks</span>
                                <span style="font-weight: 700; color: var(--theme-dark); font-size: 15px;"><?= $c['unique_clicks'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- TAB 2: Link Clicks Leaderboard -->
<div id="tab-links" class="analytics-tab-content" style="display: none;">
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="border-bottom: none; padding-bottom: 0; margin-bottom: 16px;">
            <span class="card-title">Top Clicked Link URLs</span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Destination Link URL</th>
                        <th>Parent Campaign</th>
                        <th style="text-align: center; width: 120px;">Total Clicks</th>
                        <th style="text-align: center; width: 120px;">Unique Clicks</th>
                        <th style="text-align: center; width: 150px;">Click Ratio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($linkStats)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--theme-dark-slate); padding: 40px 0;">
                                No click tracking data logged.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($linkStats as $link): 
                            $ratio = $link['total_clicks'] > 0 ? round(($link['unique_clicks'] / $link['total_clicks']) * 100, 1) : 0.0;
                        ?>
                            <tr>
                                <td style="max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600;">
                                    <a href="<?= e($link['url']) ?>" target="_blank" style="color: var(--theme-blurple); text-decoration: none;" title="<?= e($link['url']) ?>">
                                        <?= e($link['url']) ?>
                                    </a>
                                </td>
                                <td style="color: var(--theme-dark-slate); font-weight: 500;"><?= e($link['campaign_name']) ?></td>
                                <td style="text-align: center; font-weight: 600; color: var(--theme-dark);"><?= number_format((int)$link['total_clicks']) ?></td>
                                <td style="text-align: center; font-weight: 600; color: #00d4b2;"><?= number_format((int)$link['unique_clicks']) ?></td>
                                <td style="text-align: center;">
                                    <div style="font-size: 11px; font-weight: 600; color: var(--theme-dark-slate); margin-bottom: 4px;"><?= $ratio ?>% Unique</div>
                                    <div style="height: 4px; background-color: var(--theme-bg); border-radius: 2px; overflow: hidden; border: 1px solid var(--theme-border); max-width: 120px; margin: 0 auto;">
                                        <div style="width: <?= $ratio ?>%; height: 100%; background-color: #00d4b2; border-radius: 2px;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TAB 3: Live Clicks Log -->
<div id="tab-logs" class="analytics-tab-content" style="display: none;">
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="border-bottom: none; padding-bottom: 0; margin-bottom: 16px;">
            <span class="card-title">Real-Time Link Click Feed</span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Subscriber Profile</th>
                        <th>Campaign</th>
                        <th>URL Destination</th>
                        <th>IP Address</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clickLogs)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--theme-dark-slate); padding: 40px 0;">
                                No click events logged yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clickLogs as $log): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <span style="font-weight: 600; color: var(--theme-dark);"><?= e($log['first_name'] . ' ' . $log['last_name']) ?: 'Anonymous' ?></span>
                                        <span style="font-size: 11px; color: var(--theme-dark-slate);"><?= e($log['email']) ?></span>
                                    </div>
                                </td>
                                <td style="font-weight: 500; color: var(--theme-dark);"><?= e($log['campaign_name']) ?></td>
                                <td style="max-width: 350px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <a href="<?= e($log['url']) ?>" target="_blank" style="color: var(--theme-blurple); text-decoration: none;" title="<?= e($log['url']) ?>">
                                        <?= e($log['url']) ?>
                                    </a>
                                </td>
                                <td style="color: var(--theme-dark-slate); font-family: monospace; font-size: 12px;"><?= e($log['ip_address']) ?: '—' ?></td>
                                <td style="color: var(--theme-dark-slate); font-size: 13px;"><?= date('M j, Y H:i:s', strtotime($log['clicked_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function switchTab(event, tabId) {
        // Hide all tab contents
        const contents = document.querySelectorAll(".analytics-tab-content");
        contents.forEach(content => content.style.display = "none");

        // Deactivate all tab buttons
        const buttons = document.querySelectorAll(".analytics-tab-btn");
        buttons.forEach(btn => {
            btn.classList.remove("active");
            btn.style.color = "var(--theme-dark-slate)";
            btn.style.borderBottomColor = "transparent";
        });

        // Show active tab
        document.getElementById(tabId).style.display = "block";

        // Style active button
        const activeBtn = event.currentTarget;
        activeBtn.classList.add("active");
        activeBtn.style.color = "var(--theme-blurple)";
        activeBtn.style.borderBottomColor = "var(--theme-blurple)";
    }
</script>
