<?php
declare(strict_types=1);

$runUrl = rtrim(getSetting('app_url'), '/') . '/rss/run?secret=' . urlencode(getSetting('cron_secret'));
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Automated RSS-to-Email</h1>
        <p>Connect your blog feed or website RSS to automatically queue newsletter campaigns when new articles are published.</p>
    </div>
</div>

<div class="grid grid-2">
    <!-- Configuration Form -->
    <div class="card" style="display: flex; flex-direction: column; gap: 20px;">
        <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 16px; margin-bottom: 0;">
            <span class="card-title">Add RSS Feed Monitor</span>
        </div>
        
        <form method="post" action="?action=create">
            <?= Auth::csrfField() ?>
            <div class="form-group">
                <label class="form-label" for="feed_url">RSS Feed URL</label>
                <input class="form-control" type="url" id="feed_url" name="feed_url" required placeholder="https://example.com/feed/">
            </div>

            <div class="form-group">
                <label class="form-label" for="list_id">Target Recipient Segment List</label>
                <select class="form-control" id="list_id" name="list_id" required>
                    <option value="">-- Choose Segment --</option>
                    <?php foreach ($lists as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="frequency">Monitoring Frequency</label>
                <select class="form-control" id="frequency" name="frequency" required>
                    <option value="hourly">Hourly</option>
                    <option value="daily">Daily</option>
                    <option value="weekly" selected>Weekly</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="font-weight: 600;">Monitor Feed →</button>
        </form>
    </div>

    <!-- Feeds List -->
    <div class="card" style="display: flex; flex-direction: column; gap: 16px;">
        <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 16px; margin-bottom: 0;">
            <span class="card-title">Active RSS Feed Subscriptions</span>
        </div>

        <div class="table-wrapper" style="border: 1px solid var(--theme-border);">
            <table>
                <thead>
                    <tr>
                        <th>Feed URL</th>
                        <th>Target Segment</th>
                        <th>Frequency</th>
                        <th>Last Checked</th>
                        <th style="width: 80px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($feeds)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--theme-dark-slate); padding: 40px;">No feeds registered. Add a feed on the left to start automated campaigns!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($feeds as $f): ?>
                            <tr>
                                <td>
                                    <div style="font-family: monospace; font-size: 11px; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($f['feed_url']) ?>">
                                        <?= e($f['feed_url']) ?>
                                    </div>
                                </td>
                                <td><span style="font-weight: 600; color: var(--theme-dark);"><?= e($f['list_name']) ?></span></td>
                                <td><span class="badge" style="background-color: var(--theme-blurple-light); color: var(--theme-blurple); text-transform: uppercase; font-size: 9px; font-weight: 700;"><?= e($f['frequency']) ?></span></td>
                                <td style="color: var(--theme-dark-slate); font-size: 11px;">
                                    <?= $f['last_checked_at'] ? date('M j, H:i', strtotime($f['last_checked_at'])) : 'Never' ?>
                                </td>
                                <td>
                                    <form method="post" action="?action=delete&id=<?= e($f['id']) ?>" onsubmit="return confirm('Remove this RSS feed?');" style="margin: 0;">
                                        <?= Auth::csrfField() ?>
                                        <button type="submit" class="btn btn-danger" style="padding: 2px 6px; font-size: 10px;">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div>
            <h4 style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--theme-dark); margin: 0 0 6px;">Autopilot Cron Trigger URL</h4>
            <p style="font-size: 12px; color: var(--theme-dark-slate); line-height: 1.5; margin: 0 0 10px;">
                Trigger the RSS checking scanner automatically using this endpoint inside your cron jobs scheduler:
            </p>
            <input class="form-control" type="text" readonly value="curl -s <?= e($runUrl) ?>" onclick="this.select()" style="font-family: monospace; font-size: 12px; margin-bottom: 0; background-color: #fafbfc;">
        </div>
    </div>
</div>
