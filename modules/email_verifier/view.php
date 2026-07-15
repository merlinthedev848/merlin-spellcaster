<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Email Verifier Dashboard</h1>
        <p>Monitor deliverability, syntax, MX servers, and purge disposable emails or spam traps from your list.</p>
    </div>
</div>

<!-- Stats Indicators -->
<div class="grid grid-4" style="margin-bottom: 24px;">
    <div class="card" style="padding: 16px;">
        <span style="font-size: 11px; font-weight: 600; color: var(--theme-dark-slate); text-transform: uppercase;">Total CRM Contacts</span>
        <h2 style="font-size: 28px; font-weight: 700; color: var(--theme-dark); margin-top: 4px;"><?= $total ?></h2>
    </div>
    
    <div class="card" style="padding: 16px;">
        <span style="font-size: 11px; font-weight: 600; color: var(--success); text-transform: uppercase;">Active / Deliverable</span>
        <h2 style="font-size: 28px; font-weight: 700; color: var(--success); margin-top: 4px;"><?= $active ?></h2>
    </div>
    
    <div class="card" style="padding: 16px;">
        <span style="font-size: 11px; font-weight: 600; color: var(--danger); text-transform: uppercase;">Bounced / Spam Flagged</span>
        <h2 style="font-size: 28px; font-weight: 700; color: var(--danger); margin-top: 4px;" id="bounce_count_stat"><?= $bounced ?></h2>
    </div>

    <div class="card" style="padding: 16px;">
        <span style="font-size: 11px; font-weight: 600; color: var(--theme-dark-slate); text-transform: uppercase;">Unsubscribed</span>
        <h2 style="font-size: 28px; font-weight: 700; color: var(--theme-dark); margin-top: 4px;"><?= $unsubscribed ?></h2>
    </div>
</div>

<div class="grid grid-2" style="align-items: start;">
    <!-- Scan Trigger Card -->
    <div class="card" style="padding: 24px; min-height: 300px; display: flex; flex-direction: column;">
        <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
            <span class="card-title">List Verification Scan</span>
        </div>
        
        <p style="font-size: 13px; color: var(--theme-dark-slate); line-height: 1.5; margin-bottom: 24px;">
            Run a deliverability audit across all active contacts. The verifier will query DNS records for valid mail servers (MX) and screen domains against known disposable address blacklists. Flagged contacts will be automatically moved to <strong>bounced</strong> status to keep your list clean.
        </p>

        <div style="margin-top: auto;">
            <button type="button" id="start_scan_btn" class="btn btn-primary" onclick="runEmailScan()" style="font-weight: 600; width: 100%; padding: 12px;">
                Scan & Verify Active Contacts List →
            </button>
        </div>

        <!-- Progress Indicator -->
        <div id="scan_progress_container" style="display: none; margin-top: 24px;">
            <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; margin-bottom: 6px;">
                <span id="scan_status_label" style="color: var(--theme-dark);">Initializing Scan...</span>
                <span id="scan_percentage" style="color: var(--theme-blurple);">0%</span>
            </div>
            
            <!-- Progress Bar -->
            <div style="background-color: var(--theme-border); border-radius: 6px; height: 10px; overflow: hidden; width: 100%; margin-bottom: 12px;">
                <div id="scan_progress_bar" style="background-color: var(--theme-blurple); height: 100%; width: 0%; transition: width 0.3s ease;"></div>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--theme-dark-slate);">
                <span>Processed: <strong id="processed_count">0</strong> / <strong id="total_count">0</strong></span>
                <span>Flagged: <strong id="flagged_count" style="color: var(--danger);">0</strong></span>
            </div>
        </div>
    </div>

    <!-- Real-time Scan Console -->
    <div class="card" style="padding: 24px; min-height: 300px; display: flex; flex-direction: column;">
        <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
            <span class="card-title">Live Verification Logs</span>
        </div>
        
        <div id="scan_console" style="background-color: #0f172a; color: #38bdf8; font-family: monospace; font-size: 12px; padding: 16px; border-radius: 6px; flex-grow: 1; min-height: 180px; max-height: 220px; overflow-y: auto; line-height: 1.6; border: 1px solid #1e293b;">
            <div style="color: #64748b;">> Console idle. Click 'Scan & Verify' to begin deliverability checks...</div>
        </div>
    </div>
</div>

<script>
    let scanIds = [];
    let currentIndex = 0;
    let flaggedCount = 0;
    const batchSize = 10; // Number of emails per AJAX batch request

    async function runEmailScan() {
        const btn = document.getElementById("start_scan_btn");
        const progress = document.getElementById("scan_progress_container");
        const consoleEl = document.getElementById("scan_console");
        
        btn.disabled = true;
        btn.textContent = "Auditing List deliverability...";
        progress.style.display = "block";
        consoleEl.innerHTML = "";
        
        logToConsole("Fetching active email contacts list...", "info");

        try {
            const resp = await fetch("<?= e(defined('BASE_PATH') ? BASE_PATH : '') ?>/verifier/scan");
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
            
            logToConsole("Loaded " + scanIds.length + " active contacts. Starting validations...", "info");
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
            const resp = await fetch("<?= e(defined('BASE_PATH') ? BASE_PATH : '') ?>/verifier/scan-batch", {
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

            // Run next batch after short delay to prevent server overhead
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

        // Increment bounce stat display
        const statBounce = document.getElementById("bounce_count_stat");
        if (statBounce) {
            const val = parseInt(statBounce.textContent) || 0;
            statBounce.textContent = (val + flaggedCount).toString();
        }
    }

    function logToConsole(message, type) {
        const consoleEl = document.getElementById("scan_console");
        let color = "#38bdf8"; // Info blue
        if (type === "success") color = "#4ade80"; // Green
        if (type === "error") color = "#f87171"; // Red
        
        const line = document.createElement("div");
        line.style.color = color;
        line.textContent = (type === "info" ? "> " : "") + message;
        
        consoleEl.appendChild(line);
        consoleEl.scrollTop = consoleEl.scrollHeight;
    }
</script>
