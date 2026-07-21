<?php
declare(strict_types=1);
?>

<div class="header-actions" style="margin-bottom: 20px;">
    <div class="page-title">
        <h1>Global Omnibox Search</h1>
        <p><?= $query !== '' ? "Found <strong>{$totalResults}</strong> result(s) for '<strong>" . e($query) . "</strong>'" : "Search across contacts, campaigns, automations, templates, and subscription forms." ?></p>
    </div>
</div>

<div class="card" style="padding: 20px; margin-bottom: 24px;">
    <form method="get" action="<?= e(getSetting('app_url')) ?>/search" style="display: flex; gap: 12px; margin-bottom: 0;">
        <input class="form-control" type="text" name="q" value="<?= e($query) ?>" placeholder="Type anything (e.g. contact email, campaign title, tag, subject)..." style="font-size: 15px; padding: 12px 16px; margin-bottom: 0;" autofocus>
        <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-weight: 600;">🔍 Search System</button>
    </form>
</div>

<?php if ($query !== ''): ?>
    <div class="grid grid-2" style="align-items: start; gap: 24px;">
        
        <!-- Contacts Results -->
        <div class="card" style="padding: 20px;">
            <div class="card-header" style="margin-bottom: 14px; border-bottom: 1px solid var(--theme-border); padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                <span class="card-title">👤 Contacts (CRM) (<?= count($contacts) ?>)</span>
            </div>
            <?php if (empty($contacts)): ?>
                <p style="color: var(--theme-dark-slate); font-size: 13px; margin: 0;">No contacts matching query.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <?php foreach ($contacts as $c): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: var(--theme-bg); border-radius: 6px; border: 1px solid var(--theme-border);">
                            <div>
                                <a href="<?= e(getSetting('app_url')) ?>/contacts/view?id=<?= $c['id'] ?>" style="font-weight: 600; color: var(--theme-blurple); text-decoration: none; font-size: 14px;">
                                    <?= e($c['email']) ?>
                                </a>
                                <span style="font-size: 12px; color: var(--theme-dark-slate); display: block;">
                                    <?= e(trim($c['first_name'] . ' ' . $c['last_name'])) ?: 'No name' ?> • Score: <?= (int)$c['lead_score'] ?> pts
                                </span>
                            </div>
                            <span class="badge" style="background: rgba(99,91,255,0.1); color: var(--theme-blurple); font-weight: 600; text-transform: uppercase; font-size: 10px;"><?= e($c['status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Campaigns Results -->
        <div class="card" style="padding: 20px;">
            <div class="card-header" style="margin-bottom: 14px; border-bottom: 1px solid var(--theme-border); padding-bottom: 10px;">
                <span class="card-title">📧 Campaigns (<?= count($campaigns) ?>)</span>
            </div>
            <?php if (empty($campaigns)): ?>
                <p style="color: var(--theme-dark-slate); font-size: 13px; margin: 0;">No campaigns matching query.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <?php foreach ($campaigns as $camp): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: var(--theme-bg); border-radius: 6px; border: 1px solid var(--theme-border);">
                            <div>
                                <a href="<?= e(getSetting('app_url')) ?>/campaigns" style="font-weight: 600; color: var(--theme-dark); text-decoration: none; font-size: 14px;">
                                    <?= e($camp['name']) ?>
                                </a>
                                <span style="font-size: 12px; color: var(--theme-dark-slate); display: block;"><?= e($camp['subject']) ?></span>
                            </div>
                            <span class="badge" style="background: rgba(52,211,153,0.15); color: #059669; font-weight: 700; text-transform: uppercase; font-size: 10px;"><?= e($camp['status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Automations Results -->
        <div class="card" style="padding: 20px;">
            <div class="card-header" style="margin-bottom: 14px; border-bottom: 1px solid var(--theme-border); padding-bottom: 10px;">
                <span class="card-title">🔀 Automations (<?= count($automations) ?>)</span>
            </div>
            <?php if (empty($automations)): ?>
                <p style="color: var(--theme-dark-slate); font-size: 13px; margin: 0;">No automations matching query.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <?php foreach ($automations as $auto): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: var(--theme-bg); border-radius: 6px; border: 1px solid var(--theme-border);">
                            <div>
                                <a href="<?= e(getSetting('app_url')) ?>/automations/edit?id=<?= $auto['id'] ?>" style="font-weight: 600; color: var(--theme-blurple); text-decoration: none; font-size: 14px;">
                                    <?= e($auto['title']) ?>
                                </a>
                                <span style="font-size: 12px; color: var(--theme-dark-slate); display: block;">Trigger: <?= e($auto['trigger_event']) ?></span>
                            </div>
                            <span class="badge" style="background: rgba(99,91,255,0.1); color: var(--theme-blurple); font-weight: 600; font-size: 10px;"><?= e($auto['status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Templates & Forms Results -->
        <div class="card" style="padding: 20px;">
            <div class="card-header" style="margin-bottom: 14px; border-bottom: 1px solid var(--theme-border); padding-bottom: 10px;">
                <span class="card-title">📝 Templates & Forms (<?= count($templates) + count($forms) ?>)</span>
            </div>
            <?php if (empty($templates) && empty($forms)): ?>
                <p style="color: var(--theme-dark-slate); font-size: 13px; margin: 0;">No templates or forms matching query.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <?php foreach ($templates as $tpl): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: var(--theme-bg); border-radius: 6px; border: 1px solid var(--theme-border);">
                            <div>
                                <a href="<?= e(getSetting('app_url')) ?>/templates/edit?id=<?= $tpl['id'] ?>" style="font-weight: 600; color: var(--theme-blurple); text-decoration: none; font-size: 14px;">
                                    [Template] <?= e($tpl['name']) ?>
                                </a>
                                <span style="font-size: 12px; color: var(--theme-dark-slate); display: block;"><?= e($tpl['subject'] ?? '') ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach ($forms as $f): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: var(--theme-bg); border-radius: 6px; border: 1px solid var(--theme-border);">
                            <div>
                                <a href="<?= e(getSetting('app_url')) ?>/forms/edit?id=<?= $f['id'] ?>" style="font-weight: 600; color: var(--theme-blurple); text-decoration: none; font-size: 14px;">
                                    [Form] <?= e($f['name']) ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
<?php endif; ?>
