<?php
declare(strict_types=1);
?>

<div class="header-actions" style="margin-bottom: 20px;">
    <div class="page-title">
        <h1>Outbound Email Queue</h1>
        <p>Inspect live pending broadcasts, retry failed deliveries, and flush or cancel queue items.</p>
    </div>
    
    <div style="display: flex; gap: 10px; align-items: center;">
        <!-- Global Queue Pause/Resume Toggle -->
        <form method="post" action="?action=toggle_pause" style="margin: 0;">
            <?= Auth::csrfField() ?>
            <button type="submit" class="btn <?= $queuePaused ? 'btn-danger' : 'btn-secondary' ?>" style="font-weight: 600; padding: 8px 16px;">
                <?= $queuePaused ? '▶ Resume Global Queue' : '⏸ Pause Global Queue' ?>
            </button>
        </form>

        <?php if ($failedCount > 0): ?>
            <form method="post" action="?action=retry_failed" style="margin: 0;" onsubmit="return confirm('Retry all failed queue items?');">
                <?= Auth::csrfField() ?>
                <button type="submit" class="btn btn-secondary" style="font-weight: 600; padding: 8px 16px;">🔄 Retry <?= $failedCount ?> Failed Items</button>
            </form>
        <?php endif; ?>

        <?php if ($pendingCount > 0): ?>
            <form method="post" action="?action=process_all" style="margin: 0;">
                <?= Auth::csrfField() ?>
                <button type="submit" class="btn btn-primary" style="font-weight: 600; padding: 8px 16px;">⚡ Process & Send All Pending Emails (<?= $pendingCount ?>)</button>
            </form>
            <form method="post" action="?action=flush_pending" style="margin: 0;" onsubmit="return confirm('WARNING: Are you sure you want to flush and remove all <?= $pendingCount ?> pending emails from the queue?');">
                <?= Auth::csrfField() ?>
                <button type="submit" class="btn btn-danger" style="font-weight: 600; padding: 8px 16px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2 2v2"></path></svg>Flush <?= $pendingCount ?> Pending Emails</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Queue Metrics Overview Grid -->
<div class="grid grid-4" style="margin-bottom: 24px; gap: 16px;">
    <div class="card" style="padding: 16px; border-left: 4px solid var(--warning);">
        <span style="font-size: 11px; font-weight: 700; color: var(--theme-dark-slate); text-transform: uppercase;">Pending Queue</span>
        <h2 style="font-size: 28px; font-weight: 700; color: var(--warning); margin-top: 4px;"><?= number_format($pendingCount) ?></h2>
    </div>
    <div class="card" style="padding: 16px; border-left: 4px solid var(--theme-blurple);">
        <span style="font-size: 11px; font-weight: 700; color: var(--theme-dark-slate); text-transform: uppercase;">Sending / Active</span>
        <h2 style="font-size: 28px; font-weight: 700; color: var(--theme-blurple); margin-top: 4px;"><?= number_format($sendingCount) ?></h2>
    </div>
    <div class="card" style="padding: 16px; border-left: 4px solid var(--success);">
        <span style="font-size: 11px; font-weight: 700; color: var(--theme-dark-slate); text-transform: uppercase;">Sent / Delivered</span>
        <h2 style="font-size: 28px; font-weight: 700; color: var(--success); margin-top: 4px;"><?= number_format($sentCount) ?></h2>
    </div>
    <div class="card" style="padding: 16px; border-left: 4px solid var(--danger);">
        <span style="font-size: 11px; font-weight: 700; color: var(--theme-dark-slate); text-transform: uppercase;">Failed Deliveries</span>
        <h2 style="font-size: 28px; font-weight: 700; color: var(--danger); margin-top: 4px;"><?= number_format($failedCount) ?></h2>
    </div>
</div>

