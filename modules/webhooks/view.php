<?php
declare(strict_types=1);

$incomingUrl = rtrim(getSetting('app_url'), '/') . '/webhooks/incoming';
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Webhook Integrations</h1>
        <p>Configure automated data synchronization between Merlin and external platforms (like Zapier, Slack, or CRMs).</p>
    </div>
</div>

<div class="grid grid-2">
    <!-- Outbound Webhooks Card -->
    <div class="card" style="display: flex; flex-direction: column; gap: 20px;">
        <div class="card-header" style="border-bottom: 1px solid var(--stripe-border); padding-bottom: 16px; margin-bottom: 0;">
            <span class="card-title">Outbound Webhooks (Merlin → External)</span>
        </div>
        
        <form method="post" action="?action=create">
            <div class="form-group">
                <label class="form-label" for="name">Friendly Name</label>
                <input class="form-control" type="text" id="name" name="name" required placeholder="e.g. Sync to Zapier CRM">
            </div>

            <div class="form-group">
                <label class="form-label" for="url">Endpoint Destination URL</label>
                <input class="form-control" type="url" id="url" name="url" required placeholder="https://hooks.zapier.com/hooks/catch/...">
            </div>

            <div class="form-group">
                <label class="form-label" for="event_type">Trigger Event</label>
                <select class="form-control" id="event_type" name="event_type" required>
                    <option value="">-- Choose Event Trigger --</option>
                    <option value="contact_added">Contact Added (contact_added)</option>
                    <option value="email_opened">Email Opened (email_opened)</option>
                    <option value="link_clicked">Link Clicked (link_clicked)</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="font-weight: 600;">Create Outbound Hook →</button>
        </form>

        <div class="table-wrapper" style="margin-top: 16px; border: 1px solid var(--stripe-border);">
            <table style="font-size: 13px;">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Event</th>
                        <th>URL</th>
                        <th style="width: 80px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($endpoints)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--stripe-dark-slate); padding: 20px;">No outbound webhooks defined.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($endpoints as $ep): ?>
                            <tr>
                                <td style="font-weight: 600; color: var(--stripe-dark);"><?= e($ep['name']) ?></td>
                                <td><span class="badge badge-active" style="font-size:10px; font-weight:600;"><?= e($ep['event_type']) ?></span></td>
                                <td style="font-family: monospace; color: var(--stripe-dark-slate); font-size:11px; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= e($ep['url']) ?>"><?= e($ep['url']) ?></td>
                                <td>
                                    <form method="post" action="?action=delete&id=<?= $ep['id'] ?>" onsubmit="return confirm('Delete this webhook?');" style="margin:0;">
                                        <button type="submit" class="btn btn-danger" style="padding: 2px 6px; font-size: 10px;">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Inbound Webhooks Card -->
    <div class="card" style="display: flex; flex-direction: column; gap: 20px;">
        <div class="card-header" style="border-bottom: 1px solid var(--stripe-border); padding-bottom: 16px; margin-bottom: 0;">
            <span class="card-title">Inbound Webhooks (External → Merlin)</span>
        </div>
        
        <p style="font-size: 13px; color: var(--stripe-dark-slate); line-height: 1.6;">
            Automatically push subscribers into your lists from external applications (like WordPress, WooCommerce, or Shopify) by sending a JSON POST payload.
        </p>

        <div style="background-color: #fafbfc; border: 1px solid var(--stripe-border); border-radius: 8px; padding: 16px;">
            <span style="font-size: 11px; text-transform: uppercase; color: var(--stripe-dark-slate); font-weight: 600; display: block; margin-bottom: 6px;">POST Webhook URL</span>
            <input class="form-control" type="text" readonly value="<?= e($incomingUrl) ?>" onclick="this.select()" style="font-family: monospace; font-size: 12px; margin-bottom: 0;">
        </div>

        <div>
            <h4 style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--stripe-dark); margin: 0 0 10px;">Payload Fields</h4>
            <table class="data-table" style="font-size: 12px; width: 100%;">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Type</th>
                        <th>Required</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="font-family: monospace; font-weight: 600;">email</td>
                        <td>String</td>
                        <td>Yes</td>
                    </tr>
                    <tr>
                        <td style="font-family: monospace; font-weight: 600;">list_id</td>
                        <td>Integer</td>
                        <td>Yes</td>
                    </tr>
                    <tr>
                        <td style="font-family: monospace; font-weight: 600;">first_name</td>
                        <td>String</td>
                        <td>No</td>
                    </tr>
                    <tr>
                        <td style="font-family: monospace; font-weight: 600;">last_name</td>
                        <td>String</td>
                        <td>No</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div>
            <h4 style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--stripe-dark); margin: 0 0 8px;">Your Target Segment List IDs</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px;">
                <?php foreach ($lists as $l): ?>
                    <div style="background-color: #fafbfc; border: 1px solid var(--stripe-border); padding: 8px 12px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 12px; font-weight: 600; color: var(--stripe-dark); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:110px;" title="<?= e($l['name']) ?>"><?= e($l['name']) ?></span>
                        <span style="font-size: 11px; font-family: monospace; font-weight: 700; color: var(--stripe-blurple);">ID: <?= $l['id'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
