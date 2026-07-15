<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Search Scraper Engine</h1>
        <p>Harvest email addresses automatically based on target niches, auto-assigning them to your list under the <strong>scraped</strong> tag.</p>
    </div>
</div>

<div class="grid grid-1-3" style="align-items: start; margin-bottom: 24px;">
    <!-- Left Pane: Run Search -->
    <div class="card" style="padding: 24px; min-height: 280px; display: flex; flex-direction: column;">
        <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
            <span class="card-title">Keyword Search Parameters</span>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="niche_keyword">Target Keyword / Niche</label>
            <input class="form-control" type="text" id="niche_keyword" placeholder="e.g. 'london web design' or 'real estate uk'">
        </div>

        <div class="form-group" style="margin-bottom: 24px;">
            <label class="form-label" for="search_depth">Search Engine Depth (Pages)</label>
            <select class="form-control" id="search_depth">
                <option value="1">1 Page (Fast)</option>
                <option value="2" selected>2 Pages (Recommended)</option>
                <option value="3">3 Pages (Deep Search)</option>
                <option value="5">5 Pages (Thorough)</option>
            </select>
        </div>

        <div style="margin-top: auto;">
            <button type="button" id="start_scrape_btn" class="btn btn-primary" onclick="runScraper()" style="font-weight: 600; width: 100%; padding: 10px;">
                Harvest Emails →
            </button>
        </div>
    </div>

    <!-- Right Pane: Live Scrape Console -->
    <div class="card" style="padding: 24px; min-height: 280px; display: flex; flex-direction: column;">
        <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
            <span class="card-title">Real-time Scraper Terminal</span>
        </div>
        
        <div id="scrape_console" style="background-color: #0f172a; color: #38bdf8; font-family: monospace; font-size: 12px; padding: 16px; border-radius: 6px; flex-grow: 1; min-height: 160px; max-height: 200px; overflow-y: auto; line-height: 1.6; border: 1px solid #1e293b;">
            <div style="color: #64748b;">> Scraper engine idle. Enter keyword parameters and start harvesting...</div>
        </div>
    </div>
</div>

<!-- Info Cards -->
<div class="card" style="padding: 20px;">
    <div class="card-header" style="border-bottom: none; padding-bottom: 0; margin-bottom: 12px;">
        <span class="card-title">Scraper Integration Details</span>
    </div>
    
    <div class="grid grid-3" style="margin-bottom: 0; gap: 16px;">
        <div style="background-color: var(--theme-bg); padding: 16px; border-radius: 6px; border: 1px solid var(--theme-border);">
            <h4 style="color: var(--theme-dark); margin-bottom: 4px; font-weight: 700;">Automatic Tagging</h4>
            <p style="font-size: 12px; color: var(--theme-dark-slate); line-height: 1.4;">
                All harvested contacts are automatically tagged under the teal <strong>scraped</strong> tag, allowing you to segment or target them cleanly in campaigns.
            </p>
        </div>
        
        <div style="background-color: var(--theme-bg); padding: 16px; border-radius: 6px; border: 1px solid var(--theme-border);">
            <h4 style="color: var(--theme-dark); margin-bottom: 4px; font-weight: 700;">Deliverability Verification</h4>
            <p style="font-size: 12px; color: var(--theme-dark-slate); line-height: 1.4;">
                Connects directly to the <strong>Email Address Verifier</strong> module. Addresses are verified for valid format, MX server records, and disposable domains before entry.
            </p>
        </div>

        <div style="background-color: var(--theme-bg); padding: 16px; border-radius: 6px; border: 1px solid var(--theme-border);">
            <h4 style="color: var(--theme-dark); margin-bottom: 4px; font-weight: 700;">Harvester Status</h4>
            <p style="font-size: 12px; color: var(--theme-dark-slate); line-height: 1.4;">
                Currently holds <strong id="scraped_stat_count" style="color: var(--theme-blurple);"><?= $scrapedCount ?></strong> scraped contacts in your mailing database list directory.
            </p>
        </div>
    </div>
</div>

<script>
    async function runScraper() {
        const btn = document.getElementById("start_scrape_btn");
        const keywordInput = document.getElementById("niche_keyword");
        const depthSelect = document.getElementById("search_depth");
        const consoleEl = document.getElementById("scrape_console");

        const kw = keywordInput.value.trim();
        if (kw === "") {
            alert("Please enter a target niche keyword.");
            return;
        }

        btn.disabled = true;
        btn.textContent = "Harvesting web pages...";
        consoleEl.innerHTML = "";

        logConsole("Starting search query crawl for: '" + kw + "'...", "info");

        try {
            const resp = await fetch("<?= e(defined('BASE_PATH') ? BASE_PATH : '') ?>/scraper/run?keyword=" + encodeURIComponent(kw) + "&depth=" + depthSelect.value);
            const data = await resp.json();

            if (!data.success) {
                logConsole("Scraper Error: " + data.error, "error");
                btn.disabled = false;
                btn.textContent = "Harvest Emails →";
                return;
            }

            if (data.emails && data.emails.length > 0) {
                data.emails.forEach(email => {
                    logConsole("✔ Found deliverable email: " + email, "success");
                });
            }

            logConsole("\n--- Scrape Finished ---", "info");
            logConsole("Successfully Imported & Verified: " + data.added + " contacts", "success");
            logConsole("Discarded (Invalid or Duplicate): " + data.skipped + " contacts", "error");

            // Update stats
            const stat = document.getElementById("scraped_stat_count");
            if (stat) {
                const cur = parseInt(stat.textContent) || 0;
                stat.textContent = (cur + data.added).toString();
            }

        } catch (err) {
            logConsole("Crawl Error: " + err.message, "error");
        }

        btn.disabled = false;
        btn.textContent = "Harvest Emails →";
    }

    function logConsole(message, type) {
        const consoleEl = document.getElementById("scrape_console");
        let color = "#38bdf8"; // blue info
        if (type === "success") color = "#4ade80"; // green
        if (type === "error") color = "#f87171"; // red
        
        const line = document.createElement("div");
        line.style.color = color;
        line.textContent = (type === "info" ? "> " : "") + message;
        consoleEl.appendChild(line);
        consoleEl.scrollTop = consoleEl.scrollHeight;
    }
</script>
