<?php
declare(strict_types=1);
$baseUrl = rtrim(getSetting('app_url'), '/');
?>

<div class="header-actions" style="margin-bottom: 20px;">
    <div class="page-title">
        <h1>Conversion & Viral Engagement Suite</h1>
        <p>Integrate dynamic email countdown timers, balance click traffic using rotators, and manage user growth loops.</p>
    </div>
</div>

<!-- Tabs Row -->
<div style="display: flex; gap: 4px; border-bottom: 1px solid var(--theme-border); margin-bottom: 24px;">
    <button class="btn conversions-tab-btn active" id="btn-tab-timers" onclick="switchConversionsTab(event, 'tab-timers')" style="border: none; border-bottom: 2px solid var(--theme-blurple); background: transparent; padding: 12px 18px; border-radius: 0; color: var(--theme-blurple); font-weight: 600; cursor: pointer;">
        ⏰ Countdown Timers (FOMO)
    </button>
    <button class="btn conversions-tab-btn" id="btn-tab-rotators" onclick="switchConversionsTab(event, 'tab-rotators')" style="border: none; border-bottom: 2px solid transparent; background: transparent; padding: 12px 18px; border-radius: 0; color: var(--theme-dark-slate); font-weight: 600; cursor: pointer;">
        🔗 Smart Link Rotators
    </button>
    <button class="btn conversions-tab-btn" id="btn-tab-referrals" onclick="switchConversionsTab(event, 'tab-referrals')" style="border: none; border-bottom: 2px solid transparent; background: transparent; padding: 12px 18px; border-radius: 0; color: var(--theme-dark-slate); font-weight: 600; cursor: pointer;">
        🔥 Viral Growth Referrals
    </button>
    <button class="btn conversions-tab-btn" id="btn-tab-utm" onclick="switchConversionsTab(event, 'tab-utm')" style="border: none; border-bottom: 2px solid transparent; background: transparent; padding: 12px 18px; border-radius: 0; color: var(--theme-dark-slate); font-weight: 600; cursor: pointer;">
        🏷️ Dynamic UTM Builder
    </button>
    <button class="btn conversions-tab-btn" id="btn-tab-personalization" onclick="switchConversionsTab(event, 'tab-personalization')" style="border: none; border-bottom: 2px solid transparent; background: transparent; padding: 12px 18px; border-radius: 0; color: var(--theme-dark-slate); font-weight: 600; cursor: pointer;">
        🌐 Web Personalization & Popups
    </button>
</div>

<!-- TAB 1: Countdown Timers -->
<div id="tab-timers" class="conversions-tab-content">
    <div class="grid grid-2" style="align-items: start; gap: 24px;">
        <div class="card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
                <span class="card-title">Timer Code Builder</span>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="timer_end_date">Target End Date & Time</label>
                <input class="form-control" type="datetime-local" id="timer_end_date" value="<?= date('Y-m-d\T23:59:59', strtotime('+3 days')) ?>" oninput="updateTimerEmbedCode()">
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label class="form-label">Fenced Image Embed Code (Copy & Paste)</label>
                <textarea class="form-control" id="timer_embed_code" readonly onclick="this.select()" style="font-family: monospace; font-size: 11px; min-height: 80px; background-color: var(--theme-bg);"></textarea>
                <span style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 6px; display: block;">Paste this block directly into your HTML template editor. The server will dynamically compute the remaining time and draw a live PNG ticker image in the user inbox.</span>
            </div>
        </div>

        <div class="card" style="padding: 24px; min-height: 280px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
                <span class="card-title">Live Rendering Ticker Preview</span>
            </div>
            <div style="flex-grow: 1; display: flex; align-items: center; justify-content: center; background-color: var(--theme-bg); border-radius: 6px; border: 1px solid var(--theme-border); padding: 16px;">
                <img id="timer_preview_img" src="" alt="Live Ticker Countdown Preview" style="max-width: 100%; border-radius: 4px; display: block; border: 1px solid var(--theme-border);">
            </div>
        </div>
    </div>
