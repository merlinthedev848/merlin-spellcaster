<?php
declare(strict_types=1);
?>

<div class="header-actions" style="margin-bottom: 20px;">
    <div class="page-title">
        <h1>B2B Lead Scrapers</h1>
        <p>Extract leads dynamically from search engine pages and local map directories directly into your CRM.</p>
    </div>
</div>

<!-- Tabs Row -->
<div style="display: flex; gap: 4px; border-bottom: 1px solid var(--theme-border); margin-bottom: 24px;">
    <button class="btn scraper-tab-btn active" id="btn-tab-search" onclick="switchScraperTab(event, 'tab-search')" style="border: none; border-bottom: 2px solid var(--theme-blurple); background: transparent; padding: 12px 18px; border-radius: 0; color: var(--theme-blurple); font-weight: 600; cursor: pointer;">
        🔎 Organic Search Scraper
    </button>
    <button class="btn scraper-tab-btn" id="btn-tab-maps" onclick="switchScraperTab(event, 'tab-maps')" style="border: none; border-bottom: 2px solid transparent; background: transparent; padding: 12px 18px; border-radius: 0; color: var(--theme-dark-slate); font-weight: 600; cursor: pointer;">
        🗺️ Google Maps B2B Lead Generator
    </button>
</div>

<!-- TAB 1: Organic Search Scraper -->
<div id="tab-search" class="scraper-tab-content">
    <div class="grid grid-1-3" style="align-items: start; gap: 24px; margin-bottom: 24px;">
        <div class="card" style="padding: 24px; min-height: 300px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">Search Parameters</span>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="keyword">Search Target / Niche</label>
                <input class="form-control" type="text" id="keyword" placeholder="e.g. Plumbers Birmingham or Dentist Manchester">
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label" for="depth">Crawling Page Depth</label>
                <select class="form-control" id="depth">
                    <option value="1">1 Page (Fast)</option>
                    <option value="2" selected>2 Pages (Balanced)</option>
                    <option value="3">3 Pages (Deep)</option>
                    <option value="5">5 Pages (Extensive)</option>
                </select>
            </div>

            <div style="margin-top: auto;">
                <button type="button" id="start_search_btn" class="btn btn-primary" onclick="runSearchScraper()" style="font-weight: 600; width: 100%; padding: 12px; justify-content: center;">
                    Extract Search Leads →
                </button>
            </div>
        </div>

        <div class="card" style="padding: 24px; min-height: 300px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">Live Scraper Terminal</span>
            </div>
            
            <div id="search_console" style="background-color: #0f172a; color: #38bdf8; font-family: monospace; font-size: 12px; padding: 16px; border-radius: 6px; flex-grow: 1; min-height: 200px; max-height: 300px; overflow-y: auto; line-height: 1.6; border: 1px solid #1e293b;">
                <div style="color: #64748b;">> Search engine idle. Waiting for query inputs...</div>
            </div>
        </div>
    </div>
</div>

<!-- TAB 2: Google Maps Scraper -->
<div id="tab-maps" class="scraper-tab-content" style="display: none;">
    <div class="grid grid-1-3" style="align-items: start; gap: 24px; margin-bottom: 24px;">
        <div class="card" style="padding: 24px; min-height: 300px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">Maps Search Parameters</span>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="search_query">Business Type / Niche</label>
                <input class="form-control" type="text" id="search_query" placeholder="e.g. Plumbers or Real Estate Agencies">
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label" for="search_location">Target Location</label>
                <input class="form-control" type="text" id="search_location" placeholder="e.g. London, UK or 10001">
            </div>

            <div style="margin-top: auto;">
                <button type="button" id="start_maps_btn" class="btn btn-primary" onclick="runMapsScraper()" style="font-weight: 600; width: 100%; padding: 12px; justify-content: center;">
                    Extract Maps Leads →
                </button>
            </div>
        </div>

        <div class="card" style="padding: 24px; min-height: 300px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">Real-time Maps Terminal</span>
            </div>
            
            <div id="maps_console" style="background-color: #0f172a; color: #38bdf8; font-family: monospace; font-size: 12px; padding: 16px; border-radius: 6px; flex-grow: 1; min-height: 200px; max-height: 300px; overflow-y: auto; line-height: 1.6; border: 1px solid #1e293b;">
                <div style="color: #64748b;">> Google Maps engine idle. Waiting for search queries...</div>
            </div>
        </div>
    </div>
</div>

