<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Subscription Forms</h1>
        <p>Design custom lead capture forms with download delivery, double opt-in, and auto-verify deliverability checks.</p>
    </div>
    <div>
        <a href="<?= e(getSetting('app_url')) ?>/forms/create" class="btn btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Create Form
        </a>
    </div>
</div>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Form Details</th>
                <th>Target List</th>
                <th>Double Opt-in</th>
                <th>Lead Magnet</th>
                <th>Created At</th>
                <th style="width: 280px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($forms)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--stripe-dark-slate); padding: 40px;">No forms created yet. Create a form to embed or share with customers.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($forms as $f): 
                    $directUrl = rtrim(getSetting('app_url'), '/') . '/subscribe?form=' . $f['id'];
                    $iframeCode = '<iframe src="' . e($directUrl) . '" style="width:100%; max-width:500px; height:450px; border:none; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.05); overflow:hidden;"></iframe>';
                ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600; color: var(--stripe-dark); margin-bottom: 2px;"><?= e($f['name']) ?></div>
                            <div style="font-size: 11px; color: var(--stripe-dark-slate); font-family: monospace;"><?= e($f['headline']) ?></div>
                        </td>
                        <td style="color: var(--stripe-dark-slate); font-weight: 500;">
                            <?= e($f['list_name'] ?? '— None —') ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $f['double_optin'] ? 'queued' : 'active' ?>" style="font-size: 10px; font-weight: 600;">
                                <?= $f['double_optin'] ? 'Enabled' : 'Disabled' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($f['download_url']): ?>
                                <span style="font-size: 11px; font-weight: 600; color: var(--stripe-blurple); display: inline-flex; align-items: center; gap: 4px;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                    Lead File
                                </span>
                            <?php else: ?>
                                <span style="color: var(--stripe-dark-slate); font-size: 12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--stripe-dark-slate);"><?= date('M j, Y', strtotime($f['created_at'])) ?></td>
                        <td>
                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                                    <button type="button" class="btn btn-secondary" onclick="toggleEmbed(<?= $f['id'] ?>)" style="padding: 4px 8px; font-size: 11px;">Embed Info</button>
                                    <a href="<?= e(getSetting('app_url')) ?>/forms/edit?id=<?= $f['id'] ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">Edit</a>
                                    <form method="post" action="?action=delete&id=<?= $f['id'] ?>" onsubmit="return confirm('Are you sure you want to delete this form? Embeds using it will show an error.');" style="margin:0;">
                                        <button type="submit" class="btn btn-danger" style="padding: 4px 8px; font-size: 11px;">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Embed toggle row -->
                    <tr id="embed-row-<?= $f['id'] ?>" style="display: none; background-color: #fafbfc;">
                        <td colspan="6" style="padding: 16px 20px;">
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <div>
                                    <span style="font-size: 11px; text-transform: uppercase; color: var(--stripe-dark-slate); font-weight: 600; display: block; margin-bottom: 6px;">Direct Link Sharing URL</span>
                                    <input class="form-control" type="text" readonly value="<?= e($directUrl) ?>" onclick="this.select()" style="font-family: monospace; font-size: 12px; margin-bottom: 0;">
                                </div>
                                <div>
                                    <span style="font-size: 11px; text-transform: uppercase; color: var(--stripe-dark-slate); font-weight: 600; display: block; margin-bottom: 6px;">Iframe Embed Code</span>
                                    <textarea class="form-control" readonly onclick="this.select()" style="font-family: monospace; font-size: 12px; min-height: 60px; margin-bottom: 0;"><?= e($iframeCode) ?></textarea>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    function toggleEmbed(id) {
        const row = document.getElementById("embed-row-" + id);
        if (row) {
            row.style.display = row.style.display === "none" ? "table-row" : "none";
        }
    }
</script>
