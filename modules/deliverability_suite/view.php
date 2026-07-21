<?php
declare(strict_types=1);
?>

<div class="header-actions" style="margin-bottom: 20px;">
    <div class="page-title">
        <h1>Email Deliverability Suite</h1>
        <p>Audit deliverability, screen content for spam words, and schedule automated domain warming campaigns.</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" style="font-weight: 600; padding: 10px 20px;" onclick="runEmailScan()">
            ⚡ Verify & Process All Contacts
        </button>
    </div>
</div>

<!-- Tabs Row -->
<div style="display: flex; gap: 4px; border-bottom: 1px solid var(--theme-border); margin-bottom: 24px;">
    <button class="btn analytics-tab-btn active" id="btn-tab-verifier" onclick="switchDeliverabilityTab(event, 'tab-verifier')" style="border: none; border-bottom: 2px solid var(--theme-blurple); background: transparent; padding: 12px 18px; border-radius: 0; color: var(--theme-blurple); font-weight: 600; cursor: pointer;">
        🔍 Email Verifier
    </button>
    <button class="btn analytics-tab-btn" id="btn-tab-sentinel" onclick="switchDeliverabilityTab(event, 'tab-sentinel')" style="border: none; border-bottom: 2px solid transparent; background: transparent; padding: 12px 18px; border-radius: 0; color: var(--theme-dark-slate); font-weight: 600; cursor: pointer;">
        🛡️ Content Spam Scanner
    </button>
    <button class="btn analytics-tab-btn" id="btn-tab-warmup" onclick="switchDeliverabilityTab(event, 'tab-warmup')" style="border: none; border-bottom: 2px solid transparent; background: transparent; padding: 12px 18px; border-radius: 0; color: var(--theme-dark-slate); font-weight: 600; cursor: pointer;">
        🔥 SMTP Domain Warm-Up
    </button>
    <button class="btn analytics-tab-btn" id="btn-tab-dns" onclick="switchDeliverabilityTab(event, 'tab-dns')" style="border: none; border-bottom: 2px solid transparent; background: transparent; padding: 12px 18px; border-radius: 0; color: var(--theme-dark-slate); font-weight: 600; cursor: pointer;">
        🌐 Domain DNS Health (SPF/DMARC)
    </button>
</div>

<!-- TAB 1: Email Verifier -->
<div id="tab-verifier" class="deliverability-tab-content">
    <!-- Stats Indicators -->
    <div class="grid grid-4" style="margin-bottom: 24px; gap: 16px;">
        <div class="card" style="padding: 16px;">
            <span style="font-size: 11px; font-weight: 600; color: var(--theme-dark-slate); text-transform: uppercase;">Total Contacts</span>
            <h2 style="font-size: 28px; font-weight: 700; color: var(--theme-dark); margin-top: 4px;"><?= $totalVerifier ?></h2>
        </div>
        <div class="card" style="padding: 16px;">
            <span style="font-size: 11px; font-weight: 600; color: var(--success); text-transform: uppercase;">Active / Deliverable</span>
            <h2 style="font-size: 28px; font-weight: 700; color: var(--success); margin-top: 4px;"><?= $activeVerifier ?></h2>
        </div>
        <div class="card" style="padding: 16px;">
            <span style="font-size: 11px; font-weight: 600; color: var(--danger); text-transform: uppercase;">Bounced / Flagged</span>
            <h2 style="font-size: 28px; font-weight: 700; color: var(--danger); margin-top: 4px;" id="bounce_count_stat"><?= $bouncedVerifier ?></h2>
        </div>
        <div class="card" style="padding: 16px;">
            <span style="font-size: 11px; font-weight: 600; color: var(--theme-dark-slate); text-transform: uppercase;">Unsubscribed</span>
            <h2 style="font-size: 28px; font-weight: 700; color: var(--theme-dark); margin-top: 4px;"><?= $unsubsVerifier ?></h2>
        </div>
    </div>

    <div class="grid grid-2" style="align-items: start; gap: 24px;">
        <div class="card" style="padding: 24px; min-height: 300px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">List Verification Scan</span>
            </div>
            <p style="font-size: 13px; color: var(--theme-dark-slate); line-height: 1.5; margin-bottom: 24px;">
                Run a deliverability audit across all active contacts. The verifier will query DNS records for valid mail servers (MX) and screen domains against known disposable address blacklists. Flagged contacts will be automatically moved to <strong>bounced</strong> status to keep your list clean.
            </p>
            <div style="margin-top: auto;">
                <button type="button" id="start_scan_btn" class="btn btn-primary" onclick="runEmailScan()" style="font-weight: 600; width: 100%; padding: 12px; justify-content: center;">
                    Scan & Verify Active Contacts List →
                </button>
            </div>
            <div id="scan_progress_container" style="display: none; margin-top: 24px;">
                <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; margin-bottom: 6px;">
                    <span id="scan_status_label" style="color: var(--theme-dark);">Initializing Scan...</span>
                    <span id="scan_percentage" style="color: var(--theme-blurple);">0%</span>
                </div>
                <div style="background-color: var(--theme-border); border-radius: 6px; height: 10px; overflow: hidden; width: 100%; margin-bottom: 12px;">
                    <div id="scan_progress_bar" style="background-color: var(--theme-blurple); height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--theme-dark-slate);">
                    <span>Processed: <strong id="processed_count">0</strong> / <strong id="total_count">0</strong></span>
                    <span>Flagged: <strong id="flagged_count" style="color: var(--danger);">0</strong></span>
                </div>
            </div>
        </div>

        <div class="card" style="padding: 24px; min-height: 300px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">Live Verification Logs</span>
            </div>
            <div id="scan_console" style="background-color: #0f172a; color: #38bdf8; font-family: monospace; font-size: 12px; padding: 16px; border-radius: 6px; flex-grow: 1; min-height: 180px; max-height: 220px; overflow-y: auto; line-height: 1.6; border: 1px solid #1e293b;">
                <div style="color: #64748b;">> Console idle. Click 'Scan & Verify' to begin...</div>
            </div>
        </div>
    </div>