</div>

<!-- TAB 2: Smart Link Rotators -->
<div id="tab-rotators" class="conversions-tab-content" style="display: none;">
    <div class="grid grid-2" style="align-items: start; gap: 24px;">
        <!-- Configuration Form -->
        <div class="card" style="padding: 24px;">
            <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 12px; margin-bottom: 16px;">
                <span class="card-title">Create Link Rotator</span>
            </div>
            
            <form method="post" action="?action=rotator_create">
                <?= Auth::csrfField() ?>
                <div class="form-group">
                    <label class="form-label" for="name">Friendly Name</label>
                    <input class="form-control" type="text" id="name" name="name" required placeholder="e.g. Summer Promo Rotation">
                </div>

                <div class="form-group">
                    <label class="form-label" for="slug">URL Slug</label>
                    <input class="form-control" type="text" id="slug" name="slug" required placeholder="e.g. promo1" pattern="[a-zA-Z0-9\-_]+">
                    <span style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 4px; display: block;">Only alphanumeric characters, hyphens, and underscores allowed.</span>
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-label" for="destinations">Redirection Destinations (One URL per line)</label>
                    <textarea class="form-control" id="destinations" name="destinations" required style="min-height: 100px; font-family: monospace;" placeholder="https://domain-one.com/promo&#10;https://domain-two.com/promo"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="font-weight: 600; width: 100%; justify-content: center; height: 38px;">
                    Register Rotator →
                </button>
            </form>
        </div>

        <!-- Rotators List -->
        <div class="card" style="padding: 24px;">
            <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 12px; margin-bottom: 16px;">
                <span class="card-title">Registered Cloaked Links</span>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Rotator Details</th>
                            <th>Cloaked Share URL</th>
                            <th style="width: 100px; text-align: center;">Clicks</th>
                            <th style="width: 80px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rotators)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--theme-dark-slate); padding: 40px;">No link rotators defined yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rotators as $r):
                                $shareLink = $baseUrl . '/go?s=' . urlencode($r['slug']);
                                $dests = json_decode($r['destinations'], true) ?: [];
                            ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: var(--theme-dark);"><?= e($r['name']) ?></div>
                                        <div style="font-size: 11px; color: var(--theme-dark-slate); line-height: 1.4; margin-top: 4px;">
                                            <strong>Destinations (<?= count($dests) ?>):</strong><br>
                                            <?php foreach ($dests as $d): ?>
                                                • <span style="font-family: monospace; font-size:10px;"><?= e($d) ?></span><br>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <input class="form-control" type="text" readonly value="<?= e($shareLink) ?>" onclick="this.select()" style="font-family: monospace; font-size: 11px; margin-bottom: 0; background-color: var(--theme-bg); padding: 4px 8px; height: auto;">
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge" style="font-size: 12px; font-weight: 700; background-color: rgba(99, 91, 255, 0.1); color: var(--theme-blurple); padding: 4px 10px; border-radius: 4px;">
                                            <?= (int)$r['clicks'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" action="?action=rotator_delete&id=<?= e($r['id']) ?>" onsubmit="return confirm('Remove this rotator link?');" style="margin: 0;">
                                            <?= Auth::csrfField() ?>
                                            <button type="submit" class="btn btn-danger" style="padding: 4px 8px; font-size: 11px;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- TAB 3: Viral Referral Program -->
<div id="tab-referrals" class="conversions-tab-content" style="display: none;">
    <div class="grid grid-1-3" style="align-items: start; gap: 24px;">
        <!-- Left: Overview Card -->
        <div class="card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
                <span class="card-title">Growth Loop Mechanics</span>
            </div>
            <p style="font-size: 13px; color: var(--theme-dark-slate); line-height: 1.5; margin-bottom: 16px;">
                When active, every contact added to the system automatically gets a unique referral code.
            </p>
            <p style="font-size: 13px; color: var(--theme-dark-slate); line-height: 1.5; margin-bottom: 20px;">
                You can insert the tag <code>{{referral_link}}</code> inside email campaigns. If a recipient forwards their email and a friend subscribes using their customized referral link, the sender's referral stats increment!
            </p>
            <div style="background-color: var(--theme-blurple-light); border: 1px solid rgba(99,91,255,0.1); border-radius: 6px; padding: 12px; font-size: 12px; color: var(--theme-dark-slate); line-height: 1.4;">
                ⚡ <strong>Tip:</strong> Create auto-responder flows that reward referrers when they hit 5, 10, or 25 conversion points using scoring logs.
            </div>
        </div>

        <!-- Right: Leaderboard Table -->
        <div class="card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 16px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">Referral Program Leaderboard</span>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Contact Email</th>
                            <th>Name</th>
                            <th>Referral Code</th>
                            <th style="text-align: right; width: 120px;">Conversions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topReferrers)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--theme-dark-slate);">No referrals recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topReferrers as $ref): ?>
                                <tr>
                                    <td style="font-weight: 600; color: var(--theme-dark);"><?= e($ref['email']) ?></td>
                                    <td><?= e($ref['first_name'] . ' ' . $ref['last_name']) ?: '—' ?></td>
                                    <td><code style="font-family: monospace; font-size:11px; background-color:var(--theme-bg); padding:2px 6px; border-radius:4px;"><?= e($ref['referral_code']) ?></code></td>
                                    <td style="text-align: right; font-weight: 700; color: var(--success);">
                                        <?= (int)$ref['referral_count'] ?> signups
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- TAB 4: Dynamic UTM Builder -->
<div id="tab-utm" class="conversions-tab-content" style="display: none;">
    <div class="grid grid-2" style="gap: 24px; align-items: start;">
        <div class="card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
                <span class="card-title">UTM Campaign URL Generator</span>
            </div>
            <div class="form-group">
                <label class="form-label">Destination Landing Page URL</label>
                <input class="form-control" type="url" id="utm_url" value="https://yourdomain.com/landing" oninput="buildUtmUrl()" placeholder="https://yourdomain.com">
            </div>
            <div class="form-group">
                <label class="form-label">Campaign Source (utm_source)</label>
                <input class="form-control" type="text" id="utm_source" value="email_newsletter" oninput="buildUtmUrl()" placeholder="e.g. google, newsletter, linkedin">
            </div>
            <div class="form-group">
                <label class="form-label">Campaign Medium (utm_medium)</label>
                <input class="form-control" type="text" id="utm_medium" value="email" oninput="buildUtmUrl()" placeholder="e.g. cpc, email, social">
            </div>
            <div class="form-group">
                <label class="form-label">Campaign Name (utm_campaign)</label>
                <input class="form-control" type="text" id="utm_campaign" value="summer_outreach_2026" oninput="buildUtmUrl()" placeholder="e.g. voiceover_promo">
            </div>
        </div>

        <div class="card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
                <span class="card-title">Generated Tracked Campaign URL</span>
            </div>
            <div class="form-group">
                <label class="form-label">Complete Tracked Link</label>
                <textarea class="form-control" id="utm_result" readonly onclick="this.select()" rows="4" style="font-family: monospace; font-size: 12px; background: var(--theme-bg);"></textarea>
            </div>
            <button class="btn btn-primary" onclick="copyUtmLink()" style="width: 100%; justify-content: center; font-weight: 700;">Copy Tracked Link to Clipboard</button>
        </div>
    </div>
