<?php
declare(strict_types=1);

$currentTab = $_GET['tab'] ?? 'scraper';
?>

<div class="header-actions" style="margin-bottom: 20px;">
    <div class="page-title">
        <h1>Lead Acquisition & Intelligence Powerhouse</h1>
        <p>Target clients and buyers who hire your services across 7 search engines, verify deliverability, and enrich B2B profile data.</p>
    </div>
</div>

<!-- Suite Navigation Tabs -->
<div style="display: flex; gap: 4px; border-bottom: 1px solid var(--theme-border); margin-bottom: 24px; flex-wrap: wrap;">
    <button class="btn intel-tab-btn <?= $currentTab === 'scraper' ? 'active' : '' ?>" onclick="switchIntelTab(event, 'tab-scraper')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'scraper' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'scraper' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        🎯 Client & Buyer Lead Finder
    </button>
    <button class="btn intel-tab-btn <?= $currentTab === 'maps' ? 'active' : '' ?>" onclick="switchIntelTab(event, 'tab-maps')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'maps' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'maps' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        🗺️ Local Agency & Studio Finder
    </button>
    <button class="btn intel-tab-btn <?= $currentTab === 'verifier' ? 'active' : '' ?>" onclick="switchIntelTab(event, 'tab-verifier')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'verifier' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'verifier' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        🔍 Email Verifier & Spam Audit
    </button>
    <button class="btn intel-tab-btn <?= $currentTab === 'enrichment' ? 'active' : '' ?>" onclick="switchIntelTab(event, 'tab-enrichment')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'enrichment' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'enrichment' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        🏢 B2B Data Enrichment
    </button>
</div>

<!-- TAB 1: Client & Buyer Lead Finder -->
<div id="tab-scraper" class="intel-tab-content" style="display: <?= $currentTab === 'scraper' ? 'block' : 'none' ?>;">
    <div class="grid grid-2" style="align-items: start; gap: 24px; margin-bottom: 24px;">
        <div class="card" style="padding: 24px; min-height: 320px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">Buyer Intent Target Search</span>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="keyword">Your Service / Specialty Offering</label>
                <input class="form-control" type="text" id="keyword" value="British Voiceover Services" placeholder="e.g. British Voice Over Actor, Newcastle Voiceover, Web Development">
                <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px;">
                    <span style="font-size: 11px; font-weight: 600; color: var(--theme-dark-slate); align-self: center;">Quick Presets:</span>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setKeyword('British Voiceover Services')">🎙️ British Voiceover</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setKeyword('Newcastle Geordie Voiceover')">🎙️ Geordie / Newcastle</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setKeyword('Northeast England Voice Actor')">🎙️ Northeast England</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setKeyword('English Voice Over Studio')">🎙️ English Studio</button>
                </div>
            </div>

            <div class="form-row" style="margin-bottom: 16px;">
                <div class="form-group">
                    <label class="form-label" for="channel">Search Engines</label>
                    <select class="form-control" id="channel">
                        <option value="all" selected>All 7 Engines (Google + DuckDuckGo + Bing + Yahoo)</option>
                        <option value="google">Google Engine Only</option>
                        <option value="duckduckgo">DuckDuckGo Engine Only</option>
                        <option value="bing">Bing Search Engine Only</option>
                        <option value="yahoo">Yahoo Search Engine Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="depth">Crawling Depth</label>
                    <select class="form-control" id="depth">
                        <option value="1">1 Page (Fast)</option>
                        <option value="2" selected>2 Pages (Deep Crawl)</option>
                        <option value="3">3 Pages (Extensive Audit)</option>
                    </select>
                </div>
            </div>

            <div style="margin-top: auto;">
                <button type="button" id="start_search_btn" class="btn btn-primary" onclick="runSearchScraper()" style="font-weight: 600; width: 100%; padding: 12px; justify-content: center;">
                    ⚡ Find Clients & Buyer Contacts →
                </button>
            </div>
        </div>

        <div class="card" style="padding: 24px; min-height: 320px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">Live Scraper Console</span>
            </div>
            <div id="search_console" style="background-color: #0f172a; color: #38bdf8; font-family: monospace; font-size: 12px; padding: 16px; border-radius: 6px; flex-grow: 1; min-height: 220px; max-height: 260px; overflow-y: auto; line-height: 1.6; border: 1px solid #1e293b;">
                <div style="color: #64748b;">> Buyer Lead Scraper ready. Enter your service and click 'Find Clients'...</div>
            </div>
        </div>
    </div>

    <!-- Review & Import Panel -->
    <div class="card" id="review_card" style="padding: 24px; display: none; margin-bottom: 24px;">
        <div class="card-header" style="border-bottom: none; padding-bottom: 0; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <span class="card-title">Discovered Client & Buyer Contacts</span>
                <p style="font-size: 12px; color: var(--theme-dark-slate); margin-top: 4px; margin-bottom: 0;">Excludes self-email & competitor actor directories. Shows producers, agencies, & decision makers.</p>
            </div>
            <button type="button" class="btn btn-primary" onclick="importSelectedLeads()" style="font-weight: 700; height: 38px; padding: 0 20px;">
                Import Selected Buyer Leads to CRM
            </button>
        </div>

        <div class="table-wrapper" style="border: 1px solid var(--theme-border);">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;"><input type="checkbox" onchange="toggleAllScrapedCheckboxes(this)" checked></th>
                        <th>Buyer Company / Title</th>
                        <th>Target Role</th>
                        <th>Contact Email</th>
                        <th>Phone</th>
                        <th>Buyer Match</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody id="extracted_leads_tbody">
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TAB 2: Google Maps Local Agency Finder -->
<div id="tab-maps" class="intel-tab-content" style="display: <?= $currentTab === 'maps' ? 'block' : 'none' ?>;">
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
            <span class="card-title">Local Agency & Production Studio Finder</span>
        </div>
        <p style="font-size: 13px; color: var(--theme-dark-slate); line-height: 1.5; margin-bottom: 20px;">
            Target video production companies, ad agencies, and studios in specific cities (e.g. Newcastle, London, Manchester).
        </p>
    </div>
