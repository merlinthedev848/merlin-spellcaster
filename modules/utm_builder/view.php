<?php
declare(strict_types=1);
?>

<div class="header-actions" style="margin-bottom: 20px;">
    <div class="page-title">
        <h1>UTM Campaign Link Builder</h1>
        <p>Construct tracked URLs for your marketing campaigns to measure marketing performance inside Google Analytics.</p>
    </div>
</div>

<div class="grid grid-2" style="align-items: start; gap: 24px; margin-bottom: 24px;">
    <!-- Build Form -->
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--theme-border);">
            <span class="card-title">Link Configuration</span>
        </div>
        <form method="post" action="">
            <?= Auth::csrfField() ?>
            
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" for="original_url">Destination URL <span style="color:var(--danger)">*</span></label>
                <input class="form-control" type="url" id="original_url" name="original_url" required placeholder="https://example.com/landing-page" oninput="updateLiveUrl()">
            </div>

            <div class="grid grid-2" style="gap: 16px; margin-bottom: 16px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="utm_source">Campaign Source <span style="color:var(--danger)">*</span></label>
                    <input class="form-control" type="text" id="utm_source" name="utm_source" required placeholder="e.g. newsletter, google" oninput="updateLiveUrl()">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="utm_medium">Campaign Medium <span style="color:var(--danger)">*</span></label>
                    <input class="form-control" type="text" id="utm_medium" name="utm_medium" required placeholder="e.g. email, cpc, banner" oninput="updateLiveUrl()">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" for="utm_campaign">Campaign Name <span style="color:var(--danger)">*</span></label>
                <input class="form-control" type="text" id="utm_campaign" name="utm_campaign" required placeholder="e.g. promo_july, black_friday" oninput="updateLiveUrl()">
            </div>

            <div class="grid grid-2" style="gap: 16px; margin-bottom: 20px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="utm_term">Campaign Term <span style="font-size:11px; color:var(--theme-dark-slate)">(Optional)</span></label>
                    <input class="form-control" type="text" id="utm_term" name="utm_term" placeholder="e.g. email_marketing" oninput="updateLiveUrl()">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="utm_content">Campaign Content <span style="font-size:11px; color:var(--theme-dark-slate)">(Optional)</span></label>
                    <input class="form-control" type="text" id="utm_content" name="utm_content" placeholder="e.g. logo_link, text_link" oninput="updateLiveUrl()">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 42px; font-weight: 700;">
                Generate & Save Tracking Link
            </button>
        </form>
    </div>

    <!-- Live Preview & Presets -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Live URL preview box -->
        <div class="card" style="padding: 24px; background-color: var(--theme-blurple-light); border: 1px solid rgba(99,91,255,0.15);">
            <div class="card-header" style="margin-bottom: 12px; padding-bottom: 0; border-bottom: none;">
                <span class="card-title" style="color: var(--theme-blurple);">Live URL Output Preview</span>
            </div>
            <p style="font-size: 12px; color: var(--theme-dark-slate); margin-bottom: 14px; line-height: 1.45;">This is the final tracking URL you will paste into your email template links:</p>
            <div style="background-color: var(--theme-white); border: 1px solid var(--theme-border); border-radius: 6px; padding: 12px; font-family: monospace; font-size: 11px; word-break: break-all; min-height: 50px; font-weight: 600; color: var(--theme-dark);" id="live_url_box">
                [ Enter details to generate link ]
            </div>
        </div>

        <!-- Presets card -->
        <div class="card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 16px; padding-bottom: 0; border-bottom: none;">
                <span class="card-title">Quick Channel Presets</span>
            </div>
            <p style="font-size: 12px; color: var(--theme-dark-slate); margin-bottom: 16px;">Quickly populate Source and Medium parameters for standard channels:</p>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="applyPreset('newsletter', 'email')" style="font-size:12px; padding: 6px 12px;">📧 Email Newsletter</button>
                <button type="button" class="btn btn-secondary" onclick="applyPreset('google', 'cpc')" style="font-size:12px; padding: 6px 12px;">🔍 Google CPC Ad</button>
                <button type="button" class="btn btn-secondary" onclick="applyPreset('facebook', 'social')" style="font-size:12px; padding: 6px 12px;">👥 Facebook Social</button>
                <button type="button" class="btn btn-secondary" onclick="applyPreset('twitter', 'social')" style="font-size:12px; padding: 6px 12px;">🐦 Twitter Post</button>
            </div>
        </div>
    </div>
</div>

