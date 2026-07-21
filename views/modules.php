<?php
declare(strict_types=1);
?>

<div class="header-actions" style="margin-bottom: 20px;">
    <div class="page-title">
        <h1>Commercial Module Marketplace & Addons</h1>
        <p>Manage, license, and enable commercial power suites or upload custom extension ZIP bundles.</p>
    </div>
</div>

<!-- Upload & Licensing Card -->
<div class="card" style="padding: 24px; margin-bottom: 24px; border-left: 4px solid var(--theme-blurple);">
    <div class="card-header" style="margin-bottom: 16px; border-bottom: none; padding-bottom: 0; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <span class="card-title">Install Expansion Suite / License Key</span>
            <p style="font-size: 12px; color: var(--theme-dark-slate); margin-top: 4px; margin-bottom: 0;">Upload licensed <code>.zip</code> module packages to extend your platform's capabilities.</p>
        </div>
        <span class="badge" style="background: rgba(99,91,255,0.15); color: var(--theme-blurple); font-weight: 700; padding: 6px 12px;">Marketplace License Engine v2.5</span>
    </div>

    <form method="post" action="<?= e(getSetting('app_url')) ?>/extensions/upload" enctype="multipart/form-data" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
        <?= Auth::csrfField() ?>
        <div style="flex: 1; min-width: 260px;">
            <label class="form-label" for="module_zip">Select Module ZIP Archive</label>
            <input class="form-control" type="file" id="module_zip" name="module_zip" accept=".zip" required style="padding: 6px 12px;">
        </div>
        <button type="submit" class="btn btn-primary" style="height: 38px; font-weight: 600; padding: 0 24px;">
            ⚡ Upload & Install Module Suite
        </button>
    </form>
</div>

<!-- Commercial Power Suites Cards -->
<div style="margin-bottom: 16px;">
    <h2 style="font-size: 18px; font-weight: 700; color: var(--theme-dark);">Commercial Power Suites (Available for Sale)</h2>
</div>

<div class="grid grid-2" style="gap: 20px; align-items: stretch; margin-bottom: 24px;">
    <?php foreach ($modules as $id => $mod): ?>
        <div class="card" style="padding: 24px; display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid <?= $mod['enabled'] ? 'var(--theme-blurple)' : 'var(--theme-border)' ?>;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                    <div>
                        <h3 style="font-size: 17px; font-weight: 700; color: var(--theme-dark); margin: 0;"><?= e($mod['name']) ?></h3>
                        <span style="font-size: 11px; font-weight: 600; color: var(--theme-dark-slate); display: inline-block; margin-top: 2px;">
                            v<?= e($mod['version'] ?? '1.0.0') ?> • <?= e($mod['tier'] ?? 'Extension') ?>
                        </span>
                    </div>
                    <span style="font-size: 14px; font-weight: 800; color: var(--theme-blurple); background: rgba(99,91,255,0.1); padding: 4px 10px; border-radius: 6px;">
                        <?= e($mod['price'] ?? 'Included') ?>
                    </span>
                </div>

                <p style="font-size: 13px; color: var(--theme-dark-slate); line-height: 1.5; margin-bottom: 16px;">
                    <?= e($mod['description'] ?? 'No description.') ?>
                </p>

                <?php if (!empty($mod['features'])): ?>
                    <div style="background: var(--theme-bg); padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid var(--theme-border);">
                        <span style="font-size: 11px; font-weight: 700; color: var(--theme-dark); text-transform: uppercase; display: block; margin-bottom: 6px;">Key Suite Capabilities:</span>
                        <ul style="margin: 0; padding-left: 18px; font-size: 12px; color: var(--theme-dark-slate); line-height: 1.6;">
                            <?php foreach ($mod['features'] as $feat): ?>
                                <li><?= e($feat) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid var(--theme-border);">
                <div>
                    <span class="badge" style="background-color: <?= $mod['enabled'] ? 'rgba(16,185,129,0.15)' : 'var(--theme-bg)' ?>; color: <?= $mod['enabled'] ? '#059669' : 'var(--theme-dark-slate)' ?>; font-weight: 700; padding: 6px 12px;">
                        <?= $mod['enabled'] ? '✓ Suite Active & Licensed' : '○ Suite Disabled' ?>
                    </span>
                </div>
                <div style="display: flex; gap: 8px;">
                    <?php if ($mod['enabled'] && !empty($mod['menu_path'])): ?>
                        <a href="<?= e(getSetting('app_url') . $mod['menu_path']) ?>" class="btn btn-primary" style="padding: 6px 16px; font-size: 13px; font-weight: 600; text-decoration: none;">
                            Launch Suite →
                        </a>
                    <?php endif; ?>

                    <form method="post" action="<?= e(getSetting('app_url')) ?>/extensions/toggle?id=<?= urlencode($id) ?>" style="margin: 0;">
                        <?= Auth::csrfField() ?>
                        <button type="submit" class="btn <?= $mod['enabled'] ? 'btn-secondary' : 'btn-primary' ?>" style="padding: 6px 16px; font-size: 13px; font-weight: 600;">
                            <?= $mod['enabled'] ? 'Disable Suite' : 'Enable & Activate' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
