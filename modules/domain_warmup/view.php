<?php
declare(strict_types=1);

$runUrl = rtrim(getSetting('app_url'), '/') . '/warmup/run';
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Domain Warm-Up Engine</h1>
        <p>Establish a solid sender reputation with Gmail, Outlook, and other email providers by gradually warming your IP/sending domain.</p>
    </div>
</div>

<div class="grid grid-2">
    <!-- Config Card -->
    <div class="card" style="display: flex; flex-direction: column; gap: 20px;">
        <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 16px; margin-bottom: 0;">
            <span class="card-title">Engine Configuration</span>
        </div>
        
        <form method="post" action="?action=update">
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; font-size: 13px; color: var(--theme-dark);">
                    <input type="checkbox" name="warmup_active" value="1" <?= $warmupActive ? 'checked' : '' ?> style="cursor: pointer; accent-color: var(--theme-blurple);">
                    Activate Warm-Up Engine Throttling
                </label>
            </div>

            <div class="form-group">
                <label class="form-label" for="warmup_seed_list">Warm-up Seed List</label>
                <select class="form-control" id="warmup_seed_list" name="warmup_seed_list" required>
                    <option value="">-- Select Target Seed List --</option>
                    <?php foreach ($lists as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= $seedListId === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 4px; display: block;">Select a list containing seed email addresses you own (e.g. Gmail, Yahoo, Outlook accounts) to receive test emails.</span>
            </div>

            <div class="form-group">
                <label class="form-label" for="warmup_start_date">Warm-up Start Date</label>
                <input class="form-control" type="date" id="warmup_start_date" name="warmup_start_date" value="<?= e($startDate) ?>" required>
            </div>

            <button type="submit" class="btn btn-primary" style="font-weight: 600;">Save Settings →</button>
        </form>
    </div>

    <!-- Stats & Logic Card -->
    <div class="card" style="display: flex; flex-direction: column; gap: 20px;">
        <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 16px; margin-bottom: 0;">
            <span class="card-title">Warm-Up Progress & Cron URL</span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; background-color: #fafbfc; border: 1px solid var(--theme-border); padding: 16px; border-radius: 8px;">
            <div>
                <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Engine Status</span>
                <?php if ($warmupActive): ?>
                    <span class="badge badge-active" style="font-weight: 700;">Running</span>
                <?php else: ?>
                    <span class="badge" style="font-weight: 700; background-color: #e2e8f0; color: #4a5568; border: 1px solid #cbd5e0;">Inactive</span>
                <?php endif; ?>
            </div>
            <div>
                <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Current Day</span>
                <span style="font-size: 14px; font-weight: 700; color: var(--theme-dark);"><?= $day > 0 ? "Day {$day} of 30" : "—" ?></span>
            </div>
            <div>
                <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Daily Send Quota</span>
                <span style="font-size: 14px; font-weight: 700; color: var(--theme-dark);"><?= $quota > 0 ? "{$quota} emails/day" : "—" ?></span>
            </div>
        </div>

        <div>
            <h4 style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--theme-dark); margin: 0 0 6px;">Daily Automation Cron Trigger</h4>
            <p style="font-size: 12px; color: var(--theme-dark-slate); line-height: 1.5; margin: 0 0 10px;">
                Configure a server cron job to execute this endpoint once per day to automatically send the daily quota batch:
            </p>
            <input class="form-control" type="text" readonly value="curl -s <?= e($runUrl) ?>" onclick="this.select()" style="font-family: monospace; font-size: 12px; margin-bottom: 0; background-color: #fafbfc;">
        </div>

        <div style="background-color: rgba(245, 158, 11, 0.04); border: 1px dashed rgba(245, 158, 11, 0.2); padding: 12px; border-radius: 6px; font-size: 12px; color: var(--theme-dark-slate); line-height: 1.5;">
            ⚠️ **Important:** Seed accounts should ideally open emails, click links, and mark them as "not spam" when received. This interactive behavior is what informs spam filters that your domain is sending high-quality content.
        </div>
    </div>
</div>
