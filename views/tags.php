<?php declare(strict_types=1); ?>

<div class="header-actions">
    <div class="page-title">
        <h1>CRM Tags Management</h1>
        <p>Create and organize tags to segment your contacts and trigger automations.</p>
    </div>
</div>

<div class="grid grid-2" style="gap: 24px; align-items: start;">
    <!-- Create Tag Form -->
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="margin-bottom: 20px;">
            <span class="card-title">Create New Tag</span>
        </div>
        <form method="post" action="<?= e($appUrl) ?>/tags/create">
            <?= Auth::csrfField() ?>
            <div class="form-group">
                <label class="form-label" for="name">Tag Name</label>
                <input class="form-control" type="text" id="name" name="name" required placeholder="e.g. VIP Customer">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>Create Tag</button>
        </form>
    </div>

    <!-- Tags List -->
    <div class="card">
        <div class="card-header" style="padding: 20px 24px; border-bottom: 1px solid var(--theme-border);">
            <span class="card-title">Existing Tags</span>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tag Name</th>
                        <th>Subscribers</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tags)): ?>
                        <tr><td colspan="3" style="text-align: center; color: var(--theme-dark-slate); padding: 24px;">No tags found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tags as $t): ?>
                            <tr>
                                <td style="font-weight: 500;">
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; background: var(--theme-blurple); color: white;">
                                        <?= e($t['name']) ?>
                                    </span>
                                </td>
                                <td><?= number_format((float)$t['subscriber_count']) ?></td>
                                <td style="text-align: right;">
                                    <div style="display: flex; justify-content: flex-end; gap: 8px;">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="editTag(<?= e($t['id']) ?>, <?= e(json_encode($t['name'])) ?>)">Edit</button>
                                        <form method="post" action="<?= e($appUrl) ?>/tags?action=delete" style="margin: 0;" onsubmit="return confirm('Delete this tag?');">
                                            <?= Auth::csrfField() ?>
                                            <input type="hidden" name="id" value="<?= e($t['id']) ?>">
                                            <button type="submit" class="btn btn-danger btn-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>Delete</button>
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
</div>

<!-- Edit Modal Overlay -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 400px; padding: 24px; position: relative;">
        <button type="button" onclick="closeEditModal()" style="position: absolute; top: 12px; right: 12px; background: none; border: none; cursor: pointer; font-size: 16px;">&times;</button>
        <h3 style="margin-top: 0; font-size: 16px; margin-bottom: 16px;">Edit Tag</h3>
        <form method="post" action="<?= e($appUrl) ?>/tags/edit">
            <?= Auth::csrfField() ?>
            <input type="hidden" id="edit_id" name="id">
            <div class="form-group">
                <label class="form-label" for="edit_name">Tag Name</label>
                <input class="form-control" type="text" id="edit_name" name="name" required>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editTag(id, name) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>