</div>

<!-- TAB 3: Email Verifier -->
<div id="tab-verifier" class="intel-tab-content" style="display: <?= $currentTab === 'verifier' ? 'block' : 'none' ?>;">
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
            <h2 style="font-size: 28px; font-weight: 700; color: var(--danger); margin-top: 4px;"><?= $bouncedVerifier ?></h2>
        </div>
        <div class="card" style="padding: 16px;">
            <span style="font-size: 11px; font-weight: 600; color: var(--theme-dark-slate); text-transform: uppercase;">Unsubscribed</span>
            <h2 style="font-size: 28px; font-weight: 700; color: var(--theme-dark); margin-top: 4px;"><?= $unsubsVerifier ?></h2>
        </div>
    </div>
</div>

<!-- TAB 4: B2B Data Enrichment -->
<div id="tab-enrichment" class="intel-tab-content" style="display: <?= $currentTab === 'enrichment' ? 'block' : 'none' ?>;">
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="margin-bottom: 16px; border-bottom: none; padding-bottom: 0; display: flex; justify-content: space-between; align-items: center;">
            <span class="card-title">Corporate Leads Available for Enrichment</span>
            <button id="btn_enrich_all" type="button" class="btn btn-primary" onclick="enrichAllContacts()">⚡ Process & Enrich All Contacts</button>
        </div>
    </div>
</div>

<script>
let searchActive = false;
let extractedLeads = [];

function switchIntelTab(e, tabId) {
    document.querySelectorAll('.intel-tab-btn').forEach(btn => {
        btn.style.borderBottomColor = 'transparent';
        btn.style.color = 'var(--theme-dark-slate)';
    });
    e.currentTarget.style.borderBottomColor = 'var(--theme-blurple)';
    e.currentTarget.style.color = 'var(--theme-blurple)';
    document.querySelectorAll('.intel-tab-content').forEach(c => c.style.display = 'none');
    document.getElementById(tabId).style.display = 'block';
}

function setKeyword(val) {
    document.getElementById('keyword').value = val;
    runSearchScraper();
}

