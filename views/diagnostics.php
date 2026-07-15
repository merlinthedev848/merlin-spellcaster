<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Diagnostics & System Health</h1>
        <p>Check the integration health, permissions, email deliverability queues, and SMTP error logs.</p>
    </div>
</div>

<div class="grid grid-2" style="align-items: start; margin-bottom: 24px;">
    <!-- System Checks -->
    <div class="card">
        <div class="card-header"><span class="card-title">Integration & Environment Health Checks</span></div>
        <div style="display: flex; flex-direction: column; gap: 16px;">
            <?php foreach ($checks as $key => $check): 
                $color = 'var(--theme-dark-slate)';
                $icon = '';
                if ($check['status'] === 'pass') {
                    $color = 'var(--success)';
                    $icon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="margin-top:2px;"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                } elseif ($check['status'] === 'warn') {
                    $color = 'var(--warning)';
                    $icon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="margin-top:2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
                } else {
                    $color = 'var(--danger)';
                    $icon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="margin-top:2px;"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';
                }
            ?>
                <div style="display: flex; gap: 12px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
                    <div style="color: <?= $color ?>; flex-shrink: 0;"><?= $icon ?></div>
                    <div>
                        <span style="font-weight: 600; font-size: 14px; color: var(--theme-dark); display: block;"><?= e($check['name']) ?></span>
                        <span style="color: var(--theme-dark-slate); font-size: 13px;"><?= e($check['message']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Email Queue Stats -->
        <div class="card">
            <div class="card-header"><span class="card-title">Email Queue Status Monitor</span></div>
            <div class="grid grid-2" style="gap: 16px; margin-bottom: 0;">
                <div style="background-color: var(--theme-bg); padding: 12px; border-radius: 6px; border: 1px solid var(--theme-border); text-align: center;">
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Pending Queue</span>
                    <span style="font-size: 20px; font-weight: 700; color: var(--theme-dark);"><?= number_format((int)$queueMetrics['pending']) ?></span>
                </div>
                <div style="background-color: var(--theme-blurple-light); padding: 12px; border-radius: 6px; border: 1px solid rgba(99,91,255,0.1); text-align: center;">
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-blurple); font-weight: 600; display: block; margin-bottom: 4px;">Active Sending</span>
                    <span style="font-size: 20px; font-weight: 700; color: var(--theme-blurple);"><?= number_format((int)$queueMetrics['sending']) ?></span>
                </div>
                <div style="background-color: var(--success-light); padding: 12px; border-radius: 6px; border: 1px solid rgba(0,212,178,0.1); text-align: center;">
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--success); font-weight: 600; display: block; margin-bottom: 4px;">Sent Deliveries</span>
                    <span style="font-size: 20px; font-weight: 700; color: var(--success);"><?= number_format((int)$queueMetrics['sent']) ?></span>
                </div>
                <div style="background-color: var(--danger-light); padding: 12px; border-radius: 6px; border: 1px solid rgba(255,91,96,0.1); text-align: center;">
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--danger); font-weight: 600; display: block; margin-bottom: 4px;">Failed Deliveries</span>
                    <span style="font-size: 20px; font-weight: 700; color: var(--danger);"><?= number_format((int)$queueMetrics['failed']) ?></span>
                </div>
            </div>
        </div>

        <!-- Queue Send Throttling Estimates -->
        <?php if (!empty($queueEstimates)): ?>
            <div class="card">
                <div class="card-header"><span class="card-title">Campaign Delivery Speed Estimates</span></div>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($queueEstimates as $est): 
                        $maxH = (int)$est['max_per_hour'];
                        $pending = (int)$est['pending_count'];
                        if ($maxH > 0) {
                            $hours = round($pending / $maxH, 1);
                            $days = round($pending / ($maxH * 24), 1);
                            $estStr = "{$hours} hours" . ($days >= 1.0 ? " (~{$days} days)" : "");
                            $limitStr = "Throttled at {$maxH}/hour";
                        } else {
                            $estStr = "Sends next cron run";
                            $limitStr = "Unlimited (Throttled by cron limit)";
                        }
                    ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--theme-border); padding-bottom: 8px;">
                            <div>
                                <span style="font-weight: 600; font-size: 13px; color: var(--theme-dark); display: block;"><?= e($est['name']) ?></span>
                                <span style="color: var(--theme-dark-slate); font-size: 11px;"><?= $limitStr ?> • <strong><?= number_format($pending) ?></strong> pending</span>
                            </div>
                            <div style="text-align: right;">
                                <span style="font-size: 12px; font-weight: 700; color: var(--theme-blurple); background-color: var(--theme-blurple-light); padding: 4px 8px; border-radius: 6px;"><?= $estStr ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Automation queue stats -->
        <div class="card">
            <div class="card-header"><span class="card-title">Automation Trigger Queue</span></div>
            <div class="grid grid-2" style="gap: 16px; margin-bottom: 0;">
                <div style="background-color: var(--theme-bg); padding: 12px; border-radius: 6px; border: 1px solid var(--theme-border); text-align: center;">
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Waiting Events</span>
                    <span style="font-size: 20px; font-weight: 700; color: var(--theme-dark);"><?= number_format((int)$autoMetrics['pending']) ?></span>
                </div>
                <div style="background-color: var(--success-light); padding: 12px; border-radius: 6px; border: 1px solid rgba(0,212,178,0.1); text-align: center;">
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--success); font-weight: 600; display: block; margin-bottom: 4px;">Completed Runs</span>
                    <span style="font-size: 20px; font-weight: 700; color: var(--success);"><?= number_format((int)$autoMetrics['completed']) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Failed Email Queue Logs -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <span class="card-title" style="color: var(--danger);">Latest Email Delivery Failures</span>
            <span style="font-size: 11px; background-color: var(--danger-light); color: var(--danger); font-weight: 600; padding: 2px 6px; border-radius: 4px;">Error Logs</span>
        </div>
        <form action="<?= e(getSetting('app_url')) ?>/diagnostics/clear-logs" method="post" onsubmit="return confirm('Are you sure you want to permanently clear all failed email delivery logs?');" style="margin: 0;">
            <?= Auth::csrfField() ?>
            <button type="submit" class="btn" style="background-color: var(--danger-light); color: var(--danger); border: 1px solid rgba(255,91,96,0.2); font-size: 12px; padding: 6px 12px;">Clear Logs Permanently</button>
        </form>
    </div>
    
    <div class="table-wrapper" style="border: none; box-shadow: none;">
        <table style="width: 100%;">
            <thead>
                <tr>
                    <th style="padding: 10px 12px;">Recipient</th>
                    <th style="padding: 10px 12px;">Campaign</th>
                    <th style="padding: 10px 12px; text-align: center;">Retries</th>
                    <th style="padding: 10px 12px;">Send Date</th>
                    <th style="padding: 10px 12px;">Raw SMTP Error Description</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($failedItems)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--theme-dark-slate); padding: 30px 0;">No delivery failures found. Your SMTP gateway is working correctly!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($failedItems as $item): ?>
                        <tr>
                            <td style="font-weight: 600; padding: 12px;"><?= e($item['email']) ?></td>
                            <td style="color: var(--theme-dark-slate); padding: 12px;"><?= e($item['campaign_name']) ?></td>
                            <td style="text-align: center; padding: 12px;"><?= (int)$item['attempts'] ?></td>
                            <td style="font-size: 12px; color: var(--theme-dark-slate); padding: 12px;"><?= date('M j, Y H:i:s', strtotime($item['send_at'])) ?></td>
                            <td style="padding: 12px;">
                                <div style="background-color: var(--danger-light); color: var(--danger); font-family: monospace; font-size: 12px; padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(255,91,96,0.1); max-width: 480px; overflow-x: auto; white-space: pre-wrap; word-break: break-all;">
                                    <?= e($item['error_message']) ?: 'Unknown connection error' ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