</div>

<!-- TAB 5: Web Personalization & Popups -->
<div id="tab-personalization" class="conversions-tab-content" style="display: none;">
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
            <span class="card-title">Exit-Intent Popup & Web Personalization Generator</span>
        </div>
        <p style="font-size: 13px; color: var(--theme-dark-slate); margin-bottom: 20px;">
            Capture leaving website visitors with exit-intent popup widgets configured to collect lead emails directly into your CRM.
        </p>
        <div class="form-group">
            <label class="form-label">Popup Headline</label>
            <input class="form-control" type="text" id="popup_headline" value="Wait! Before You Leave..." oninput="buildPopupSnippet()">
        </div>
        <div class="form-group">
            <label class="form-label">Popup Offer Description</label>
            <input class="form-control" type="text" id="popup_body" value="Get our free Voiceover Casting & Production Checklist instantly." oninput="buildPopupSnippet()">
        </div>
        <div class="form-group">
            <label class="form-label">Embed Script Snippet (Copy to Website Header/Footer)</label>
            <textarea class="form-control" id="popup_snippet" readonly onclick="this.select()" rows="4" style="font-family: monospace; font-size: 11px; background: var(--theme-bg);"></textarea>
        </div>
    </div>
</div>

<script>
    // Tab switching controller
    function switchConversionsTab(event, tabId) {
        const contents = document.querySelectorAll(".conversions-tab-content");
        contents.forEach(c => c.style.display = "none");

        const buttons = document.querySelectorAll(".conversions-tab-btn");
        buttons.forEach(btn => {
            btn.classList.remove("active");
            btn.style.color = "var(--theme-dark-slate)";
            btn.style.borderBottomColor = "transparent";
        });

        document.getElementById(tabId).style.display = "block";
        
        const activeBtn = event.currentTarget;
        activeBtn.classList.add("active");
        activeBtn.style.color = "var(--theme-blurple)";
        activeBtn.style.borderBottomColor = "var(--theme-blurple)";

        const tabParam = tabId.replace('tab-', '');
        const newUrl = new URL(window.location.href);
        newUrl.searchParams.set('tab', tabParam);
        window.history.pushState(null, '', newUrl.toString());
    }

    // Auto-select tab on reload
    window.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab');
        if (tab && document.getElementById('btn-tab-' + tab)) {
            document.getElementById('btn-tab-' + tab).click();
        }
        updateTimerEmbedCode();
        buildUtmUrl();
        buildPopupSnippet();
    });

    function buildUtmUrl() {
        const url = document.getElementById('utm_url').value.trim() || 'https://yourdomain.com';
        const src = encodeURIComponent(document.getElementById('utm_source').value.trim() || 'email');
        const med = encodeURIComponent(document.getElementById('utm_medium').value.trim() || 'email');
        const cmp = encodeURIComponent(document.getElementById('utm_campaign').value.trim() || 'campaign');
        const delim = url.includes('?') ? '&' : '?';
        document.getElementById('utm_result').value = `${url}${delim}utm_source=${src}&utm_medium=${med}&utm_campaign=${cmp}`;
    }

    function copyUtmLink() {
        const el = document.getElementById('utm_result');
        el.select();
        document.execCommand('copy');
        alert('UTM tracked link copied to clipboard!');
    }

    function buildPopupSnippet() {
        const head = document.getElementById('popup_headline').value;
        const body = document.getElementById('popup_body').value;
        const appUrl = '<?= e($baseUrl) ?>';
        document.getElementById('popup_snippet').value = `<script src="${appUrl}/api/popup.js?headline=${encodeURIComponent(head)}&body=${encodeURIComponent(body)}"><\/script>`;
    }

    // --- Dynamic Countdown Timer code calculations ---
    function updateTimerEmbedCode() {
        const input = document.getElementById('timer_end_date');
        const codeBox = document.getElementById('timer_embed_code');
        const preview = document.getElementById('timer_preview_img');

        const endDate = input.value;
        const appUrl = '<?= e($baseUrl) ?>';
        const embedUrl = `${appUrl}/fomo/render?end=${encodeURIComponent(endDate)}`;

        codeBox.value = `<img src="${embedUrl}" alt="Countdown" width="600" style="display:block; max-width:100%; border-radius:8px;">`;
        preview.src = embedUrl;
    }
</script>
