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
        🔎 Advanced Search Scraper
    </button>
    <button class="btn scraper-tab-btn" id="btn-tab-maps" onclick="switchScraperTab(event, 'tab-maps')" style="border: none; border-bottom: 2px solid transparent; background: transparent; padding: 12px 18px; border-radius: 0; color: var(--theme-dark-slate); font-weight: 600; cursor: pointer;">
        🗺️ Google Maps B2B Lead Generator
    </button>
</div>

<!-- TAB 1: Advanced Search Scraper -->
<div id="tab-search" class="scraper-tab-content">
    <div class="grid grid-2" style="align-items: start; gap: 24px; margin-bottom: 24px;">
        <!-- Left: Search form -->
        <div class="card" style="padding: 24px; min-height: 300px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">Search Parameters</span>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="keyword">Target Search Query / Buyer Intent Phrase</label>
                <input class="form-control" type="text" id="keyword" placeholder="e.g. Looking for British Voice Over Actor or Newcastle Voiceover">
                <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px;">
                    <span style="font-size: 11px; font-weight: 600; color: var(--theme-dark-slate); align-self: center;">Intent Presets:</span>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setKeyword('Looking for British Voice Over Actor')">🎙️ British Voiceover</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setKeyword('Newcastle Geordie Voiceover Artist')">🎙️ Geordie / Newcastle</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setKeyword('Looking for Northeast England Voice Actor')">🎙️ Northeast England</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setKeyword('Need English Voice Over Studio')">🎙️ English Studio</button>
                </div>
            </div>

            <div class="form-row" style="margin-bottom: 16px;">
                <div class="form-group">
                    <label class="form-label" for="channel">Search Engines</label>
                    <select class="form-control" id="channel">
                        <option value="all" selected>All Engines (DuckDuckGo + Bing + Yahoo + Ask)</option>
                        <option value="duckduckgo">DuckDuckGo Engine Only</option>
                        <option value="bing">Bing Search Engine Only</option>
                        <option value="yahoo">Yahoo Search Engine Only</option>
                        <option value="ask">Ask.com Engine Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="depth">Crawling Depth</label>
                    <select class="form-control" id="depth">
                        <option value="1">1 Page (Fast Scan)</option>
                        <option value="2" selected>2 Pages (Deep Crawl)</option>
                        <option value="3">3 Pages (Extensive Domain Audit)</option>
                    </select>
                </div>
            </div>

            <div style="margin-top: auto;">
                <button type="button" id="start_search_btn" class="btn btn-primary" onclick="runSearchScraper()" style="font-weight: 600; width: 100%; padding: 12px; justify-content: center;">
                    ⚡ Search Buyer Intent & Extract Leads →
                </button>
            </div>
        </div>

        <!-- Right: Console terminal -->
        <div class="card" style="padding: 24px; min-height: 300px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">Live Scraper Terminal</span>
            </div>
            <div id="search_console" style="background-color: #0f172a; color: #38bdf8; font-family: monospace; font-size: 12px; padding: 16px; border-radius: 6px; flex-grow: 1; min-height: 200px; max-height: 250px; overflow-y: auto; line-height: 1.6; border: 1px solid #1e293b;">
                <div style="color: #64748b;">> Scraper idle. Enter parameters and click 'Crawl' to search...</div>
            </div>
        </div>
    </div>

    <!-- Review & Import Card (Hidden initially) -->
    <div class="card" id="review_card" style="padding: 24px; display: none; margin-bottom: 24px;">
        <div class="card-header" style="border-bottom: none; padding-bottom: 0; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <span class="card-title">Extracted Leads Review Panel</span>
                <p style="font-size: 12px; color: var(--theme-dark-slate); margin-top: 4px; margin-bottom: 0;">Review, verify deliverability status, and check leads to import selectively.</p>
            </div>
            <button type="button" class="btn btn-primary" onclick="importSelectedLeads()" style="font-weight: 700; height: 38px; padding: 0 20px;">
                Import Checked Leads to CRM
            </button>
        </div>

        <div class="table-wrapper" style="border: 1px solid var(--theme-border);">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;"><input type="checkbox" id="select_all_leads" onchange="toggleAllScrapedCheckboxes(this)" style="accent-color: var(--theme-blurple);"></th>
                        <th>Email Address</th>
                        <th>Crawled Domain / Source path</th>
                        <th style="width: 180px; text-align: center;">Deliverability Status</th>
                    </tr>
                </thead>
                <tbody id="scraped_leads_tbody">
                    <!-- Loaded dynamically -->
                </tbody>
            </table>
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
    // Tab controller
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

    // Load active tab
    window.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab');
        if (tab && document.getElementById('btn-tab-' + tab)) {
            document.getElementById('btn-tab-' + tab).click();
        }
    });

    // Console printer helper
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

    function setKeyword(val) {
        document.getElementById('keyword').value = val;
        runSearchScraper();
    }

    // Toggle checkboxes
    function toggleAllScrapedCheckboxes(masterCheckbox) {
        const list = document.querySelectorAll('.scraped-lead-checkbox');
        list.forEach(c => c.checked = masterCheckbox.checked);
    }

    // --- Search Scraper Logic ---
    let searchActive = false;
    let extractedLeads = []; // Cache list

    function runSearchScraper() {
        if (searchActive) return;
        
        const keyword = document.getElementById('keyword').value.trim();
        const depth = document.getElementById('depth').value;
        const channel = document.getElementById('channel').value;
        
        if (!keyword) {
            alert("Please enter a search keyword.");
            return;
        }
        
        searchActive = true;
        const btn = document.getElementById('start_search_btn');
        btn.disabled = true;
        btn.textContent = "Crawling search channels...";
        
        document.getElementById('review_card').style.display = 'none';
        
        const consoleId = 'search_console';
        document.getElementById(consoleId).innerHTML = '';
        printConsole(consoleId, `> Initializing Search Scraper engine...`);
        printConsole(consoleId, `> Active search channels: ${channel.toUpperCase()}`);
        printConsole(consoleId, `> Querying niche target: "${keyword}"`);
        printConsole(consoleId, `> Crawling search snippets and scraping links recursively...`);

        fetch(`<?= e(BASE_PATH) ?>/scraper/run?keyword=${encodeURIComponent(keyword)}&depth=${depth}&channel=${channel}`)
            .then(r => r.json())
            .then(data => {
                searchActive = false;
                btn.disabled = false;
                btn.textContent = "Crawl Search Engines & Extract →";
                
                if (data.success) {
                    extractedLeads = data.leads;
                    printConsole(consoleId, `> Scan complete. Extracted ${extractedLeads.length} unique addresses.`, 'success');
                    
                    if (extractedLeads.length > 0) {
                        renderLeadsReviewTable();
                        document.getElementById('review_card').style.display = 'block';
                        printConsole(consoleId, `> Verification logs complete. Review list below to selectively import checked leads.`, 'success');
                    } else {
                        printConsole(consoleId, `> No email addresses discovered. Adjust search keyword parameters and try again.`, 'warn');
                    }
                } else {
                    printConsole(consoleId, `> Search failed: ${data.error}`, 'error');
                }
            })
            .catch(() => {
                searchActive = false;
                btn.disabled = false;
                btn.textContent = "Crawl Search Engines & Extract →";
                printConsole(consoleId, `> Network request failed.`, 'error');
            });
    }

    function renderLeadsReviewTable() {
        const tbody = document.getElementById('scraped_leads_tbody');
        tbody.innerHTML = '';
        
        extractedLeads.forEach((lead, index) => {
            const tr = document.createElement('tr');
            
            const badgeBg = lead.valid ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)';
            const badgeColor = lead.valid ? '#10b981' : '#ef4444';
            
            tr.innerHTML = `
                <td style="text-align: center;">
                    <input type="checkbox" class="scraped-lead-checkbox" data-index="${index}" ${lead.valid ? 'checked' : ''} style="accent-color: var(--theme-blurple);">
                </td>
                <td style="font-weight: 600; color: var(--theme-dark);">${lead.email}</td>
                <td>
                    <span style="font-size: 11px; font-family: monospace; background: var(--theme-bg); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--theme-border);">${lead.domain}</span>
                    <span style="font-size: 11px; color: var(--theme-dark-slate); margin-left: 6px;">via ${lead.source}</span>
                </td>
                <td style="text-align: center;">
                    <span style="background-color: ${badgeBg}; color: ${badgeColor}; font-weight: 700; font-size: 11px; padding: 4px 10px; border-radius: 4px; display: inline-block;">
                        ● ${lead.reason}
                    </span>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function importSelectedLeads() {
        const checkboxes = document.querySelectorAll('.scraped-lead-checkbox:checked');
        if (checkboxes.length === 0) {
            alert("Please check at least one lead email address to import.");
            return;
        }

        const leadsToImport = [];
        checkboxes.forEach(c => {
            const index = parseInt(c.getAttribute('data-index'));
            leadsToImport.push(extractedLeads[index]);
        });

        if (!confirm(`Import ${leadsToImport.length} verified B2B leads directly into your CRM list?`)) {
            return;
        }

        const consoleId = 'search_console';
        printConsole(consoleId, `> Importing ${leadsToImport.length} checked leads to CRM database...`);

        fetch(`<?= e(BASE_PATH) ?>/scraper/import`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ leads: leadsToImport })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                printConsole(consoleId, `> Successfully imported ${data.imported} leads to your contacts database.`, 'success');
                alert(`Successfully imported ${data.imported} leads!`);
                document.getElementById('review_card').style.display = 'none';
            } else {
                printConsole(consoleId, `> Import failed: ${data.error}`, 'error');
                alert("Import failed: " + data.error);
            }
        })
        .catch(() => {
            printConsole(consoleId, `> Network connection failure on import.`, 'error');
        });
    }

    // --- Google Maps Scraper Logic ---
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
        
        fetch(`<?= e(BASE_PATH) ?>/maps-scraper/run?query=${encodeURIComponent(query)}&location=${encodeURIComponent(loc)}`)
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
