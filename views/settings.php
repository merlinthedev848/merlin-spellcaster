<?php
declare(strict_types=1);
$appSecret = getSetting('app_secret');
$cronSecret = getSetting('cron_secret');
$appUrl = rtrim(getSetting('app_url', 'http://localhost/merlin-spellcaster'), '/');
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Settings</h1>
        <p>Manage connection settings, SMTP credentials, and cron automation tokens.</p>
    </div>
    <div>
        <button type="submit" form="settings_form" class="btn btn-primary" style="padding: 10px 20px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>Save Settings Profile</button>
    </div>
</div>

<div style="max-width: 1200px; margin: 0 auto;">
    <form method="post" action="" id="settings_form">
        <?= Auth::csrfField() ?>
        <div class="grid grid-2" style="align-items: start;">
        <!-- Left Column: General & SMTP settings -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <!-- General Settings Card -->
            <div class="card">
                <div class="card-header"><span class="card-title">General Platform Settings</span></div>
                
                <div class="form-group">
                    <label class="form-label" for="setting_app_name">Application Name</label>
                    <input class="form-control" type="text" id="setting_app_name" name="setting_app_name" value="<?= e(getSetting('app_name', 'Merlin Spellcaster')) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="setting_app_url">Application Base URL</label>
                    <input class="form-control" type="url" id="setting_app_url" name="setting_app_url" value="<?= e($appUrl) ?>" required>
                    <p style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 4px;">Important for absolute tracking links (open pixel and redirects).</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="setting_tracking_enabled">Tracking Status</label>
                    <select class="form-control" id="setting_tracking_enabled" name="setting_tracking_enabled">
                        <option value="1" <?= getSetting('tracking_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled (Track opens & clicks)</option>
                        <option value="0" <?= getSetting('tracking_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="setting_company_address">Company Physical Postal Address</label>
                    <textarea class="form-control" id="setting_company_address" name="setting_company_address" style="min-height: 60px; font-size: 13px;" placeholder="e.g. CK Media Services, 123 Main St, Suite 100, Austin, TX 78701"><?= e(getSetting('company_address', '')) ?></textarea>
                    <p style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 4px;">Required for compliance with the US CAN-SPAM Act. This address is automatically appended to the footer of all outgoing marketing broadcasts.</p>
                </div>
            </div>

            <!-- SMTP Credentials -->
            <div class="card">
                <div class="card-header"><span class="card-title">SMTP Mailer Settings</span></div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="setting_smtp_host">SMTP Host Address</label>
                        <input class="form-control" type="text" id="setting_smtp_host" name="setting_smtp_host" value="<?= e(getSetting('smtp_host', 'localhost')) ?>" placeholder="e.g. smtp.mailgun.org" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="setting_smtp_port">SMTP Port</label>
                        <input class="form-control" type="number" id="setting_smtp_port" name="setting_smtp_port" value="<?= e(getSetting('smtp_port', '587')) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="setting_smtp_encryption">Encryption Type</label>
                        <select class="form-control" id="setting_smtp_encryption" name="setting_smtp_encryption">
                            <option value="tls" <?= getSetting('smtp_encryption', 'tls') === 'tls' ? 'selected' : '' ?>>TLS (Recommended)</option>
                            <option value="ssl" <?= getSetting('smtp_encryption', 'tls') === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
                            <option value="none" <?= getSetting('smtp_encryption', 'tls') === 'none' ? 'selected' : '' ?>>None (Plain TCP)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="setting_smtp_user">SMTP Username</label>
                        <input class="form-control" type="text" id="setting_smtp_user" name="setting_smtp_user" value="<?= e(getSetting('smtp_user')) ?>" placeholder="e.g. postmaster@domain.com">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="setting_smtp_pass">SMTP Password</label>
                    <input class="form-control" type="password" id="setting_smtp_pass" name="setting_smtp_pass" placeholder="••••••••">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="setting_smtp_from_name">From Sender Name</label>
                        <input class="form-control" type="text" id="setting_smtp_from_name" name="setting_smtp_from_name" value="<?= e(getSetting('smtp_from_name', 'Merlin Spellcaster')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="setting_smtp_from_email">From Sender Email</label>
                        <input class="form-control" type="email" id="setting_smtp_from_email" name="setting_smtp_from_email" value="<?= e(getSetting('smtp_from_email', 'noreply@localhost')) ?>" required>
                    </div>
                </div>

                <!-- Test SMTP Connection Action -->
                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--theme-border);">
                    <label class="form-label" style="margin-bottom: 6px; font-weight: 600;">Test SMTP Mailer Connection</label>
                    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                        <input class="form-control" type="email" id="test_smtp_recipient" placeholder="Recipient Email (e.g. admin@domain.com)" style="max-width: 280px; margin-bottom: 0; font-size: 13px;" value="<?= e($_SESSION['user_email'] ?? '') ?>">
                        <button type="button" id="btn_test_smtp" class="btn btn-secondary" style="font-size: 13px; font-weight: 600; padding: 7px 14px; white-space: nowrap;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>Send Test Email
                        </button>
                    </div>
                    <div id="smtp_test_status" style="margin-top: 8px; font-size: 12px; font-weight: 600;"></div>
                </div>
            </div>

            <!-- IMAP Bounce Inbox Connection -->
            <div class="card">
                 <div class="card-header"><span class="card-title">IMAP Bounce Inbox Connection</span></div>
                 
                 <!-- Checkbox to toggle inheritance -->
                 <div class="form-group" style="margin-bottom: 20px;">
                     <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: var(--theme-dark);">
                         <input type="checkbox" id="setting_bounce_imap_inherit" name="setting_bounce_imap_inherit" value="1" <?= getSetting('bounce_imap_inherit', '1') === '1' ? 'checked' : '' ?> onchange="toggleImapCredentialsField()" style="accent-color: var(--theme-blurple);">
                         Inherit credentials from SMTP Mailer Settings (Recommended)
                     </label>
                 </div>

                 <!-- Custom IMAP Input fields (hidden if inherit is checked) -->
                 <div id="custom_imap_fields" style="display: <?= getSetting('bounce_imap_inherit', '1') === '1' ? 'none' : 'block' ?>; margin-bottom: 20px; padding: 16px; background-color: var(--theme-bg); border-radius: 6px; border: 1px solid var(--theme-border);">
                     <div class="form-row" style="margin-bottom: 12px;">
                         <div class="form-group" style="margin-bottom: 0;">
                             <label class="form-label" for="setting_bounce_imap_host">IMAP Host</label>
                             <input class="form-control" type="text" id="setting_bounce_imap_host" name="setting_bounce_imap_host" value="<?= e(getSetting('bounce_imap_host')) ?>" placeholder="e.g. imap.domain.com">
                         </div>
                         <div class="form-group" style="margin-bottom: 0;">
                             <label class="form-label" for="setting_bounce_imap_port">IMAP Port</label>
                             <input class="form-control" type="number" id="setting_bounce_imap_port" name="setting_bounce_imap_port" value="<?= e(getSetting('bounce_imap_port', '993')) ?>" placeholder="e.g. 993">
                         </div>
                     </div>
                     <div class="form-row" style="margin-bottom: 12px; margin-top: 12px;">
                         <div class="form-group" style="margin-bottom: 0;">
                             <label class="form-label" for="setting_bounce_imap_user">IMAP Username</label>
                             <input class="form-control" type="text" id="setting_bounce_imap_user" name="setting_bounce_imap_user" value="<?= e(getSetting('bounce_imap_user')) ?>" placeholder="name@domain.com">
                         </div>
                         <div class="form-group" style="margin-bottom: 0;">
                             <label class="form-label" for="setting_bounce_imap_pass">IMAP Password</label>
                             <input class="form-control" type="password" id="setting_bounce_imap_pass" name="setting_bounce_imap_pass" value="" placeholder="Leave empty to keep existing password">
                         </div>
                     </div>
                     <div class="form-group" style="margin-bottom: 0; margin-top: 12px;">
                         <label class="form-label" for="setting_bounce_imap_encryption">IMAP Encryption</label>
                         <select class="form-control" id="setting_bounce_imap_encryption" name="setting_bounce_imap_encryption">
                             <option value="ssl" <?= getSetting('bounce_imap_encryption', 'ssl') === 'ssl' ? 'selected' : '' ?>>SSL (Port 993)</option>
                             <option value="tls" <?= getSetting('bounce_imap_encryption', 'ssl') === 'tls' ? 'selected' : '' ?>>TLS (Port 143)</option>
                             <option value="none" <?= getSetting('bounce_imap_encryption', 'ssl') === 'none' ? 'selected' : '' ?>>None (Port 143)</option>
                         </select>
                     </div>
                 </div>

                <div style="margin-bottom: 16px;">
                    <button type="button" class="btn btn-secondary" id="btn_fetch_imap_folders" style="width: 100%; justify-content: center;">
                        Test Connection & Fetch Folders
                    </button>
                    <div id="imap_fetch_status" style="font-size: 12px; margin-top: 8px; text-align: center;"></div>
                </div>

                <div class="form-row" style="margin-bottom: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="setting_bounce_imap_folder_bounces">Check Bounces In Folder</label>
                        <input class="form-control" type="text" id="setting_bounce_imap_folder_bounces" name="setting_bounce_imap_folder_bounces" value="<?= e(getSetting('bounce_imap_folder_bounces', 'INBOX')) ?>" placeholder="e.g. INBOX">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="setting_bounce_imap_folder_replies">Check Replies In Folder</label>
                        <input class="form-control" type="text" id="setting_bounce_imap_folder_replies" name="setting_bounce_imap_folder_replies" value="<?= e(getSetting('bounce_imap_folder_replies', 'INBOX')) ?>" placeholder="e.g. INBOX">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="setting_bounce_imap_action">After Processing Action</label>
                    <select class="form-control" id="setting_bounce_imap_action" name="setting_bounce_imap_action">
                        <option value="mark_read" <?= getSetting('bounce_imap_action', 'mark_read') === 'mark_read' ? 'selected' : '' ?>>Mark as Read (Leave in folder)</option>
                        <option value="move" <?= getSetting('bounce_imap_action', 'mark_read') === 'move' ? 'selected' : '' ?>>Move to Archive Directories</option>
                        <option value="delete" <?= getSetting('bounce_imap_action', 'mark_read') === 'delete' ? 'selected' : '' ?>>Delete Email Completely</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="setting_bounce_imap_folder_archive_bounces">Archive Bounces To Folder</label>
                        <input class="form-control" type="text" id="setting_bounce_imap_folder_archive_bounces" name="setting_bounce_imap_folder_archive_bounces" value="<?= e(getSetting('bounce_imap_folder_archive_bounces', 'INBOX.ProcessedBounces')) ?>" placeholder="e.g. INBOX.ProcessedBounces">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="setting_bounce_imap_folder_archive_replies">Archive Replies To Folder</label>
                        <input class="form-control" type="text" id="setting_bounce_imap_folder_archive_replies" name="setting_bounce_imap_folder_archive_replies" value="<?= e(getSetting('bounce_imap_folder_archive_replies', 'INBOX.ProcessedReplies')) ?>" placeholder="e.g. INBOX.ProcessedReplies">
                    </div>
                </div>
            </div>
            

        </div>

        <!-- Right Column: Security, Webhooks & Cron instructions -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <!-- Security Tokens Info -->
            <div class="card">
                <div class="card-header"><span class="card-title">Security & API Credentials</span></div>
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div>
                        <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">App Cryptographic Secret Key</span>
                        <code style="background-color: var(--theme-bg); padding: 6px 10px; border-radius: 4px; font-size: 12px; border: 1px solid var(--theme-border); display: block; word-break: break-all; font-weight: 600;"><?= e($appSecret) ?></code>
                    </div>
                    <div>
                        <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Automation Cron Token</span>
                        <code style="background-color: var(--theme-bg); padding: 6px 10px; border-radius: 4px; font-size: 12px; border: 1px solid var(--theme-border); display: block; word-break: break-all; font-weight: 600;"><?= e($cronSecret) ?></code>
                    </div>
                </div>
            </div>

            <!-- Cron Instructions -->
            <div class="card">
                <div class="card-header"><span class="card-title">Cron Job Configuration</span></div>
                <p style="font-size: 13px; color: var(--theme-dark-slate); margin-bottom: 16px;">To automate email queue sending and workflow triggers on your Enhance or cPanel hosting account, configure a cron job to run every 5 minutes:</p>
                <div style="background-color: #f1f5f9; padding: 12px; border-radius: 6px; border: 1px solid var(--theme-border); margin-bottom: 12px;">
                    <span style="font-size: 11px; font-weight: 600; color: var(--theme-dark-slate); display: block; margin-bottom: 6px; text-transform: uppercase;">Cron Expression Command</span>
                    <code style="font-size: 12px; word-break: break-all; font-weight: 600;">*/5 * * * * curl -s "<?= e($appUrl) ?>/cron?secret=<?= e($cronSecret) ?>"</code>
                </div>
                <p style="font-size: 11px; color: var(--theme-dark-slate);">Alternatively, trigger via CLI shell command: <br><code>*/5 * * * * php <?= e(dirname(__DIR__)) ?>/index.php /cron <?= e($cronSecret) ?></code></p>
            </div>

            <!-- Webhook Config (Mailgun) -->
            <div class="card">
                <div class="card-header"><span class="card-title">Mailgun Webhook Integration</span></div>
                <p style="font-size: 13px; color: var(--theme-dark-slate); margin-bottom: 16px;">Configure Mailgun webhooks to track permanent bounces, complaints, and spam events automatically in your CRM:</p>
                <div style="background-color: #f1f5f9; padding: 12px; border-radius: 6px; border: 1px solid var(--theme-border); margin-bottom: 12px;">
                    <span style="font-size: 11px; font-weight: 600; color: var(--theme-dark-slate); display: block; margin-bottom: 6px; text-transform: uppercase;">Webhook target URL</span>
                    <code style="font-size: 12px; word-break: break-all; font-weight: 600;"><?= e($appUrl) ?>/api/webhooks</code>
                </div>
                <p style="font-size: 11px; color: var(--theme-dark-slate);">In Mailgun Dashboard under <strong>Sending > Webhooks</strong>, select events: <br>• Permanent Failure (Bounce) <br>• Spam Complaint</p>
            </div>
        </div>
    </div>
</form>
</div>

<script>
function toggleImapCredentialsField() {
    const inherit = document.getElementById('setting_bounce_imap_inherit').checked;
    document.getElementById('custom_imap_fields').style.display = inherit ? 'none' : 'block';
}

document.getElementById('btn_fetch_imap_folders').addEventListener('click', async function() {
    const btn = this;
    const status = document.getElementById('imap_fetch_status');
    const inherit = document.getElementById('setting_bounce_imap_inherit').checked;
    
    let host, port, user, pass, ssl;
    
    if (inherit) {
        const smtpHost = document.getElementById('setting_smtp_host').value.trim();
        const smtpUser = document.getElementById('setting_smtp_user').value.trim();
        const smtpPass = document.getElementById('setting_smtp_pass').value;
        const smtpEnc = document.getElementById('setting_smtp_encryption').value;

        if (!smtpHost || !smtpUser || !smtpPass) {
            status.innerHTML = '<span style="color:var(--danger)">Please fill in SMTP Host, Username, and Password first.</span>';
            return;
        }

        host = smtpHost;
        if (smtpHost.toLowerCase().startsWith('smtp.')) {
            host = 'imap.' + smtpHost.substring(5);
        }
        user = smtpUser;
        pass = smtpPass;
        ssl = (smtpEnc === 'ssl') ? '1' : '0';
        port = (smtpEnc === 'ssl') ? '993' : '143';
    } else {
        host = document.getElementById('setting_bounce_imap_host').value.trim();
        port = document.getElementById('setting_bounce_imap_port').value.trim();
        user = document.getElementById('setting_bounce_imap_user').value.trim();
        pass = document.getElementById('setting_bounce_imap_pass').value;
        const enc = document.getElementById('setting_bounce_imap_encryption').value;
        ssl = (enc === 'ssl') ? '1' : '0';

        if (!host || !user) {
            status.innerHTML = '<span style="color:var(--danger)">Please fill in IMAP Host and Username.</span>';
            return;
        }
    }

    btn.disabled = true;
    btn.innerHTML = 'Connecting...';
    status.innerHTML = '';

    const formData = new FormData();
    formData.append('host', host);
    formData.append('port', port);
    formData.append('user', user);
    formData.append('pass', pass);
    formData.append('ssl', ssl);

    try {
        const response = await fetch('<?= e(BASE_PATH) ?>/api/imap-folders', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success && data.folders) {
            status.innerHTML = '<span style="color:var(--success)">Connection successful! Folders loaded.</span>';
            
            // Replace the 4 inputs with select boxes
            const folderInputs = [
                'setting_bounce_imap_folder_bounces',
                'setting_bounce_imap_folder_replies',
                'setting_bounce_imap_folder_archive_bounces',
                'setting_bounce_imap_folder_archive_replies'
            ];
            
            folderInputs.forEach(id => {
                const el = document.getElementById(id);
                if (el.tagName === 'INPUT') {
                    const currentVal = el.value;
                    const select = document.createElement('select');
                    select.className = 'form-control';
                    select.id = id;
                    select.name = id;
                    
                    data.folders.forEach(folder => {
                        const option = document.createElement('option');
                        option.value = folder;
                        option.textContent = folder;
                        if (folder === currentVal) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
                    
                    // Add existing value if it wasn't in the list
                    if (!data.folders.includes(currentVal) && currentVal !== '') {
                        const option = document.createElement('option');
                        option.value = currentVal;
                        option.textContent = currentVal + " (Custom/Missing)";
                        option.selected = true;
                        select.appendChild(option);
                    }
                    
                    el.parentNode.replaceChild(select, el);
                } else if (el.tagName === 'SELECT') {
                    // Just update options if already a select
                    const currentVal = el.value;
                    el.innerHTML = '';
                    data.folders.forEach(folder => {
                        const option = document.createElement('option');
                        option.value = folder;
                        option.textContent = folder;
                        if (folder === currentVal) {
                            option.selected = true;
                        }
                        el.appendChild(option);
                    });
                }
            });
        } else {
            status.innerHTML = `<span style="color:var(--danger)">Error: ${data.error}</span>`;
        }
    } catch (e) {
        status.innerHTML = '<span style="color:var(--danger)">Network error or invalid response while contacting server. (' + e.message + ')</span>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Test Connection & Fetch Folders';
    }
});

document.getElementById('btn_test_smtp').addEventListener('click', async function() {
    const btn = this;
    const status = document.getElementById('smtp_test_status');
    const recipient = document.getElementById('test_smtp_recipient').value.trim();

    const host = document.getElementById('setting_smtp_host').value.trim();
    const port = document.getElementById('setting_smtp_port').value.trim();
    const encryption = document.getElementById('setting_smtp_encryption').value;
    const user = document.getElementById('setting_smtp_user').value.trim();
    const pass = document.getElementById('setting_smtp_pass').value;
    const fromName = document.getElementById('setting_smtp_from_name').value.trim();
    const fromEmail = document.getElementById('setting_smtp_from_email').value.trim();

    if (!recipient) {
        status.innerHTML = '<span style="color:var(--danger)">Please enter a recipient email address to send the test to.</span>';
        return;
    }
    if (!host || !fromEmail) {
        status.innerHTML = '<span style="color:var(--danger)">Please enter SMTP Host and Sender Email address.</span>';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = 'Connecting & Sending...';
    status.innerHTML = '<span style="color:var(--theme-dark-slate)">Connecting to ' + host + ':' + port + '...</span>';

    const formData = new FormData();
    formData.append('host', host);
    formData.append('port', port);
    formData.append('encryption', encryption);
    formData.append('user', user);
    formData.append('pass', pass);
    formData.append('from_name', fromName);
    formData.append('from_email', fromEmail);
    formData.append('recipient_email', recipient);

    try {
        const response = await fetch('<?= e(BASE_PATH) ?>/api/test-smtp', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            status.innerHTML = `<span style="color:var(--success)">✅ ${data.message}</span>`;
        } else {
            status.innerHTML = `<span style="color:var(--danger)">❌ Connection/Delivery Failed: ${data.error}</span>`;
        }
    } catch (e) {
        status.innerHTML = '<span style="color:var(--danger)">❌ Network error: ' + e.message + '</span>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>Send Test Email';
    }
});
</script>
