<?php
declare(strict_types=1);

$baseUrl = rtrim(getSetting('app_url'), '/');
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Smart Link Rotator</h1>
        <p>Cloak your affiliate or tracking links behind your own domain, and balance clicks across multiple destination URLs.</p>
    </div>
</div>

<div class="grid grid-2">
    <!-- Configuration Form -->
    <div class="card" style="display: flex; flex-direction: column; gap: 20px;">
        <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 16px; margin-bottom: 0;">
            <span class="card-title">Create Link Rotator</span>
        </div>
        
        <form method="post" action="?action=create">
            <div class="form-group">
                <label class="form-label" for="name">Friendly Name</label>
                <input class="form-control" type="text" id="name" name="name" required placeholder="e.g. Summer Promo Rotation">
            </div>

            <div class="form-group">
                <label class="form-label" for="slug">URL Slug</label>
                <input class="form-control" type="text" id="slug" name="slug" required placeholder="e.g. promo1" pattern="[a-zA-Z0-9\-_]+">
                <span style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 4px; display: block;">Only alphanumeric characters, hyphens, and underscores allowed.</span>
            </div>

            <div class="form-group">
                <label class="form-label" for="destinations">Redirection Destinations (One URL per line)</label>
                <textarea class="form-control" id="destinations" name="destinations" required style="min-height: 120px; font-family: monospace;" placeholder="https://dest-one.com&#10;https://dest-two.com"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="font-weight: 600;">Register Rotator →</button>
        </form>
    </div>

    <!-- Rotators List -->
    <div class="card" style="display: flex; flex-direction: column; gap: 16px;">
        <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 16px; margin-bottom: 0;">
            <span class="card-title">Registered Cloaked Links</span>
        </div>

        <div class="table-wrapper" style="border: 1px solid var(--theme-border);">
            <table>
                <thead>
                    <tr>
                        <th>Rotator details</th>
                        <th>Cloaked Share Link</th>
                        <th style="width: 100px; text-align: center;">Total Clicks</th>
                        <th style="width: 80px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rotators)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--theme-dark-slate); padding: 40px;">No link rotators defined yet. Create one on the left to start rotating traffic!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rotators as $r):
                            $shareLink = $baseUrl . '/go?s=' . urlencode($r['slug']);
                            $dests = json_decode($r['destinations'], true) ?: [];
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: var(--theme-dark);"><?= e($r['name']) ?></div>
                                    <div style="font-size: 11px; color: var(--theme-dark-slate); line-height: 1.4; margin-top: 4px;">
                                        <strong>Destinations (<?= count($dests) ?>):</strong><br>
                                        <?php foreach ($dests as $d): ?>
                                            • <span style="font-family: monospace; font-size:10px;"><?= e($d) ?></span><br>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td>
                                    <input class="form-control" type="text" readonly value="<?= e($shareLink) ?>" onclick="this.select()" style="font-family: monospace; font-size: 11px; margin-bottom: 0; background-color: #fafbfc; padding: 4px 8px; height: auto;">
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-active" style="font-size: 12px; font-weight: 700; background-color: rgba(99, 91, 255, 0.1); color: var(--theme-blurple); padding: 4px 12px; border-radius: 12px; border: 1px solid rgba(99, 91, 255, 0.15);">
                                        <?= (int)$r['clicks'] ?> clicks
                                    </span>
                                </td>
                                <td>
                                    <form method="post" action="?action=delete&id=<?= $r['id'] ?>" onsubmit="return confirm('Remove this rotator link?');" style="margin: 0;">
                                        <button type="submit" class="btn btn-danger" style="padding: 2px 6px; font-size: 10px;">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