function printConsole(id, text) {
    const cons = document.getElementById(id);
    const div = document.createElement('div');
    div.textContent = text;
    cons.appendChild(div);
    cons.scrollTop = cons.scrollHeight;
}

function toggleAllScrapedCheckboxes(master) {
    document.querySelectorAll('.scraped-lead-checkbox').forEach(c => c.checked = master.checked);
}

function runSearchScraper() {
    if (searchActive) return;
    
    const keyword = document.getElementById('keyword').value.trim();
    const depth = document.getElementById('depth').value;
    const channel = document.getElementById('channel').value;
    
    if (!keyword) {
        alert("Please enter your service or specialty offering.");
        return;
    }
    
    searchActive = true;
    const btn = document.getElementById('start_search_btn');
    btn.disabled = true;
    btn.textContent = "Searching buyer channels...";
    
    document.getElementById('review_card').style.display = 'none';
    const consoleId = 'search_console';
    document.getElementById(consoleId).innerHTML = '';
    
    printConsole(consoleId, `> Initializing Buyer Lead Finder engine...`);
    printConsole(consoleId, `> Filtering out self-email and actor directory sites...`);
    printConsole(consoleId, `> Targeting buyer agencies, producers, & casting directors for: "${keyword}"`);

    fetch(`<?= e(BASE_PATH) ?>/scraper/run?keyword=${encodeURIComponent(keyword)}&depth=${depth}&channel=${channel}`)
        .then(r => r.json())
        .then(data => {
            searchActive = false;
            btn.disabled = false;
            btn.textContent = "⚡ Find Clients & Buyer Contacts →";

            if (data.status === 'success') {
                printConsole(consoleId, `> Crawl finished cleanly.`);
                printConsole(consoleId, `> Found ${data.count} buyer contacts with high commercial relevance.`);
                extractedLeads = data.data;
                renderExtractedLeads(extractedLeads);
            } else {
                printConsole(consoleId, `> Error: ${data.message}`);
            }
        })
        .catch(err => {
            searchActive = false;
            btn.disabled = false;
            btn.textContent = "⚡ Find Clients & Buyer Contacts →";
            printConsole(consoleId, `> Engine error: ${err.message}`);
        });
}

function renderExtractedLeads(leads) {
    const tbody = document.getElementById('extracted_leads_tbody');
    tbody.innerHTML = '';

    if (!leads || leads.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--theme-dark-slate);">No buyer leads found for this exact phrase. Try a broader service term.</td></tr>';
        document.getElementById('review_card').style.display = 'block';
        return;
    }

    leads.forEach((item, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="text-align:center;"><input type="checkbox" class="scraped-lead-checkbox" data-idx="${idx}" checked></td>
            <td style="font-weight:600; color:var(--theme-dark);">${escapeHtml(item.company)}</td>
            <td><span class="badge" style="background:rgba(99,91,255,0.1); color:var(--theme-blurple); font-weight:600;">${escapeHtml(item.role)}</span></td>
            <td style="font-weight:600;">${escapeHtml(item.email)}</td>
            <td>${escapeHtml(item.phone || '-')}</td>
            <td><span style="color:#059669; font-weight:700;">${item.buyer_score}% Match</span></td>
            <td style="font-size:11px; color:var(--theme-dark-slate);">${escapeHtml(item.source)}</td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('review_card').style.display = 'block';
}

function escapeHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function importSelectedLeads() {
    const checkboxes = document.querySelectorAll('.scraped-lead-checkbox:checked');
    const selected = [];
    checkboxes.forEach(cb => {
        const idx = cb.getAttribute('data-idx');
        if (extractedLeads[idx]) {
            selected.push(extractedLeads[idx]);
        }
    });

    if (selected.length === 0) {
        alert("Please check at least one lead to import.");
        return;
    }

    fetch(`<?= e(BASE_PATH) ?>/scraper/import`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ leads: selected })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            alert(`Successfully imported ${res.imported} buyer leads into your CRM! (${res.skipped} skipped/duplicates).`);
        } else {
            alert(`Import failed: ${res.message}`);
        }
    })
    .catch(err => alert(`Import error: ${err.message}`));
}
</script>
