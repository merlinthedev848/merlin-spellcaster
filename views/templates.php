<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Newsletter Templates</h1>
        <p>Design reusable newsletter templates and HTML frameworks with live side-by-side workspace previewing.</p>
    </div>
    <div>
        <a href="<?= e(getSetting('app_url')) ?>/templates/create" class="btn btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Create Template
        </a>
    </div>
</div>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Template Name</th>
                <th>Default Subject Line</th>
                <th>Created At</th>
                <th style="width: 150px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($templates)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: var(--stripe-dark-slate); padding: 40px;">No templates configured. Create one to begin designing campaigns.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($templates as $t): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--stripe-dark);"><?= e($t['name']) ?></td>
                        <td style="color: var(--stripe-dark-slate);"><?= e($t['subject']) ?: '—' ?></td>
                        <td style="color: var(--stripe-dark-slate);"><?= date('M j, Y H:i:s', strtotime($t['created_at'])) ?></td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <a href="<?= e(getSetting('app_url')) ?>/templates/edit?id=<?= $t['id'] ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">Edit</a>
                                <form method="post" action="?action=delete&id=<?= $t['id'] ?>" onsubmit="return confirm('Are you sure you want to delete this template? Campaigns already sent using this layout will not be affected.');">
                                    <button type="submit" class="btn btn-danger" style="padding: 4px 8px; font-size: 11px;">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