<!-- Saved links table -->
<div class="card" style="padding: 24px;">
    <div class="card-header" style="margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--theme-border);">
        <span class="card-title">Saved Tracking Campaigns</span>
    </div>
    
    <div class="table-wrapper" style="border: none; box-shadow: none;">
        <table style="width: 100%;">
            <thead>
                <tr>
                    <th>Campaign Info</th>
                    <th>UTM Parameters</th>
                    <th>Final Tracked Destination URL</th>
                    <th style="width: 140px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($savedLinks)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--theme-dark-slate); padding: 40px;">No saved tracking links found. Generate one above.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($savedLinks as $link): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--theme-dark);"><?= e($link['utm_campaign']) ?></div>
                                <div style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 4px;"><?= date('M j, Y H:i', strtotime($link['created_at'])) ?></div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <span style="font-size: 11px; color: var(--theme-dark-slate);">Source: <strong style="color:var(--theme-blurple)"><?= e($link['utm_source']) ?></strong></span>
                                    <span style="font-size: 11px; color: var(--theme-dark-slate);">Medium: <strong style="color:var(--theme-blurple)"><?= e($link['utm_medium']) ?></strong></span>
                                    <?php if (!empty($link['utm_term'])): ?>
                                        <span style="font-size: 11px; color: var(--theme-dark-slate);">Term: <strong><?= e($link['utm_term']) ?></strong></span>
                                    <?php endif; ?>
                                    <?php if (!empty($link['utm_content'])): ?>
                                        <span style="font-size: 11px; color: var(--theme-dark-slate);">Content: <strong><?= e($link['utm_content']) ?></strong></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="max-width: 320px;">
                                <div style="font-family: monospace; font-size: 11px; background-color: var(--theme-bg); padding: 6px 10px; border-radius: 4px; border: 1px solid var(--theme-border); word-break: break-all; color: var(--theme-dark); font-weight: 600;" id="copy_target_<?= $link['id'] ?>">
                                    <?= e($link['final_url']) ?>
                                </div>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <button type="button" class="btn btn-secondary" onclick="copyLink(<?= $link['id'] ?>, this)" style="padding: 6px 12px; font-size: 12px; font-weight: 600;">
                                    Copy Link
                                </button>
                                <form method="post" action="?action=delete" style="margin: 0; display: inline-block; margin-left: 4px;" onsubmit="return confirm('Are you sure you want to remove this saved link?');">
                                    <?= Auth::csrfField() ?>
                                    <input type="hidden" name="delete_id" value="<?= $link['id'] ?>">
                                    <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function applyPreset(source, medium) {
    document.getElementById('utm_source').value = source;
    document.getElementById('utm_medium').value = medium;
    updateLiveUrl();
}

function updateLiveUrl() {
    const orig = document.getElementById('original_url').value.trim();
    const source = document.getElementById('utm_source').value.trim();
    const medium = document.getElementById('utm_medium').value.trim();
    const campaign = document.getElementById('utm_campaign').value.trim();
    const term = document.getElementById('utm_term').value.trim();
    const content = document.getElementById('utm_content').value.trim();

    const previewBox = document.getElementById('live_url_box');

    if (!orig) {
        previewBox.innerText = '[ Enter details to generate link ]';
        return;
    }

    try {
        let url;
        if (!orig.startsWith('http://') && !orig.startsWith('https://')) {
            url = new URL('http://' + orig);
        } else {
            url = new URL(orig);
        }

        if (source) url.searchParams.set('utm_source', source);
        if (medium) url.searchParams.set('utm_medium', medium);
        if (campaign) url.searchParams.set('utm_campaign', campaign);
        if (term) url.searchParams.set('utm_term', term);
        if (content) url.searchParams.set('utm_content', content);

        let finalUrlStr = url.toString();
        // If we added http:// prefix dummy, strip it back
        if (!orig.startsWith('http://') && !orig.startsWith('https://') && finalUrlStr.startsWith('http://')) {
            finalUrlStr = finalUrlStr.substring(7);
        }
        
        previewBox.innerText = finalUrlStr;
    } catch (e) {
        previewBox.innerText = 'Invalid Destination URL format';
    }
}

function copyLink(id, btn) {
    const text = document.getElementById('copy_target_' + id).innerText.trim();
    navigator.clipboard.writeText(text).then(() => {
        const origText = btn.innerText;
        btn.innerText = 'Copied! ✓';
        btn.style.backgroundColor = 'var(--success)';
        btn.style.color = 'var(--theme-white)';
        setTimeout(() => {
            btn.innerText = origText;
            btn.style.backgroundColor = '';
            btn.style.color = '';
        }, 1500);
    });
}
</script>
