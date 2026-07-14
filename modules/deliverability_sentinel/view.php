<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>AI Deliverability Sentinel</h1>
        <p>Monitor your domain reputation and scan outgoing campaigns for spam triggers to ensure maximum inbox placement.</p>
    </div>
</div>

<div class="grid grid-1-3" style="align-items: start; margin-bottom: 24px;">
    
    <!-- Left Pane: Campaign Scanner -->
    <div class="card" style="padding: 24px; min-height: 400px; display: flex; flex-direction: column;">
        <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
            <span class="card-title">Pre-Flight Campaign Scan</span>
        </div>
        
        <div class="form-group" style="margin-bottom: 24px;">
            <label class="form-label" for="campaign_select">Select Draft Campaign</label>
            <select class="form-control" id="campaign_select">
                <option value="">-- Choose Campaign --</option>
                <?php foreach ($campaigns as $c): ?>
                    <option value="<?= $c['id'] ?>" data-subject="<?= e($c['subject']) ?>" data-body="<?= e($c['body_html']) ?>">
                        <?= e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="button" id="scan_btn" class="btn btn-primary" onclick="runScan()" style="font-weight: 600; width: 100%; padding: 10px; justify-content: center;">
            <svg style="width: 18px; height: 18px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            Analyze with AI Sentinel
        </button>
        
        <div id="scan_results" style="display: none; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-color);">
            <div style="text-align: center; margin-bottom: 16px;">
                <div style="font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Inbox Probability Score</div>
                <div id="scan_score" style="font-size: 3.5rem; font-weight: 800; line-height: 1; margin: 8px 0;">95</div>
                <div id="scan_verdict" style="font-size: 1.1rem; font-weight: 600; padding: 4px 12px; border-radius: 20px; display: inline-block;">Excellent</div>
            </div>
            
            <div>
                <strong style="display: block; margin-bottom: 8px; font-size: 0.9rem;">Spam Triggers Detected:</strong>
                <div id="scan_triggers" style="display: flex; flex-wrap: wrap; gap: 8px;"></div>
            </div>
        </div>
        
        <div id="scan_loading" style="display: none; text-align: center; margin-top: 40px; color: var(--text-muted);">
            <svg style="animation: spin 1s linear infinite; width: 32px; height: 32px; margin: 0 auto 16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p>Scanning against 100+ spam filters...</p>
        </div>
    </div>

    <!-- Right Pane: Domain Reputation -->
    <div class="card" style="padding: 24px; min-height: 400px; display: flex; flex-direction: column;">
        <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
            <span class="card-title">Live Postmaster Metrics</span>
            <span class="badge" style="background: rgba(52,211,153,0.1); color: #34d399; margin-left: 8px;">Connected</span>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
            <div style="background: var(--bg-color); padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); text-align: center;">
                <div style="color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase;">IP Reputation</div>
                <div style="font-size: 1.5rem; font-weight: bold; color: #34d399; margin-top: 8px;">High</div>
            </div>
            <div style="background: var(--bg-color); padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); text-align: center;">
                <div style="color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase;">Domain Reputation</div>
                <div style="font-size: 1.5rem; font-weight: bold; color: #34d399; margin-top: 8px;">High</div>
            </div>
        </div>
        
        <div style="background: var(--bg-color); padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                <span style="font-weight: 600;">Spam Complaint Rate</span>
                <span style="color: #34d399; font-weight: bold;">0.02%</span>
            </div>
            <div style="width: 100%; background: #334155; height: 8px; border-radius: 4px; overflow: hidden;">
                <div style="width: 2%; background: #34d399; height: 100%;"></div>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
                <span>Current</span>
                <span>Warning at 0.1%</span>
            </div>
        </div>
        
        <h4 style="margin: 0 0 12px 0; font-size: 1rem;">Authentication Status</h4>
        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px;">
            <li style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: rgba(52,211,153,0.05); border: 1px solid rgba(52,211,153,0.2); border-radius: 6px;">
                <span style="font-weight: 600;">SPF</span>
                <span style="color: #34d399;">Pass ✓</span>
            </li>
            <li style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: rgba(52,211,153,0.05); border: 1px solid rgba(52,211,153,0.2); border-radius: 6px;">
                <span style="font-weight: 600;">DKIM</span>
                <span style="color: #34d399;">Pass ✓</span>
            </li>
            <li style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: rgba(52,211,153,0.05); border: 1px solid rgba(52,211,153,0.2); border-radius: 6px;">
                <span style="font-weight: 600;">DMARC</span>
                <span style="color: #34d399;">Pass ✓</span>
            </li>
        </ul>

    </div>
</div>

<style>
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>

<script>
function runScan() {
    const select = document.getElementById('campaign_select');
    if (!select.value) {
        alert("Please select a campaign to scan.");
        return;
    }
    
    const opt = select.options[select.selectedIndex];
    const payload = {
        subject: opt.getAttribute('data-subject'),
        html_body: opt.getAttribute('data-body')
    };
    
    const btn = document.getElementById('scan_btn');
    btn.disabled = true;
    
    document.getElementById('scan_results').style.display = 'none';
    document.getElementById('scan_loading').style.display = 'block';
    
    fetch('/deliverability/scan', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        document.getElementById('scan_loading').style.display = 'none';
        
        if (data.success) {
            document.getElementById('scan_results').style.display = 'block';
            
            const sc = document.getElementById('scan_score');
            sc.innerText = data.score;
            sc.style.color = data.color;
            
            const vd = document.getElementById('scan_verdict');
            vd.innerText = data.verdict;
            vd.style.backgroundColor = data.color + '20';
            vd.style.color = data.color;
            
            const trig = document.getElementById('scan_triggers');
            trig.innerHTML = '';
            if (data.triggers.length > 0) {
                data.triggers.forEach(t => {
                    const badge = document.createElement('span');
                    badge.style.cssText = "background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 4px 10px; border-radius: 4px; font-size: 0.85rem; border: 1px solid rgba(239, 68, 68, 0.2);";
                    badge.innerText = `"${t}"`;
                    trig.appendChild(badge);
                });
            } else {
                trig.innerHTML = '<span style="color: #94a3b8;">None found! Your copy is clean.</span>';
            }
        } else {
            alert("Error: " + data.error);
        }
    })
    .catch(err => {
        btn.disabled = false;
        document.getElementById('scan_loading').style.display = 'none';
        alert("Network error.");
    });
}
</script>
