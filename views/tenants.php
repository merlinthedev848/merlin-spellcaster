<?php
declare(strict_types=1);
?>

<div class="header-actions" style="margin-bottom: 20px;">
    <div class="page-title">
        <h1>Super Admin Tenant provisioning</h1>
        <p>Manage isolated client marketing databases, view tenant statistics, and spin up new instances on demand.</p>
    </div>
</div>

<div class="grid grid-1-3" style="align-items: start; gap: 24px; margin-bottom: 24px;">
    <!-- Provisioning Wizard Form -->
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 12px; margin-bottom: 16px;">
            <span class="card-title">Provision New Tenant</span>
        </div>
        
        <form method="post" action="<?= e(getSetting('app_url')) ?>/super/tenants/create">
            <?= Auth::csrfField() ?>
            <div class="form-group">
                <label class="form-label" for="name">Company / Organization Name</label>
                <input class="form-control" type="text" id="name" name="name" required placeholder="e.g. Acme Corp">
            </div>

            <div class="form-group">
                <label class="form-label" for="slug">Subdomain Slug</label>
                <input class="form-control" type="text" id="slug" name="slug" required placeholder="e.g. acme" pattern="[a-zA-Z0-9]+">
                <span style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 4px; display: block;">Only letters and numbers allowed (e.g. <code>acme</code>).</span>
            </div>

            <div class="form-group">
                <label class="form-label" for="db_name">Database Name (Pre-created in cPanel)</label>
                <input class="form-control" type="text" id="db_name" name="db_name" required placeholder="e.g. mailer_c1_spellc_acme">
                <span style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 4px; display: block;">Please create this database in your hosting control panel first and assign the database user to it.</span>
            </div>

            <div class="form-group">
                <label class="form-label" for="admin_email">Primary Admin Email</label>
                <input class="form-control" type="email" id="admin_email" name="admin_email" required placeholder="admin@acme.com">
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label" for="admin_password">Admin Access Password</label>
                <input class="form-control" type="password" id="admin_password" name="admin_password" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 38px; font-weight: 600;">
                Deploy Tenant Instance →
            </button>
        </form>
    </div>

    <!-- Active Tenants List -->
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 12px; margin-bottom: 16px;">
            <span class="card-title">Active Tenant Instances</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Organization</th>
                        <th>Subdomain Access</th>
                        <th>Database Name</th>
                        <th style="text-align: center; width: 100px;">CRM Contacts</th>
                        <th style="text-align: center; width: 100px;">Campaigns</th>
                        <th style="width: 80px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tenants)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--theme-dark-slate); padding: 40px;">No tenants provisioned yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tenants as $t): 
                            $primaryUrl = getSetting('app_url');
                            $parsed = parse_url($primaryUrl);
                            $scheme = $parsed['scheme'] ?? 'http';
                            $host = $parsed['host'] ?? 'localhost';
                            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
                            $launchUrl = $scheme . '://' . $t['slug'] . '.' . $host . $port . '/login';
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: var(--theme-dark);"><?= e($t['name']) ?></div>
                                    <div style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 2px;">Created: <?= date('M j, Y', strtotime($t['created_at'])) ?></div>
                                </td>
                                <td>
                                    <code style="font-family: monospace; font-size: 11px; background-color: var(--theme-bg); padding: 4px 8px; border-radius: 4px; border: 1px solid var(--theme-border); color: var(--theme-blurple); font-weight: 600;">
                                        <?= e($t['slug']) ?>.<?= e($host) ?><?= e($port) ?>
                                    </code>
                                </td>
                                <td>
                                    <span style="font-size: 11px; font-family: monospace; color: var(--theme-dark-slate);"><?= e($t['db_name']) ?></span>
                                </td>
                                <td style="text-align: center; font-weight: 600; color: var(--theme-dark);">
                                    <?= (int)$t['contacts'] ?>
                                </td>
                                <td style="text-align: center; font-weight: 600; color: var(--theme-dark);">
                                    <?= (int)$t['campaigns'] ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="<?= e($launchUrl) ?>" target="_blank" class="btn btn-primary" style="padding: 4px 8px; font-size: 11px; text-decoration: none;">
                                        Launch ↗
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
