<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Dynamic Web Personalization</h1>
        <p>Integrate your emails with your website. Show customized offers or banners depending on who is visiting.</p>
    </div>
</div>

<div class="grid grid-1-3" style="align-items: start; margin-bottom: 24px;">
    
    <!-- Left Pane: Snippet Code -->
    <div class="card" style="padding: 24px; min-height: 400px; display: flex; flex-direction: column;">
        <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
            <span class="card-title">Tracking Script Snippet</span>
        </div>
        
        <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 16px;">
            Copy and paste this tracking snippet right before the closing <code>&lt;/head&gt;</code> tag on your website.
        </p>

        <textarea class="form-control" rows="8" readonly style="font-family: monospace; font-size: 11px; line-height: 1.5; background: var(--bg-color); cursor: text;" id="snippet_code"><!-- Merlin Personalization Snippet -->
<script>
(function() {
    const params = new URLSearchParams(window.location.search);
    const subId = params.get('sub_id');
    if (!subId) return;
    
    fetch('<?= e($appUrl) ?>/personalization/api?sub_id=' + subId)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.tags.length > 0) {
                window.MerlinVisitorTags = data.tags;
                // Dispatch event so other scripts can act on it
                window.dispatchEvent(new CustomEvent('merlin_ready', { detail: data.tags }));
            }
        });
})();
</script></textarea>
        
        <button class="btn btn-secondary" onclick="copySnippet()" style="margin-top: 16px;">Copy Snippet Code</button>
    </div>

    <!-- Right Pane: Setup Guide -->
    <div class="card" style="padding: 24px; min-height: 400px;">
        <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
            <span class="card-title">How to Personalize Your Website</span>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 16px; font-size: 0.95rem; line-height: 1.6;">
            <div>
                <strong>Step 1: Append Subscriber ID to campaign links</strong>
                <p style="margin: 4px 0 0 0; color: var(--text-muted);">
                    When writing emails, make sure your links have the parameter <code>?sub_id={{subscriber_id}}</code>. Merlin will automatically swap this placeholder.
                </p>
            </div>
            
            <div>
                <strong>Step 2: Add dynamic code on your page</strong>
                <p style="margin: 4px 0 0 0; color: var(--text-muted);">
                    Listen to the <code>merlin_ready</code> event and dynamically show/hide features or alter headline text. Example:
                </p>
                <pre style="background: #0f172a; color: #38bdf8; font-family: monospace; font-size: 11px; padding: 12px; border-radius: 6px; margin-top: 8px; line-height: 1.5; overflow-x: auto;">
window.addEventListener('merlin_ready', function(e) {
    const tags = e.detail;
    if (tags.includes('vip')) {
        document.getElementById('promo-banner').innerText = "Special 50% VIP Discount Just for You!";
        document.getElementById('promo-banner').style.display = 'block';
    }
});</pre>
            </div>
            
            <div style="background: rgba(99,91,255,0.05); border: 1px solid rgba(99,91,255,0.1); padding: 16px; border-radius: 8px;">
                <p style="margin: 0; font-size: 0.85rem; font-weight: 600; color: var(--stripe-blurple);">
                    ℹ️ Merlin Personalization supports cookie caching, ensuring that visitors still see their tailored offers when navigating between multiple pages.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function copySnippet() {
    const el = document.getElementById('snippet_code');
    el.select();
    document.execCommand('copy');
    alert("Snippet copied to clipboard!");
}
</script>