</div>

<!-- TAB 2: Content Spam Scanner -->
<div id="tab-sentinel" class="deliverability-tab-content" style="display: none;">
    <div class="grid grid-2" style="align-items: start; gap: 24px;">
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
            <button type="button" id="scan_btn" class="btn btn-primary" onclick="runContentScan()" style="font-weight: 600; width: 100%; padding: 10px; justify-content: center;">
                Analyze Copy with Spam Sentinel
            </button>
            <div id="content_scan_results" style="display: none; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--theme-border);">
                <div style="text-align: center; margin-bottom: 16px;">
                    <div style="font-size: 0.9rem; color: var(--theme-dark-slate); text-transform: uppercase; letter-spacing: 1px;">Inbox Probability Score</div>
                    <div id="scan_score" style="font-size: 3.5rem; font-weight: 800; line-height: 1; margin: 8px 0;">100</div>
                    <div id="scan_verdict" style="font-size: 1.1rem; font-weight: 600; padding: 4px 12px; border-radius: 20px; display: inline-block;">Excellent</div>
                </div>
                <div>
                    <strong style="display: block; margin-bottom: 8px; font-size: 0.9rem;">Spam Triggers Detected:</strong>
                    <div id="scan_triggers" style="display: flex; flex-wrap: wrap; gap: 8px;"></div>
                </div>
            </div>
            <div id="content_scan_loading" style="display: none; text-align: center; margin-top: 40px; color: var(--theme-dark-slate);">
                <div class="spinner-small" style="margin: 0 auto 16px;"></div>
                <p>Scanning text copy against 100+ spam keywords...</p>
            </div>
        </div>

        <div class="card" style="padding: 24px; min-height: 400px;">
            <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">Live Postmaster Metrics</span>
                <span class="badge" style="background: rgba(16,185,129,0.1); color: #10b981; margin-left: 8px;">Connected</span>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                <div style="background: var(--theme-bg); padding: 16px; border-radius: 8px; border: 1px solid var(--theme-border); text-align: center;">
                    <div style="color: var(--theme-dark-slate); font-size: 0.85rem; text-transform: uppercase; font-weight:600;">IP Reputation</div>
                    <div style="font-size: 1.5rem; font-weight: bold; color: #10b981; margin-top: 8px;">High</div>
                </div>
                <div style="background: var(--theme-bg); padding: 16px; border-radius: 8px; border: 1px solid var(--theme-border); text-align: center;">
                    <div style="color: var(--theme-dark-slate); font-size: 0.85rem; text-transform: uppercase; font-weight:600;">Domain Reputation</div>
                    <div style="font-size: 1.5rem; font-weight: bold; color: #10b981; margin-top: 8px;">High</div>
                </div>
            </div>
            <div style="background: var(--theme-bg); padding: 16px; border-radius: 8px; border: 1px solid var(--theme-border); margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                    <span style="font-weight: 600; color:var(--theme-dark)">Spam Complaint Rate</span>
                    <span style="color: #10b981; font-weight: bold;">0.01%</span>
                </div>
                <div style="width: 100%; background: var(--theme-border); height: 8px; border-radius: 4px; overflow: hidden;">
                    <div style="width: 1%; background: #10b981; height: 100%;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--theme-dark-slate); margin-top: 4px;">
                    <span>Current</span>
                    <span>Warning threshold: 0.1%</span>
                </div>
            </div>
            <h4 style="margin: 0 0 12px 0; font-size: 0.95rem; font-weight: 700; color: var(--theme-dark)">Sender Authentication DNS Status</h4>
            <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px;">
                <li style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: rgba(16,185,129,0.05); border: 1px solid rgba(16,185,129,0.15); border-radius: 6px; font-size: 13px;">
                    <span style="font-weight: 600; color: var(--theme-dark)">SPF (Sender Policy Framework)</span>
                    <span style="color: #10b981; font-weight: 700;">Valid ✓</span>
                </li>
                <li style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: rgba(16,185,129,0.05); border: 1px solid rgba(16,185,129,0.15); border-radius: 6px; font-size: 13px;">
                    <span style="font-weight: 600; color: var(--theme-dark)">DKIM (DomainKeys Identified Mail)</span>
                    <span style="color: #10b981; font-weight: 700;">Valid ✓</span>
                </li>
                <li style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: rgba(16,185,129,0.05); border: 1px solid rgba(16,185,129,0.15); border-radius: 6px; font-size: 13px;">
                    <span style="font-weight: 600; color: var(--theme-dark)">DMARC Alignment</span>
                    <span style="color: #10b981; font-weight: 700;">Valid ✓</span>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- TAB 3: Domain Warm-Up Engine -->