<!-- Controls & Search Bar -->
<div class="card" style="padding: 16px; margin-bottom: 24px;">
    <form method="get" action="" style="display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-bottom: 0;">
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <!-- Status Tabs -->
            <a href="?status=" class="btn <?= $statusFilter === '' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 6px 14px; font-size: 12px; font-weight: 600;">All Items</a>
            <a href="?status=pending" class="btn <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 6px 14px; font-size: 12px; font-weight: 600;">Pending (<?= $pendingCount ?>)</a>
            <a href="?status=sent" class="btn <?= $statusFilter === 'sent' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 6px 14px; font-size: 12px; font-weight: 600;">Sent</a>
            <a href="?status=failed" class="btn <?= $statusFilter === 'failed' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 6px 14px; font-size: 12px; font-weight: 600;">Failed (<?= $failedCount ?>)</a>
        </div>

        <div style="display: flex; gap: 12px; align-items: center;">
            <select name="campaign_id" onchange="this.form.submit()" class="form-control" style="margin-bottom: 0; max-width: 200px; font-size: 13px; height: auto;">
                <option value="0">-- All Campaigns --</option>
                <?php foreach ($campaigns as $camp): ?>
                    <option value="<?= $camp['id'] ?>" <?= $campaignId === (int)$camp['id'] ? 'selected' : '' ?>><?= e($camp['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <input class="form-control" type="text" name="q" value="<?= e($search) ?>" placeholder="Search recipient email or campaign..." style="max-width: 280px; margin-bottom: 0; font-size: 13px;">
            <button type="submit" class="btn btn-secondary" style="font-size: 13px;">Filter</button>
            <?php if ($statusFilter !== '' || $search !== '' || $campaignId > 0): ?>
                <a href="?" class="btn btn-secondary" style="font-size: 13px;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Queue Items Table -->
<div class="card" style="padding: 24px;">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Recipient Email</th>
                    <th>Campaign</th>
                    <th style="width: 120px;">Status</th>
                    <th>Scheduled Send At</th>
                    <th style="width: 80px; text-align: center;">Attempts</th>
                    <th>Error / Notes</th>
                    <th style="width: 140px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queueItems)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--theme-dark-slate); padding: 40px;">No queue items matching current criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($queueItems as $item): ?>
                        <tr>
                            <td style="font-size: 12px; color: var(--theme-dark-slate);">#<?= $item['id'] ?></td>
                            <td style="font-weight: 600; color: var(--theme-dark);">
                                <?= e($item['recipient_email'] ?: "Subscriber #{$item['subscriber_id']}") ?>
                                <?php if (!empty($item['first_name'])): ?>
                                    <span style="font-size: 11px; color: var(--theme-dark-slate); font-weight: normal;">(<?= e($item['first_name']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-weight: 500; color: var(--theme-dark);"><?= e($item['campaign_name'] ?: "Campaign #{$item['campaign_id']}") ?></span>
                                <div style="font-size: 11px; color: var(--theme-dark-slate); max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= e($item['campaign_subject'] ?? '') ?></div>
                            </td>
                            <td>
                                <?php if ($item['status'] === 'pending'): ?>
                                    <span class="badge" style="background: rgba(234,179,8,0.15); color: #ca8a04; font-weight: 700;">PENDING</span>
                                <?php elseif ($item['status'] === 'sending'): ?>
                                    <span class="badge" style="background: rgba(99,91,255,0.15); color: var(--theme-blurple); font-weight: 700;">SENDING</span>
                                <?php elseif ($item['status'] === 'sent'): ?>
                                    <span class="badge badge-active" style="background: rgba(52,211,153,0.15); color: #059669; font-weight: 700;">SENT</span>
                                <?php elseif ($item['status'] === 'failed'): ?>
                                    <span class="badge badge-unsubscribed" style="background: rgba(239,68,68,0.15); color: #dc2626; font-weight: 700;">FAILED</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 12px; color: var(--theme-dark-slate);">
                                <?= date('M j, Y H:i:s', strtotime($item['send_at'])) ?>
                            </td>
                            <td style="text-align: center; font-size: 12px; font-weight: 600; color: var(--theme-dark);">
                                <?= $item['attempts'] ?>
                            </td>
                            <td style="font-size: 11px; color: var(--danger); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($item['error_message'] ?? '') ?>">
                                <?= e($item['error_message'] ? $item['error_message'] : '—') ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                    <?php if ($item['status'] === 'pending' || $item['status'] === 'failed'): ?>
                                        <form method="post" action="?action=send_now&id=<?= $item['id'] ?>" style="margin: 0;">
                                            <?= Auth::csrfField() ?>
                                            <button type="submit" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;" title="Send immediately">⚡ Send</button>
                                        </form>
                                        <form method="post" action="?action=cancel_item&id=<?= $item['id'] ?>" onsubmit="return confirm('Cancel this email?');" style="margin: 0;">
                                            <?= Auth::csrfField() ?>
                                            <button type="submit" class="btn btn-danger" style="padding: 4px 8px; font-size: 11px;" title="Cancel item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--theme-border);">
            <span style="font-size: 12px; color: var(--theme-dark-slate);">
                Showing <?= number_format($offset + 1) ?> to <?= number_format(min($totalItems, $offset + $limit)) ?> of <?= number_format($totalItems) ?> queue items
            </span>
            <div style="display: flex; gap: 6px;">
                <?php if ($page > 1): ?>
                    <a href="?page=1&status=<?= e($statusFilter) ?>&q=<?= e($search) ?>&campaign_id=<?= $campaignId ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">« First</a>
                    <a href="?page=<?= $page - 1 ?>&status=<?= e($statusFilter) ?>&q=<?= e($search) ?>&campaign_id=<?= $campaignId ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">‹ Prev</a>
                <?php endif; ?>

                <span class="btn btn-primary" style="padding: 4px 10px; font-size: 11px; font-weight: 700; background-color: var(--theme-blurple);"><?= $page ?> / <?= $totalPages ?></span>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&status=<?= e($statusFilter) ?>&q=<?= e($search) ?>&campaign_id=<?= $campaignId ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">Next ›</a>
                    <a href="?page=<?= $totalPages ?>&status=<?= e($statusFilter) ?>&q=<?= e($search) ?>&campaign_id=<?= $campaignId ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">Last »</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
