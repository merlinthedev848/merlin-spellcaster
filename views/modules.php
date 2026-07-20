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
        <?= Auth::csrfField() ?>
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
        <table style="table-layout: fixed; width: 100%;">
            <thead>
                <tr>
                    <th style="width: 250px; min-width: 250px;">Module Details</th>
                    <th>Description</th>
                    <th style="width: 140px; min-width: 140px; text-align: center;">Status</th>
                    <th style="width: 290px; min-width: 290px; text-align: right;">Action</th>
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
                            <td style="width: 250px; min-width: 250px; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <div style="font-weight: 600; color: var(--theme-dark); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($mod['name']) ?>"><?= e($mod['name']) ?></div>
                                <div style="display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                                    <span style="font-size: 10px; background-color: var(--theme-bg); color: var(--theme-dark-slate); padding: 2px 6px; border-radius: 4px; font-weight: 600;">v<?= e($mod['version'] ?? '1.0.0') ?></span>
                                    <span style="font-size: 11px; color: var(--theme-dark-slate);"><code>/<?= e($id) ?>/</code></span>
                                </div>
                            </td>
                            <td style="color: var(--theme-dark-slate); font-size: 13px; line-height: 1.45; vertical-align: middle;">
                                <?= e($mod['description'] ?? 'No description provided.') ?>
                            </td>
                            <td style="width: 140px; min-width: 140px; text-align: center; vertical-align: middle; white-space: nowrap;">
                                <span class="badge" style="display: inline-block; width: 85px; text-align: center; background-color: <?= $mod['enabled'] ? 'var(--theme-blurple-light)' : 'var(--theme-bg)' ?>; color: <?= $mod['enabled'] ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 700; padding: 4px 10px; border-radius: 4px;">
                                    ● <?= $mod['enabled'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td style="width: 290px; min-width: 290px; text-align: right; vertical-align: middle; white-space: nowrap;">
                                <div style="display: inline-flex; align-items: center; justify-content: flex-end; gap: 6px; width: 100%;">
                                    <?php if ($mod['enabled'] && !empty($mod['menu_path'])): ?>
                                        <a href="<?= e(getSetting('app_url') . $mod['menu_path']) ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; width: 80px; text-decoration: none; font-weight: 600; text-align: center;">
                                            Open
                                        </a>
                                    <?php else: ?>
                                        <div style="width: 80px;"></div> <!-- placeholder to keep widths static -->
                                    <?php endif; ?>
                                    
                                    <form method="post" action="<?= e(getSetting('app_url')) ?>/extensions/toggle?id=<?= urlencode($id) ?>" style="margin: 0; display: inline-block;">
                                        <?= Auth::csrfField() ?>
                                        <button type="submit" class="btn <?= $mod['enabled'] ? 'btn-secondary' : 'btn-primary' ?>" style="padding: 6px 12px; font-size: 12px; width: 90px; justify-content: center; text-align: center;">
                                            <?= $mod['enabled'] ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>
                                    
                                    <form method="post" action="<?= e(getSetting('app_url')) ?>/extensions/uninstall?id=<?= urlencode($id) ?>" onsubmit="return confirm('Are you sure you want to permanently uninstall and delete this module? This cannot be undone.');" style="margin: 0; display: inline-block;">
                                        <?= Auth::csrfField() ?>
                                        <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px; width: 90px; justify-content: center; text-align: center;">
                                            Uninstall
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
