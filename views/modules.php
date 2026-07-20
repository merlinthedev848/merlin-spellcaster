<?php
declare(strict_types=1);
?>

<div class="header-actions" style="margin-bottom: 20px;">
    <div class="page-title">
        <h1>Modules Directory</h1>
        <p>Enable premium features, install custom ZIP expansions, and manage your modular marketing tools.</p>
    </div>
</div>

<!-- Upload facility at the top -->
<div class="card" style="padding: 24px; margin-bottom: 24px;">
    <div class="card-header" style="margin-bottom: 16px; border-bottom: none; padding-bottom: 0;">
        <span class="card-title">Install Extension Bundle</span>
    </div>
    <form method="post" action="<?= e(getSetting('app_url')) ?>/extensions/upload" enctype="multipart/form-data" style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 280px;">
            <div style="background-color: var(--theme-blurple-light); color: var(--theme-dark-slate); border-radius: 6px; padding: 12px; font-size: 12px; margin-bottom: 12px; border: 1px solid rgba(99,91,255,0.1); line-height: 1.4;">
                Upload a <strong>.zip</strong> package containing a valid <code>module.json</code> descriptor at the root directory level.
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="module_zip">Select Module ZIP Archive</label>
                <input class="form-control" type="file" id="module_zip" name="module_zip" accept=".zip" required style="padding: 6px 12px;">
            </div>
        </div>
        <button type="submit" class="btn btn-primary" style="height: 38px; font-weight: 600; padding: 0 24px; justify-content: center; display: inline-flex; align-items: center; gap: 8px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
            Upload and Install
        </button>
    </form>
</div>

<!-- Modules Table Card -->
<div class="card" style="padding: 24px;">
    <div class="card-header" style="margin-bottom: 16px; border-bottom: none; padding-bottom: 0;">
        <span class="card-title">Discovered Modules</span>
    </div>
    
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th style="width: 220px;">Module Details</th>
                    <th>Description</th>
                    <th style="width: 140px; text-align: center;">Status</th>
                    <th style="width: 150px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($modules)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--theme-dark-slate); padding: 40px;">
                            No modules discovered in the <code>modules/</code> directory.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($modules as $id => $mod): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; color: var(--theme-dark);"><?= e($mod['name']) ?></div>
                                <div style="display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                                    <span style="font-size: 10px; background-color: var(--theme-bg); color: var(--theme-dark-slate); padding: 2px 6px; border-radius: 4px; font-weight: 600;">v<?= e($mod['version'] ?? '1.0.0') ?></span>
                                    <span style="font-size: 11px; color: var(--theme-dark-slate);"><code>/<?= e($id) ?>/</code></span>
                                </div>
                            </td>
                            <td style="color: var(--theme-dark-slate); font-size: 13px; line-height: 1.45;">
                                <?= e($mod['description'] ?? 'No description provided.') ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge" style="background-color: <?= $mod['enabled'] ? 'var(--theme-blurple-light)' : 'var(--theme-bg)' ?>; color: <?= $mod['enabled'] ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 700; padding: 4px 10px;">
                                    ● <?= $mod['enabled'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if ($mod['enabled'] && !empty($mod['menu_path'])): ?>
                                    <a href="<?= e(getSetting('app_url') . $mod['menu_path']) ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; min-width: 80px; text-decoration: none; font-weight: 600;">
                                        Open
                                    </a>
                                <?php endif; ?>
                                <form method="post" action="<?= e(getSetting('app_url')) ?>/extensions/toggle?id=<?= urlencode($id) ?>" style="margin: 0; display: inline-block; margin-left: 4px;">
                                    <button type="submit" class="btn <?= $mod['enabled'] ? 'btn-secondary' : 'btn-primary' ?>" style="padding: 6px 12px; font-size: 12px; min-width: 90px; justify-content: center;">
                                        <?= $mod['enabled'] ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>
                                <form method="post" action="<?= e(getSetting('app_url')) ?>/extensions/uninstall?id=<?= urlencode($id) ?>" onsubmit="return confirm('Are you sure you want to permanently uninstall and delete this module? This cannot be undone.');" style="margin: 0; display: inline-block; margin-left: 4px;">
                                    <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px; justify-content: center;">
                                        Uninstall
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
