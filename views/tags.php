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
            <div class="form-group">
                <label class="form-label" for="name">Tag Name</label>
                <input class="form-control" type="text" id="name" name="name" required placeholder="e.g. VIP Customer">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Create Tag</button>
        </form>
    </div>

    <!-- Tags List -->
    <div class="card">
        <div class="card-header" style="padding: 20px 24px; border-bottom: 1px solid var(--stripe-border);">
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
                        <tr><td colspan="3" style="text-align: center; color: var(--stripe-dark-slate); padding: 24px;">No tags found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tags as $t): ?>
                            <tr>
                                <td style="font-weight: 500;">
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; background: var(--stripe-blurple); color: white;">
                                        <?= e($t['name']) ?>
                                    </span>
                                </td>
                                <td><?= number_format((float)$t['subscriber_count']) ?></td>
                                <td style="text-align: right;">
                                    <div style="display: flex; justify-content: flex-end; gap: 8px;">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="editTag(<?= $t['id'] ?>, '<?= e(addslashes($t['name'])) ?>')">Edit</button>
                                        <form method="post" action="<?= e($appUrl) ?>/tags?action=delete" style="margin: 0;" onsubmit="return confirm('Delete this tag?');">
                                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
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
            <input type="hidden" id="edit_id" name="id">
            <div class="form-group">
                <label class="form-label" for="edit_name">Tag Name</label>
                <input class="form-control" type="text" id="edit_name" name="name" required>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
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
