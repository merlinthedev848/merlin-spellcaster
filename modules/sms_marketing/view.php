<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>SMS Marketing Engine</h1>
        <p>Send instant SMS broadcasts and campaign alerts directly to target segments using Twilio integration.</p>
    </div>
</div>

<?php if (isset($error) && $error !== null): ?>
    <div style="background-color: var(--danger-light); color: var(--danger); border: 1px solid rgba(255,91,96,0.1); border-radius: 6px; padding: 12px; font-size: 13px; font-weight: 500; margin-bottom: 24px; max-width: 1200px; margin-left: auto; margin-right: auto;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="grid grid-2" style="max-width: 1200px; margin: 0 auto; align-items: start;">
    <!-- Left Column: Send SMS Form -->
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 16px; margin-bottom: 20px;">
            <span class="card-title">Compose SMS Broadcast</span>
        </div>
        
        <form method="post" action="?action=send">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="list_id">Target Subscriber List (Optional)</label>
                    <select class="form-control" id="list_id" name="list_id">
                        <option value="0">-- Send to All Lists --</option>
                        <?php foreach ($lists as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= e($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="tag_id">Target CRM Tag Filter (Optional)</label>
                    <select class="form-control" id="tag_id" name="tag_id">
                        <option value="0">-- No Tag Filter --</option>
                        <?php foreach ($tags as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <label class="form-label" for="sms_message" style="margin-bottom: 0;">SMS Content Message</label>
                    <span id="char_counter" style="font-size: 11px; font-weight: 600; color: var(--theme-dark-slate);">0 / 160 chars</span>
                </div>
                <textarea class="form-control" id="sms_message" name="message" required style="min-height: 100px; font-size: 13px;" placeholder="Write message... e.g. Hey! CK Media standard alert: our brand new platform launches in 1 hour! Access now at {{app_url}}" oninput="updateCharCount(this)"></textarea>
                <p style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 4px;">Standard SMS limits are 160 characters. Submitting longer messages will split them automatically.</p>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; font-weight: 600; padding: 12px;">Dispatch SMS Broadcast →</button>
        </form>
    </div>

    <!-- Right Column: Settings Card -->
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 16px; margin-bottom: 20px;">
            <span class="card-title">Twilio API Configuration</span>
        </div>
        
        <form method="post" action="?action=settings">
            <div class="form-group">
                <label class="form-label" for="twilio_sid">Twilio Account SID</label>
                <input class="form-control" type="text" id="twilio_sid" name="twilio_sid" value="<?= e(getSetting('twilio_sid', '')) ?>" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
            </div>

            <div class="form-group">
                <label class="form-label" for="twilio_token">Twilio Auth Token</label>
                <input class="form-control" type="password" id="twilio_token" name="twilio_token" value="<?= e(getSetting('twilio_token', '')) ?>" placeholder="Enter Twilio secret auth token...">
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label" for="twilio_from">Twilio Outgoing Number / Sender ID</label>
                <input class="form-control" type="text" id="twilio_from" name="twilio_from" value="<?= e(getSetting('twilio_from', '')) ?>" placeholder="e.g. +18005550199 or CKSERVICES">
                <p style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 4px;">Use E.164 phone number formatting or a pre-registered alphanumeric Sender ID.</p>
            </div>

            <button type="submit" class="btn btn-secondary" style="width: 100%; font-weight: 600; padding: 12px;">Save Twilio Credentials</button>
        </form>
    </div>
</div>

<!-- History Log Card -->
<div class="card" style="max-width: 1200px; margin: 24px auto 0 auto; padding: 24px;">
    <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 16px; margin-bottom: 20px;">
        <span class="card-title">Recent Dispatched Logs</span>
    </div>
    
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Recipient Number</th>
                    <th>Message Details</th>
                    <th>Status</th>
                    <th>Dispatched At</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--theme-dark-slate); padding: 40px;">No SMS logs dispatched yet. Send a campaign above to test.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--theme-dark);"><?= e($l['phone']) ?></td>
                            <td style="color: var(--theme-dark-slate); max-width: 500px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= e($l['message']) ?></td>
                            <td>
                                <span class="badge badge-active" style="font-size: 10px; font-weight: 700; text-transform: uppercase;">
                                    <?= e($l['status']) ?>
                                </span>
                            </td>
                            <td style="color: var(--theme-dark-slate);"><?= date('M j, Y H:i:s', strtotime($l['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function updateCharCount(textarea) {
        const counter = document.getElementById("char_counter");
        const chars = textarea.value.length;
        counter.textContent = `${chars} / 160 chars`;
        if (chars > 160) {
            counter.style.color = "var(--warning)";
        } else {
            counter.style.color = "var(--theme-dark-slate)";
        }
    }
</script>
