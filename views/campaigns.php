<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Campaigns</h1>
        <p>Manage drafts, configure start schedules, monitor sent statistics, and control active sending queues.</p>
    </div>
    <div>
        <a href="<?= e(getSetting('app_url')) ?>/campaigns/create" class="btn btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            New Campaign
        </a>
    </div>
</div>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Campaign Details</th>
                <th>Status</th>
                <th style="text-align: right;">Pending</th>
                <th style="text-align: right;">Sent</th>
                <th style="text-align: right;">Unique Opens</th>
                <th style="text-align: right;">Unique Clicks</th>
                <th style="text-align: right;">Bounces</th>
                <th>Created At</th>
                <th style="width: 180px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($campaigns)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; color: var(--theme-dark-slate); padding: 40px;">No campaigns created yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($campaigns as $c): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600; color: var(--theme-dark); margin-bottom: 2px;"><?= e($c['name']) ?></div>
                            <div style="font-size: 12px; color: var(--theme-dark-slate); margin-bottom: 2px;">Subject: <?= e($c['subject']) ?></div>
                            <?php if ($c['scheduled_at']): ?>
                                <div style="font-size: 11px; background-color: var(--warning-light); color: var(--theme-dark); font-weight: 600; padding: 2px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; border: 1px solid rgba(255,186,82,0.2);">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                    Scheduled: <?= date('M j, Y H:i', strtotime($c['scheduled_at'])) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= e($c['status']) ?>">
                                <?= e($c['status']) ?>
                            </span>
                        </td>
                        <td style="text-align: right; font-weight: 500; color: var(--warning);"><?= number_format((int)$c['pending_count']) ?></td>
                        <td style="text-align: right; font-weight: 500;"><?= number_format((int)$c['send_count']) ?></td>
                        <td style="text-align: right; font-weight: 500;">
                            <?= number_format((int)$c['open_count']) ?> 
                            <span style="font-size: 11px; font-weight: 400; color: var(--theme-dark-slate); margin-left: 4px;">(<?= e($c['open_rate'] ?? '0.0') ?>%)</span>
                        </td>
                        <td style="text-align: right; font-weight: 500;">
                            <?= number_format((int)$c['click_count']) ?>
                            <span style="font-size: 11px; font-weight: 400; color: var(--theme-dark-slate); margin-left: 4px;">(<?= e($c['click_rate'] ?? '0.0') ?>%)</span>
                        </td>
                        <td style="text-align: right; color: var(--danger); font-weight: 500;"><?= number_format((int)$c['bounce_count']) ?></td>
                        <td style="color: var(--theme-dark-slate);"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                        <td>
                            <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                                <a href="<?= e(getSetting('app_url')) ?>/campaigns/edit?id=<?= e($c['id']) ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">Edit</a>
 
                                <?php if ($c['status'] === 'sending' || $c['status'] === 'queued'): ?>
                                    <form method="post" action="?action=pause&id=<?= e($c['id']) ?>" style="margin: 0;">
                                        <?= Auth::csrfField() ?>
                                        <button type="submit" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">Pause</button>
                                    </form>
                                <?php elseif ($c['status'] === 'paused'): ?>
                                    <form method="post" action="?action=resume&id=<?= e($c['id']) ?>" style="margin: 0;">
                                        <?= Auth::csrfField() ?>
                                        <button type="submit" class="btn btn-primary" style="padding: 4px 8px; font-size: 11px;">Resume</button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="post" action="?action=delete&id=<?= e($c['id']) ?>" onsubmit="return confirm('Are you sure you want to delete this campaign? This will remove all associated logs and cancel pending queues.');" style="margin: 0;">
                                    <?= Auth::csrfField() ?>
                                    <button type="submit" class="btn btn-danger" style="padding: 4px 8px; font-size: 11px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
