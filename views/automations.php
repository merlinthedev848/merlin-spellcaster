<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Automations</h1>
        <p>Build behavioral workflow chains triggered automatically by subscriber events.</p>
    </div>
    <div>
        <a href="<?= e(getSetting('app_url')) ?>/automations/create" class="btn btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            New Automation
        </a>
    </div>
</div>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Automation Name</th>
                <th>Trigger Event</th>
                <th>Total Steps</th>
                <th>Status</th>
                <th>Created At</th>
                <th style="width: 120px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($automations)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--theme-dark-slate); padding: 40px;">No automations configured. Create one to begin automating flows.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($automations as $a): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--theme-dark);"><?= e($a['name']) ?></td>
                        <td>
                            <?php
                            $trig = $a['trigger_event'];
                            $label = 'Subscriber Joined List';
                            if ($trig !== 'subscribe') {
                                $parts = explode(':', $trig);
                                $type = $parts[0];
                                $val = isset($parts[1]) ? (int)$parts[1] : 0;
                                if ($type === 'tag_added') {
                                    $tagIds = array_map('intval', explode(',', $parts[1] ?? ''));
                                    $tagNames = [];
                                    foreach ($tagIds as $tId) {
                                        $tagNames[] = $tags[$tId] ?? "Tag #{$tId}";
                                    }
                                    $label = "Tag Added: \"" . implode('", "', $tagNames) . "\"";
                                } elseif ($type === 'form_submit') {
                                    $formName = $forms[$val] ?? "Form #{$val}";
                                    $label = "Form Submitted: \"{$formName}\"";
                                } elseif ($type === 'email_open') {
                                    $campName = $campaigns[$val] ?? "Campaign #{$val}";
                                    $label = "Email Opened: \"{$campName}\"";
                                } elseif ($type === 'link_click') {
                                    $campName = $campaigns[$val] ?? "Campaign #{$val}";
                                    $label = "Link Clicked: \"{$campName}\"";
                                } elseif ($type === 'points_threshold') {
                                    $label = "Lead Score >= {$val}";
                                }
                            }
                            ?>
                            <span class="badge" style="background-color: var(--theme-blurple-light); color: var(--theme-blurple); font-weight: 500;">
                                On <?= e($label) ?>
                            </span>
                            <?php if (!empty($a['exclude_tag_id'])): 
                                $exTagName = $tags[(int)$a['exclude_tag_id']] ?? "Tag #{$a['exclude_tag_id']}";
                            ?>
                                <span class="badge" style="background-color: #fff1f2; color: #e11d48; border: 1px solid rgba(225,29,72,0.15); font-weight: 500; margin-left: 6px;">
                                    Excludes: <?= e($exTagName) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 500;"><?= $a['step_count'] ?> step(s)</td>
                        <td>
                            <span class="badge badge-<?= e($a['status']) === 'active' ? 'active' : 'paused' ?>">
                                <?= e($a['status']) ?>
                            </span>
                        </td>
                        <td style="color: var(--theme-dark-slate);"><?= date('M j, Y', strtotime($a['created_at'])) ?></td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <form method="post" action="?action=toggle&id=<?= e($a['id']) ?>">
                                    <?= Auth::csrfField() ?>
                                    <button type="submit" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">
                                        <?= $a['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                                <a href="<?= e(getSetting('app_url')) ?>/automations/edit?id=<?= e($a['id']) ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">Edit</a>
                                <form method="post" action="?action=delete&id=<?= e($a['id']) ?>" onsubmit="return confirm('Are you sure you want to delete this automation?');">
                                    <?= Auth::csrfField() ?>
                                    <button type="submit" class="btn btn-danger" style="padding: 4px 8px; font-size: 11px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