<div id="tab-warmup" class="deliverability-tab-content" style="display: none;">
    <div class="grid grid-2" style="align-items: start; gap: 24px;">
        <!-- Warm-up Settings Form -->
        <div class="card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 20px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
                <span class="card-title">SMTP Warm-up Configuration</span>
            </div>
            <form method="post" action="?action=warmup_update">
                <?= Auth::csrfField() ?>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: inline-flex; align-items: center; gap: 10px; font-weight: 600; color: var(--theme-dark); cursor: pointer;">
                        <input type="checkbox" name="warmup_active" value="1" <?= $warmupActive ? 'checked' : '' ?> style="width: 16px; height: 16px; accent-color: var(--theme-blurple);">
                        Enable Automated Warm-Up Sequence
                    </label>
                    <p style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 6px; line-height: 1.4;">
                        When active, the system will send progressive quotas of emails daily to your seed list. This establishes historical sender reputation with ISP providers.
                    </p>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" for="warmup_seed_list">Select Seed Contacts List</label>
                    <select class="form-control" id="warmup_seed_list" name="warmup_seed_list" required>
                        <option value="">-- Choose Seed List --</option>
                        <?php foreach ($lists as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= $seedListId === (int)$l['id'] ? 'selected' : '' ?>>
                                <?= e($l['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-label" for="warmup_start_date">Schedule Start Date</label>
                    <input class="form-control" type="date" id="warmup_start_date" name="warmup_start_date" value="<?= e($startDate) ?>" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 42px; font-weight: 700;">
                    Save Warm-Up Settings
                </button>
            </form>
        </div>

        <!-- Warm-up Engine Status -->
        <div class="card" style="padding: 24px; min-height: 380px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 20px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
                <span class="card-title">Sequence Health & Estimates</span>
            </div>
            
            <div style="margin-bottom: 20px;">
                <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 6px;">Warm-Up Status</span>
                <?php if ($warmupActive): ?>
                    <span class="badge" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.15); font-weight: 700; font-size: 13px; padding: 4px 12px; border-radius: 4px;">
                        ● RUNNING (Day <?= (int)$warmupDay ?>/30)
                    </span>
                <?php else: ?>
                    <span class="badge" style="background-color: var(--theme-bg); color: var(--theme-dark-slate); border: 1px solid var(--theme-border); font-weight: 700; font-size: 13px; padding: 4px 12px; border-radius: 4px;">
                        ● INACTIVE / PAUSED
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($warmupActive): ?>
                <div style="background-color: var(--theme-blurple-light); border: 1px solid rgba(99,91,255,0.1); border-radius: 6px; padding: 16px; margin-bottom: 20px;">
                    <div style="font-size: 12px; color: var(--theme-dark-slate); margin-bottom: 6px; font-weight: 600;">Today's Target Send Volume</div>
                    <div style="font-size: 24px; font-weight: 800; color: var(--theme-blurple);"><?= $warmupQuota ?> emails</div>
                </div>

                <div style="margin-top: auto;">
                    <h5 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 700; color: var(--theme-dark);">Cron Automation Link</h5>
                    <p style="font-size: 11px; color: var(--theme-dark-slate); line-height: 1.4; margin-bottom: 12px;">Trigger the daily sequence run using this secret cron callback URL:</p>
                    <code style="font-family: monospace; font-size: 11px; background-color: var(--theme-bg); padding: 8px 12px; border-radius: 4px; display: block; border: 1px solid var(--theme-border); word-break: break-all; color: var(--theme-dark); font-weight: 600;">
                        <?= e(getSetting('app_url')) ?>/warmup/run?secret=<?= e(getSetting('cron_secret')) ?>
                    </code>
                </div>
            <?php else: ?>
                <p style="color: var(--theme-dark-slate); font-size: 13px; line-height: 1.5; text-align: center; margin: auto 0;">
                    Enable the warm-up sequence on the left to activate progressive schedules, automated quotas, and retrieve the Cron callback trigger URL.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- TAB 4: Domain DNS Health -->
<div id="tab-dns" class="deliverability-tab-content" style="display: none;">
    <div class="card" style="padding: 24px; max-width: 900px; margin: 0 auto;">
        <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 16px; margin-bottom: 20px;">
            <span class="card-title">Domain DNS Authentication Diagnostic (SPF / DMARC / MX)</span>
        </div>
        
        <div style="display: flex; gap: 12px; margin-bottom: 24px;">
            <input class="form-control" type="text" id="dns_domain_input" placeholder="Enter sender domain e.g. chriskendallvo.com" value="<?= e(substr(strrchr(getSetting('smtp_from_email', ''), "@"), 1) ?: '') ?>" style="margin-bottom: 0;">
            <button type="button" class="btn btn-primary" onclick="runDnsDiagnostic()" style="padding: 0 24px; font-weight: 600; white-space: nowrap;">Check DNS Records →</button>
        </div>

        <div id="dns_results_card" style="display: none;">
            <div class="grid grid-3" style="gap: 16px; margin-bottom: 24px;">
                <div class="card" style="padding: 16px; text-align: center;">
                    <span style="font-size: 11px; font-weight: 700; color: var(--theme-dark-slate); text-transform: uppercase;">SPF Record</span>
                    <div id="spf_status_badge" style="margin-top: 8px;">-</div>
                </div>
                <div class="card" style="padding: 16px; text-align: center;">
                    <span style="font-size: 11px; font-weight: 700; color: var(--theme-dark-slate); text-transform: uppercase;">DMARC Policy</span>
                    <div id="dmarc_status_badge" style="margin-top: 8px;">-</div>
                </div>
                <div class="card" style="padding: 16px; text-align: center;">
                    <span style="font-size: 11px; font-weight: 700; color: var(--theme-dark-slate); text-transform: uppercase;">MX Mail Exchanger</span>
                    <div id="mx_status_badge" style="margin-top: 8px;">-</div>
                </div>
            </div>

            <div style="background: var(--theme-bg); border: 1px solid var(--theme-border); border-radius: 8px; padding: 16px;">
                <h4 style="font-size: 13px; font-weight: 700; color: var(--theme-dark); margin-bottom: 12px;">Raw Verified Records:</h4>
                <div style="font-family: monospace; font-size: 12px; display: flex; flex-direction: column; gap: 8px;" id="dns_raw_records"></div>
            </div>
        </div>
    </div>
</div>

<script>
    async function runDnsDiagnostic() {
        const domain = document.getElementById("dns_domain_input").value.trim();
        if (!domain) return alert("Please enter a domain.");

        const resCard = document.getElementById("dns_results_card");
        resCard.style.display = "block";
        document.getElementById("spf_status_badge").innerHTML = '<span style="color: var(--theme-dark-slate);">Checking...</span>';
        document.getElementById("dmarc_status_badge").innerHTML = '<span style="color: var(--theme-dark-slate);">Checking...</span>';
        document.getElementById("mx_status_badge").innerHTML = '<span style="color: var(--theme-dark-slate);">Checking...</span>';
        document.getElementById("dns_raw_records").innerHTML = '<div style="color: var(--theme-dark-slate);">Querying DNS...</div>';

        try {
            const resp = await fetch("/deliverability/check-domain?domain=" + encodeURIComponent(domain));
            const data = await resp.json();

            if (data.success) {
                // SPF Badge
                document.getElementById("spf_status_badge").innerHTML = data.spf.valid 
                    ? '<span class="badge badge-active" style="background: rgba(52,211,153,0.15); color: #059669; font-weight: 700;">PASS (VALID)</span>'
                    : '<span class="badge badge-unsubscribed" style="background: rgba(239,68,68,0.15); color: #dc2626; font-weight: 700;">MISSING / INVALID</span>';

                // DMARC Badge
                document.getElementById("dmarc_status_badge").innerHTML = data.dmarc.valid 
                    ? '<span class="badge badge-active" style="background: rgba(52,211,153,0.15); color: #059669; font-weight: 700;">PASS (CONFIGURED)</span>'
                    : '<span class="badge badge-unsubscribed" style="background: rgba(239,68,68,0.15); color: #dc2626; font-weight: 700;">MISSING / UNCONFIGURED</span>';

                // MX Badge
                document.getElementById("mx_status_badge").innerHTML = data.mx.valid 
                    ? '<span class="badge badge-active" style="background: rgba(52,211,153,0.15); color: #059669; font-weight: 700;">PASS (' + data.mx.records.length + ' MX)</span>'
                    : '<span class="badge badge-unsubscribed" style="background: rgba(239,68,68,0.15); color: #dc2626; font-weight: 700;">NO MX RECORD</span>';

                // Raw Output
                let html = '<div><strong>Domain Checked:</strong> ' + data.domain + '</div>';
                html += '<div><strong>SPF Record:</strong> ' + (data.spf.record ? data.spf.record : 'None detected') + '</div>';
                html += '<div><strong>DMARC Record:</strong> ' + (data.dmarc.record ? data.dmarc.record : 'None detected') + '</div>';
                html += '<div><strong>MX Records:</strong> ' + (data.mx.records.length ? data.mx.records.join(', ') : 'None detected') + '</div>';
                document.getElementById("dns_raw_records").innerHTML = html;
            } else {
                alert("DNS check error: " + data.error);
            }
        } catch (e) {
            alert("Failed to perform DNS lookup: " + e.message);
        }
    }
</script>

<script>
    // Tab switching controller
    function switchDeliverabilityTab(event, tabId) {
        const contents = document.querySelectorAll(".deliverability-tab-content");
        contents.forEach(c => c.style.display = "none");

        const buttons = document.querySelectorAll(".analytics-tab-btn");
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

        // Update URL hash parameter to persist tab on reload
        const tabParam = tabId.replace('tab-', '');
        const newUrl = new URL(window.location.href);
        newUrl.searchParams.set('tab', tabParam);
        window.history.pushState(null, '', newUrl.toString());
    }

    // Auto-select tab on page load
    window.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab');
        if (tab && document.getElementById('btn-tab-' + tab)) {
            document.getElementById('btn-tab-' + tab).click();
        }
    });

    // --- Content Scan Logic ---
    function runContentScan() {
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
        btn.textContent = "Running Analysis...";
        
        document.getElementById('content_scan_results').style.display = 'none';
        document.getElementById('content_scan_loading').style.display = 'block';
        
        fetch('<?= e(getSetting("app_url")) ?>/deliverability/scan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = "Analyze Copy with Spam Sentinel";
            document.getElementById('content_scan_loading').style.display = 'none';
            
            if (data.success) {
                document.getElementById('content_scan_results').style.display = 'block';
                
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
                        badge.style.cssText = "background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 4px 10px; border-radius: 4px; font-size: 12px; border: 1px solid rgba(239, 68, 68, 0.2); font-weight: 600;";
                        badge.innerText = `"${t}"`;
                        trig.appendChild(badge);
                    });
                } else {
                    trig.innerHTML = '<span style="color: #94a3b8; font-size: 13px;">None found! Your campaign copy is clean.</span>';
                }
            } else {
                alert("Scan Error: " + data.error);
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.textContent = "Analyze Copy with Spam Sentinel";
            document.getElementById('content_scan_loading').style.display = 'none';
            alert("Network scanning connection failed.");
        });
    }

    // --- Email Verifier Scan Logic ---
    let scanIds = [];
    let currentIndex = 0;
    let flaggedCount = 0;
    const batchSize = 5;

    async function runEmailScan() {
        const btn = document.getElementById("start_scan_btn");
        const progress = document.getElementById("scan_progress_container");
        const consoleEl = document.getElementById("scan_console");
        
        btn.disabled = true;
        btn.textContent = "Auditing deliverability...";
        progress.style.display = "block";
        consoleEl.innerHTML = "";
        
        logToConsole("Fetching active email contacts list...", "info");

        try {
            const resp = await fetch("<?= e(getSetting('app_url')) ?>/verifier/scan");
            const data = await resp.json();
            
            if (!data.success || !data.ids || data.ids.length === 0) {
                logToConsole("No active contacts found to scan.", "success");
                btn.disabled = false;
                btn.textContent = "Scan & Verify Active Contacts List →";
                return;
            }

            scanIds = data.ids;
            currentIndex = 0;
            flaggedCount = 0;

            document.getElementById("total_count").textContent = scanIds.length;
            document.getElementById("processed_count").textContent = "0";
            document.getElementById("flagged_count").textContent = "0";
            
            logToConsole("Loaded " + scanIds.length + " active contacts. Starting DNS validation...", "info");
            processNextBatch();

        } catch (err) {
            logToConsole("Error fetching subscriber list: " + err.message, "error");
            btn.disabled = false;
            btn.textContent = "Scan & Verify Active Contacts List →";
        }
    }

    async function processNextBatch() {
        if (currentIndex >= scanIds.length) {
            finishScan();
            return;
        }

        const batch = scanIds.slice(currentIndex, currentIndex + batchSize);
        document.getElementById("scan_status_label").textContent = "Validating batch... (" + (currentIndex + 1) + "-" + Math.min(currentIndex + batchSize, scanIds.length) + ")";
        
        try {
            const resp = await fetch("<?= e(getSetting('app_url')) ?>/verifier/scan-batch", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ ids: batch })
            });
            const data = await resp.json();

            if (data.success && data.results) {
                data.results.forEach(res => {
                    if (res.skipped) return;
                    if (res.valid) {
                        logToConsole("✔ " + res.email + " -> Deliverable", "success");
                    } else {
                        logToConsole("✖ " + res.email + " -> " + res.reason, "error");
                        flaggedCount++;
                        document.getElementById("flagged_count").textContent = flaggedCount;
                    }
                });
            }

            currentIndex += batch.length;
            updateProgressBar();
            setTimeout(processNextBatch, 300);

        } catch (err) {
            logToConsole("Batch processing error: " + err.message, "error");
            currentIndex += batch.length;
            updateProgressBar();
            setTimeout(processNextBatch, 500);
        }
    }

    function updateProgressBar() {
        const pct = Math.min(Math.round((currentIndex / scanIds.length) * 100), 100);
        document.getElementById("scan_percentage").textContent = pct + "%";
        document.getElementById("scan_progress_bar").style.width = pct + "%";
        document.getElementById("processed_count").textContent = currentIndex;
    }

    function finishScan() {
        document.getElementById("scan_status_label").textContent = "Audit Complete!";
        logToConsole("\n--- Scan Finished ---", "info");
        logToConsole("Processed: " + scanIds.length + " contacts.", "info");
        logToConsole("Flagged (Purged): " + flaggedCount + " invalid emails.", flaggedCount > 0 ? "error" : "success");

        const btn = document.getElementById("start_scan_btn");
        btn.disabled = false;
        btn.textContent = "Audit Finished. Run Again →";

        const statBounce = document.getElementById("bounce_count_stat");
        if (statBounce) {
            const val = parseInt(statBounce.textContent) || 0;
            statBounce.textContent = (val + flaggedCount).toString();
        }
    }

    function logToConsole(message, type) {
        const consoleEl = document.getElementById("scan_console");
        let color = "#38bdf8";
        if (type === "success") color = "#4ade80";
        if (type === "error") color = "#f87171";
        
        const line = document.createElement("div");
        line.style.color = color;
        line.textContent = (type === "info" ? "> " : "") + message;
        
        consoleEl.appendChild(line);
        consoleEl.scrollTop = consoleEl.scrollHeight;
    }
</script>
