<?php
declare(strict_types=1);

$currentTab = $_GET['tab'] ?? 'builder';
$db = Database::getConnection();
$lists = $db->query("SELECT id, name FROM lists ORDER BY name ASC")->fetchAll();
?>

<div class="header-actions" style="margin-bottom: 20px;">
    <div class="page-title">
        <h1>Automation & Webhook Workflow Suite</h1>
        <p>Build visual branching workflows, dispatch Twilio SMS broadcasts, handle webhooks, and automate RSS feeds.</p>
    </div>
</div>

<!-- Tabs -->
<div style="display: flex; gap: 4px; border-bottom: 1px solid var(--theme-border); margin-bottom: 24px; flex-wrap: wrap;">
    <button class="btn wf-tab-btn <?= $currentTab === 'builder' ? 'active' : '' ?>" onclick="switchWfTab(event, 'tab-builder')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'builder' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'builder' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        🔀 Visual Flow Builder
    </button>
    <button class="btn wf-tab-btn <?= $currentTab === 'sms' ? 'active' : '' ?>" onclick="switchWfTab(event, 'tab-sms')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'sms' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'sms' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        📱 Twilio SMS Marketing
    </button>
    <button class="btn wf-tab-btn <?= $currentTab === 'webhooks' ? 'active' : '' ?>" onclick="switchWfTab(event, 'tab-webhooks')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'webhooks' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'webhooks' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        📡 Outbound & Inbound Webhooks
    </button>
    <button class="btn wf-tab-btn <?= $currentTab === 'rss' ? 'active' : '' ?>" onclick="switchWfTab(event, 'tab-rss')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'rss' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'rss' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        📰 RSS-to-Email Automations
    </button>
</div>

