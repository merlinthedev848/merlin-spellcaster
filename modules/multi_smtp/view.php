<?php 
declare(strict_types=1); 
$appUrl = getSetting('app_url');
?>

<div class="header-actions" style="margin-bottom: 24px;">
    <div class="page-title">
        <h1>Multi-SMTP Intelligent Routing</h1>
        <p>Distribute your email volume across a load-balanced pool of SMTP providers.</p>
    </div>
</div>

<div class="grid grid-2" style="gap: 24px; align-items: start;">
    
    <!-- Left Column: Add New SMTP -->
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="margin-bottom: 20px;">
            <span class="card-title">Add New SMTP Endpoint</span>
        </div>
        
        <form method="post" action="<?= e($appUrl) ?>/multi-smtp?action=add">
            <?= Auth::csrfField() ?>
            <div class="form-group">
                <label class="form-label">Provider Name (e.g. SendGrid Account 1)</label>
                <input class="form-control" type="text" name="name" required>
            </div>
            
            <div class="grid grid-2" style="gap: 16px;">
                <div class="form-group">
                    <label class="form-label">SMTP Host</label>
                    <input class="form-control" type="text" name="host" placeholder="smtp.sendgrid.net" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Port</label>
                    <input class="form-control" type="number" name="port" value="587" required>
                </div>
            </div>
            
            <div class="grid grid-2" style="gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input class="form-control" type="text" name="username">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input class="form-control" type="password" name="password">
                </div>
            </div>
            
            <div class="grid grid-2" style="gap: 16px;">
                <div class="form-group">
                    <label class="form-label">From Email (Optional)</label>
                    <input class="form-control" type="email" name="from_email" placeholder="Overrides global default">
                </div>
                <div class="form-group">
                    <label class="form-label">From Name (Optional)</label>
                    <input class="form-control" type="text" name="from_name" placeholder="Overrides global default">
                </div>
            </div>
            
            <div class="grid grid-2" style="gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Encryption</label>
                    <select class="form-control" name="encryption">
                        <option value="tls">TLS (Recommended)</option>
                        <option value="ssl">SSL</option>
                        <option value="">None</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Daily Send Limit</label>
                    <input class="form-control" type="number" name="daily_limit" value="0" placeholder="0 = Unlimited">
                </div>
            </div>
            
            <div style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Attach SMTP Provider</button>
            </div>
        </form>
    </div>
    
    <!-- Right Column: Attached Servers -->
    <div style="display: flex; flex-direction: column; gap: 16px;">
        
        <?php if (empty($servers)): ?>
            <div class="card" style="padding: 40px; text-align: center; color: var(--theme-dark-slate);">
                <p>No extra SMTP endpoints configured.</p>
                <p style="font-size: 13px; margin-top: 8px;">The system will use your global Default SMTP configuration.</p>
            </div>
        <?php else: ?>
            <?php foreach ($servers as $s): ?>
                <div class="card" style="padding: 20px; border-left: 4px solid <?= $s['status'] == 1 ? 'var(--success)' : 'var(--danger)' ?>;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                        <div>
                            <h3 style="margin: 0; font-size: 16px; color: var(--theme-dark);"><?= e($s['name']) ?></h3>
                            <div style="font-size: 12px; color: var(--theme-dark-slate); margin-top: 4px;">
                                <?= e($s['host']) ?>:<?= e($s['port']) ?> &bull; <?= strtoupper(e($s['encryption'])) ?>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <button type="button" class="btn btn-secondary" onclick="testSmtpServer(<?= $s['id'] ?>, this)" style="padding: 4px 10px; font-size: 12px;">Test Connection</button>
                            <form method="post" action="<?= e($appUrl) ?>/multi-smtp?action=toggle" style="margin:0;">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn <?= $s['status'] == 1 ? 'btn-secondary' : 'btn-primary' ?>" style="padding: 4px 10px; font-size: 12px;">
                                    <?= $s['status'] == 1 ? 'Disable' : 'Enable' ?>
                                </button>
                            </form>
                            <form method="post" action="<?= e($appUrl) ?>/multi-smtp?action=delete" style="margin:0;" onsubmit="return confirm('Remove this SMTP provider?');">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-danger" style="padding: 4px 10px; font-size: 12px;">Remove</button>
                            </form>
                        </div>
                    </div>
                    
                    <div style="background: var(--theme-bg); padding: 12px; border-radius: 6px; font-size: 12px; color: var(--theme-dark-slate); display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <strong>Daily Limit:</strong> <?= $s['daily_limit'] > 0 ? number_format((float)$s['daily_limit']) : 'Unlimited' ?>
                        </div>
                        <div>
                            <strong>Sent Today:</strong> <?= number_format((float)$s['sent_today']) ?>
                        </div>
                        <div>
                            <strong>Auth User:</strong> <?= $s['username'] !== '' ? e($s['username']) : 'None' ?>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>Errors:</strong> 
                                <span style="color: <?= $s['error_count'] > 0 ? 'var(--danger)' : 'inherit' ?>; font-weight: <?= $s['error_count'] > 0 ? '700' : 'normal' ?>;">
                                    <?= $s['error_count'] ?>
                                </span>
                            </div>
                            <?php if ($s['error_count'] > 0): ?>
                                <form method="post" action="<?= e($appUrl) ?>/multi-smtp?action=reset_errors" style="margin:0;">
                                    <?= Auth::csrfField() ?>
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-secondary" style="padding: 2px 6px; font-size: 10px; height: auto;">Clear</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($s['daily_limit'] > 0): ?>
                        <?php $pct = min(100, round(($s['sent_today'] / max(1, $s['daily_limit'])) * 100)); ?>
                        <div style="margin-top: 16px;">
                            <div style="display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 6px; font-weight: 600; color: var(--theme-dark-slate);">
                                <span>Volume Utilization</span>
                                <span><?= $pct ?>%</span>
                            </div>
                            <div style="background-color: var(--theme-border); height: 6px; border-radius: 3px; overflow: hidden;">
                                <div style="width: <?= $pct ?>%; height: 100%; background-color: <?= $pct > 90 ? 'var(--danger)' : 'var(--theme-blurple)' ?>; border-radius: 3px; transition: width 0.3s ease;"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
    </div>
</div>

<script>
    async function testSmtpServer(id, btn) {
        const origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = "Testing...";
        try {
            const resp = await fetch("/multi-smtp/test?id=" + id);
            const data = await resp.json();
            if (data.success) {
                alert("✓ SMTP Connection Successful! " + (data.message || "Test email delivered cleanly."));
            } else {
                alert("✖ SMTP Connection Failed: " + (data.error || "Could not connect to host."));
            }
        } catch (e) {
            alert("Error testing SMTP server: " + e.message);
        } finally {
            btn.disabled = false;
            btn.textContent = origText;
        }
    }
</script>