<script>
    // Tab switching controller
    function switchScraperTab(event, tabId) {
        const contents = document.querySelectorAll(".scraper-tab-content");
        contents.forEach(c => c.style.display = "none");

        const buttons = document.querySelectorAll(".scraper-tab-btn");
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

    // Auto-select tab on load
    window.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab');
        if (tab && document.getElementById('btn-tab-' + tab)) {
            document.getElementById('btn-tab-' + tab).click();
        }
    });

    // Console logging utilities
    function printConsole(consoleId, msg, type = 'info') {
        const cons = document.getElementById(consoleId);
        const div = document.createElement('div');
        const time = new Date().toLocaleTimeString();
        
        let colorStr = '';
        if (type === 'success') colorStr = 'color: #34d399; font-weight: bold;';
        if (type === 'error') colorStr = 'color: #f87171;';
        if (type === 'warn') colorStr = 'color: #fbbf24;';
        
        div.innerHTML = `<span style="color:#64748b">[${time}]</span> <span style="${colorStr}">${msg}</span>`;
        cons.appendChild(div);
        cons.scrollTop = cons.scrollHeight;
    }

    // --- Search Scraper Logic ---
    let searchActive = false;
    function runSearchScraper() {
        if (searchActive) return;
        
        const keyword = document.getElementById('keyword').value.trim();
        const depth = document.getElementById('depth').value;
        
        if (!keyword) {
            alert("Please enter a search keyword.");
            return;
        }
        
        searchActive = true;
        const btn = document.getElementById('start_search_btn');
        btn.disabled = true;
        btn.textContent = "Extracting search pages...";
        
        const consoleId = 'search_console';
        document.getElementById(consoleId).innerHTML = '';
        printConsole(consoleId, `> Initializing Ask.com scraping engine...`);
        printConsole(consoleId, `> Querying keyword target: "${keyword}"`);
        printConsole(consoleId, `> Processing depth: ${depth} pages`);

        fetch(`<?= e(getSetting('app_url')) ?>/scraper/run?keyword=${encodeURIComponent(keyword)}&depth=${depth}`)
            .then(r => r.json())
            .then(data => {
                searchActive = false;
                btn.disabled = false;
                btn.textContent = "Extract Search Leads →";
                
                if (data.success) {
                    printConsole(consoleId, `> Scan complete. Extracted ${data.emails.length} deliverable email addresses.`, 'success');
                    data.emails.forEach(email => {
                        printConsole(consoleId, `  -> Extracted: ${email}`, 'success');
                    });
                    printConsole(consoleId, `> Added ${data.added} new contacts. Skipped ${data.skipped} bounces/duplicates.`, 'success');
                } else {
                    printConsole(consoleId, `> Scan failed: ${data.error}`, 'error');
                }
            })
            .catch(() => {
                searchActive = false;
                btn.disabled = false;
                btn.textContent = "Extract Search Leads →";
                printConsole(consoleId, `> Network connection failure.`, 'error');
            });
    }

    // --- Maps Scraper Logic ---
    let mapsActive = false;
    function runMapsScraper() {
        if (mapsActive) return;
        
        const query = document.getElementById('search_query').value.trim();
        const loc = document.getElementById('search_location').value.trim();
        
        if (!query || !loc) {
            alert("Please enter both Business Type and Location.");
            return;
        }
        
        mapsActive = true;
        const btn = document.getElementById('start_maps_btn');
        btn.disabled = true;
        btn.textContent = "Extracting maps listings...";
        
        const consoleId = 'maps_console';
        document.getElementById(consoleId).innerHTML = '';
        printConsole(consoleId, `> Initializing Google Maps crawling sequence...`);
        printConsole(consoleId, `> Setting geographical bounds to: ${loc}`);
        printConsole(consoleId, `> Extracting listings matching: "${query}"`);
        
        fetch(`<?= e(getSetting('app_url')) ?>/maps-scraper/run?query=${encodeURIComponent(query)}&location=${encodeURIComponent(loc)}`)
            .then(r => r.json())
            .then(data => {
                mapsActive = false;
                btn.disabled = false;
                btn.textContent = "Extract Maps Leads →";
                
                if (data.success) {
                    printConsole(consoleId, `> Scan complete. Discovered ${data.leads.length} local business listings.`, 'success');
                    data.leads.forEach(lead => {
                        printConsole(consoleId, `  -> Extracted: ${lead.name} | ${lead.email} | ${lead.phone}`, 'success');
                    });
                    printConsole(consoleId, `> Imported ${data.added} leads to CRM with 'maps_lead' tag successfully.`, 'success');
                } else {
                    printConsole(consoleId, `> Extraction failed: ${data.error}`, 'error');
                }
            })
            .catch(() => {
                mapsActive = false;
                btn.disabled = false;
                btn.textContent = "Extract Maps Leads →";
                printConsole(consoleId, `> Network API connection failure.`, 'error');
            });
    }
</script>