<!-- TAB 1: Visual Flow Builder -->
<div id="tab-builder" class="wf-tab-content" style="display: <?= $currentTab === 'builder' ? 'block' : 'none' ?>;">
    <div class="card" style="padding: 24px; margin-bottom: 24px;">
        <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
            <span class="card-title">Multi-Branching Visual Workflow Designer</span>
            <button class="btn btn-primary" onclick="saveWorkflow()">Save Workflow Steps</button>
        </div>
        
        <div class="grid grid-3" style="gap: 16px; margin-bottom: 20px;">
            <div class="form-group">
                <label class="form-label">Workflow Title</label>
                <input class="form-control" type="text" id="wf_title" value="New Lead Nurture Sequence">
            </div>
            <div class="form-group">
                <label class="form-label">Trigger Event</label>
                <select class="form-control" id="wf_trigger">
                    <option value="subscribe" selected>On Contact Subscribed / Added</option>
                    <option value="tag_added">On Tag Added (#scraped-buyer)</option>
                    <option value="link_clicked">On Campaign Link Clicked</option>
                    <option value="score_threshold">On Lead Score Reaches 50+</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="form-control" id="wf_status">
                    <option value="active" selected>Active & Listening</option>
                    <option value="draft">Draft / Paused</option>
                </select>
            </div>
        </div>

        <div style="background: var(--theme-bg); border: 2px dashed var(--theme-border); border-radius: 8px; padding: 24px; min-height: 250px;">
            <div style="display: flex; flex-direction: column; align-items: center; gap: 16px;">
                <div style="background: var(--theme-blurple-light); color: var(--theme-blurple); padding: 12px 24px; border-radius: 8px; font-weight: 700; border: 1px solid rgba(99,91,255,0.2);">
                    ⚡ TRIGGER: On Contact Subscribed
                </div>
                <div style="width: 2px; height: 24px; background: var(--theme-border);"></div>
                <div style="background: white; border: 1px solid var(--theme-border); padding: 12px 20px; border-radius: 8px; font-weight: 600; box-shadow: var(--theme-shadow-sm);">
                    📧 STEP 1: Send Welcome Email Campaign
                </div>
                <div style="width: 2px; height: 24px; background: var(--theme-border);"></div>
                <div style="background: white; border: 1px solid var(--theme-border); padding: 12px 20px; border-radius: 8px; font-weight: 600; box-shadow: var(--theme-shadow-sm);">
                    ⏳ STEP 2: Wait 2 Days
                </div>
                <div style="width: 2px; height: 24px; background: var(--theme-border);"></div>
                <div style="background: #fef9c3; color: #ca8a04; border: 1px solid rgba(202,138,4,0.3); padding: 12px 20px; border-radius: 8px; font-weight: 600;">
                    ❓ CONDITION: Has Contact Opened Email?
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TAB 2: Twilio SMS Marketing -->
<div id="tab-sms" class="wf-tab-content" style="display: <?= $currentTab === 'sms' ? 'block' : 'none' ?>;">
    <div class="grid grid-2" style="gap: 24px; align-items: start;">
        <!-- Config -->
        <div class="card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
                <span class="card-title">Twilio API Settings</span>
            </div>
            <form method="post" action="<?= e(getSetting('app_url')) ?>/workflows">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="save_sms_settings">
                <div class="form-group">
                    <label class="form-label">Twilio Account SID</label>
                    <input class="form-control" type="text" name="twilio_sid" value="<?= e(getSetting('twilio_sid')) ?>" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxx">
                </div>
                <div class="form-group">
                    <label class="form-label">Twilio Auth Token</label>
                    <input class="form-control" type="password" name="twilio_token" value="<?= e(getSetting('twilio_token')) ?>" placeholder="••••••••••••••••">
                </div>
                <div class="form-group">
                    <label class="form-label">Twilio From Phone Number</label>
                    <input class="form-control" type="text" name="twilio_from" value="<?= e(getSetting('twilio_from')) ?>" placeholder="+1234567890">
                </div>
                <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: center;">Save Twilio Credentials</button>
            </form>
        </div>

        <!-- SMS Broadcast Composer -->
        <div class="card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
                <span class="card-title">Send SMS Broadcast</span>
            </div>
            <form method="post" action="<?= e(getSetting('app_url')) ?>/workflows">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="send_sms_broadcast">
                <div class="form-group">
                    <label class="form-label">Target Recipient List</label>
                    <select class="form-control" name="list_id">
                        <option value="0">All Contacts with Phone Numbers</option>
                        <?php foreach ($lists as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= e($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">SMS Message Content (Max 160 chars)</label>
                    <textarea class="form-control" name="sms_message" rows="4" maxlength="160" placeholder="Hi {{first_name}}, thanks for reaching out! Check out our latest voiceover demos at..." required></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; font-weight: 700; padding: 12px;">
                    📱 Dispatch SMS Broadcast Now
                </button>
            </form>
        </div>
    </div>
</div>

<!-- TAB 3: Webhooks -->
<div id="tab-webhooks" class="wf-tab-content" style="display: <?= $currentTab === 'webhooks' ? 'block' : 'none' ?>;">
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
            <span class="card-title">Outbound Webhook Dispatcher & API Triggers</span>
        </div>
        <div class="form-group">
            <label class="form-label">Webhook Destination Endpoint URL</label>
            <input class="form-control" type="url" value="https://zapier.com/hooks/catch/12345/abcde" placeholder="https://your-api.com/webhook">
        </div>
        <div class="form-group">
            <label class="form-label">Inbound Webhook Endpoint URL (Copy to Zapier/Make)</label>
            <input class="form-control" type="text" readonly value="<?= e(getSetting('app_url')) ?>/api/webhook/catch" onclick="this.select()">
        </div>
    </div>
</div>

<!-- TAB 4: RSS-to-Email -->
<div id="tab-rss" class="wf-tab-content" style="display: <?= $currentTab === 'rss' ? 'block' : 'none' ?>;">
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
            <span class="card-title">Automated RSS-to-Email Feed Campaigns</span>
        </div>
        <div class="form-group">
            <label class="form-label">Blog / Podcast RSS Feed URL</label>
            <input class="form-control" type="url" placeholder="https://yourdomain.com/feed.xml">
        </div>
    </div>
</div>

<script>
function switchWfTab(e, tabId) {
    document.querySelectorAll('.wf-tab-btn').forEach(btn => {
        btn.style.borderBottomColor = 'transparent';
        btn.style.color = 'var(--theme-dark-slate)';
    });
    e.currentTarget.style.borderBottomColor = 'var(--theme-blurple)';
    e.currentTarget.style.color = 'var(--theme-blurple)';
    document.querySelectorAll('.wf-tab-content').forEach(c => c.style.display = 'none');
    document.getElementById(tabId).style.display = 'block';
}

function saveWorkflow() {
    fetch('<?= e(BASE_PATH) ?>/automations/save', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({title: document.getElementById('wf_title').value})
    })
    .then(r => r.json())
    .then(res => alert(res.message || 'Workflow saved!'));
}
</script>
