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
    <button class="btn intel-tab-btn <?= $currentTab === 'scraper' ? 'active' : '' ?>" onclick="switchIntelTab(event, 'scraper')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'scraper' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'scraper' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        🎯 Client & Buyer Lead Finder
    </button>
    <button class="btn intel-tab-btn <?= $currentTab === 'maps' ? 'active' : '' ?>" onclick="switchIntelTab(event, 'maps')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'maps' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'maps' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        🗺️ Local Agency & Studio Finder
    </button>
    <button class="btn intel-tab-btn <?= $currentTab === 'verifier' ? 'active' : '' ?>" onclick="switchIntelTab(event, 'verifier')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'verifier' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'verifier' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        🔍 Email Verifier & Spam Audit
    </button>
    <button class="btn intel-tab-btn <?= $currentTab === 'enrichment' ? 'active' : '' ?>" onclick="switchIntelTab(event, 'enrichment')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'enrichment' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'enrichment' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
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
            <button type="button" class="btn btn-primary" onclick="importSelectedLeads('extracted_leads_tbody')" style="font-weight: 700; height: 38px; padding: 0 20px;">
                Import Selected Buyer Leads to CRM
            </button>
        </div>

        <div class="table-wrapper" style="border: 1px solid var(--theme-border);">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;"><input type="checkbox" onchange="toggleAllScrapedCheckboxes(this, 'scraped-lead-checkbox')" checked></th>
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
    <div class="grid grid-2" style="align-items: start; gap: 24px; margin-bottom: 24px;">
        <div class="card" style="padding: 24px; min-height: 320px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">Local Agency & Studio Finder</span>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="maps_category">Agency Category / Studio Specialty</label>
                <input class="form-control" type="text" id="maps_category" value="Video Production Agency" placeholder="e.g. Video Production Agency, Recording Studio, Creative Agency">
            </div>

            <div class="form-row" style="margin-bottom: 16px;">
                <div class="form-group">
                    <label class="form-label" for="maps_location">City / Location</label>
                    <input class="form-control" type="text" id="maps_location" value="Newcastle" placeholder="e.g. Newcastle, London, Manchester">
                </div>
                <div class="form-group">
                    <label class="form-label" for="maps_depth">Crawling Pages</label>
                    <select class="form-control" id="maps_depth">
                        <option value="1">1 Page (Quick Scan)</option>
                        <option value="2" selected>2 Pages (Medium Search)</option>
                        <option value="3">3 Pages (Deep Audit)</option>
                    </select>
                </div>
            </div>

            <div style="margin-top: auto;">
                <button type="button" id="start_maps_btn" class="btn btn-primary" onclick="runMapsScraper()" style="font-weight: 600; width: 100%; padding: 12px; justify-content: center;">
                    🗺️ Find Local Agencies & Studios →
                </button>
            </div>
        </div>

        <div class="card" style="padding: 24px; min-height: 320px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">Live Finder Console</span>
            </div>
            <div id="maps_console" style="background-color: #0f172a; color: #38bdf8; font-family: monospace; font-size: 12px; padding: 16px; border-radius: 6px; flex-grow: 1; min-height: 220px; max-height: 260px; overflow-y: auto; line-height: 1.6; border: 1px solid #1e293b;">
                <div style="color: #64748b;">> Local Finder ready. Enter category and city, then click 'Find Local Agencies'...</div>
            </div>
        </div>
    </div>

    <!-- Local Finder Results Panel -->
    <div class="card" id="maps_review_card" style="padding: 24px; display: none; margin-bottom: 24px;">
        <div class="card-header" style="border-bottom: none; padding-bottom: 0; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <span class="card-title">Discovered Local Businesses & Websites</span>
                <p style="font-size: 12px; color: var(--theme-dark-slate); margin-top: 4px; margin-bottom: 0;">Crawled local listings matching category. Imports selected leads to the CRM database.</p>
            </div>
            <button type="button" class="btn btn-primary" onclick="importSelectedLeads('maps_leads_tbody')" style="font-weight: 700; height: 38px; padding: 0 20px;">
                Import Selected Local Leads
            </button>
        </div>

        <div class="table-wrapper" style="border: 1px solid var(--theme-border);">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;"><input type="checkbox" onchange="toggleAllScrapedCheckboxes(this, 'maps-lead-checkbox')" checked></th>
                        <th>Business Name</th>
                        <th>Category</th>
                        <th>Contact Email</th>
                        <th>Phone</th>
                        <th>Scraped Site</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody id="maps_leads_tbody">
                </tbody>
            </table>
        </div>
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

    <div class="grid grid-2" style="align-items: start; gap: 24px;">
        <!-- Single Email Verifier Card -->
        <div class="card" style="padding: 24px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
                <span class="card-title">Real-Time Single Email Verifier</span>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="verify_single_email">Enter Email Address</label>
                <input class="form-control" type="email" id="verify_single_email" placeholder="e.g. producer@productionstudio.com">
            </div>

            <button type="button" class="btn btn-primary" onclick="verifySingleEmailAddress()" style="font-weight: 600; margin-top: 12px;">
                🔍 Verify Deliverability
            </button>

            <!-- Verification Output Report Card -->
            <div id="verifier_output_card" style="display: none; margin-top: 20px; padding: 16px; border-radius: 6px; border: 1px solid var(--theme-border); background-color: var(--theme-bg);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                    <div id="verifier_status_badge" style="font-weight: 700; font-size: 12px; padding: 4px 10px; border-radius: 4px;"></div>
                    <span id="verifier_output_email" style="font-weight: 600; color: var(--theme-dark); font-size: 13px;"></span>
                </div>
                <div id="verifier_output_reason" style="font-size: 12px; color: var(--theme-dark-slate); line-height: 1.5;"></div>
            </div>
        </div>

        <!-- Bulk Deliverability Audit Card -->
        <div class="card" style="padding: 24px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
                <span class="card-title">Bulk CRM Spam & bounce Audit</span>
            </div>
            <p style="font-size: 13px; color: var(--theme-dark-slate); line-height: 1.5; margin-bottom: 16px;">
                Scans all active contacts in your database, validates MX records, checks for disposable inbox patterns, and flags bad records to preserve sender score.
            </p>

            <button type="button" id="start_bulk_verifier_btn" class="btn btn-secondary" onclick="runBulkVerifierAudit()" style="font-weight: 600;">
                ⚡ Start Bulk Deliverability Audit
            </button>

            <div id="bulk_verifier_console" style="background-color: #0f172a; color: #38bdf8; font-family: monospace; font-size: 11px; padding: 12px; border-radius: 6px; margin-top: 16px; height: 120px; overflow-y: auto; line-height: 1.5; border: 1px solid #1e293b; display: none;">
            </div>
        </div>
    </div>
</div>

<!-- TAB 4: B2B Data Enrichment -->
<div id="tab-enrichment" class="intel-tab-content" style="display: <?= $currentTab === 'enrichment' ? 'block' : 'none' ?>;">
    <div class="card" style="padding: 24px; margin-bottom: 24px;">
        <div class="card-header" style="margin-bottom: 16px; border-bottom: none; padding-bottom: 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <span class="card-title">Corporate Leads Available for Enrichment</span>
                <p style="font-size: 12px; color: var(--theme-dark-slate); margin-top: 4px; margin-bottom: 0;">Targeting domain-level corporate contacts. Generates company name, industry, description, and location metadata.</p>
            </div>
            <button id="btn_enrich_all" type="button" class="btn btn-primary" onclick="enrichAllCorporateContacts()">⚡ Batch Process & Enrich All Corporate Contacts</button>
        </div>

        <div class="table-wrapper" style="border: 1px solid var(--theme-border); margin-top: 12px;">
            <table>
                <thead>
                    <tr>
                        <th>Email Contact</th>
                        <th>Domain Profile</th>
                        <th>Company Name</th>
                        <th>Industry sector</th>
                        <th>Location</th>
                        <th style="width: 140px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($enrichableContacts)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--theme-dark-slate); padding: 40px;">No corporate contacts with valid business domains found in the CRM.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($enrichableContacts as $ec): 
                            $attrs = json_decode((string)($ec['attributes'] ?? ''), true) ?: [];
                        ?>
                            <tr id="enrich_row_<?= $ec['id'] ?>">
                                <td style="font-weight: 600; color: var(--theme-dark);"><?= e($ec['email']) ?></td>
                                <td><code style="font-family: monospace; font-size: 11px; background: var(--theme-bg); padding: 3px 6px; border-radius: 4px; border: 1px solid var(--theme-border);"><?= e(explode('@', $ec['email'])[1] ?? '') ?></code></td>
                                <td id="enrich_name_<?= $ec['id'] ?>"><?= e($attrs['company_name'] ?? '-') ?></td>
                                <td id="enrich_ind_<?= $ec['id'] ?>">
                                    <?php if (!empty($attrs['industry'])): ?>
                                        <span class="badge" style="background-color: var(--theme-blurple-light); color: var(--theme-blurple); font-weight: 600;"><?= e($attrs['industry']) ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td id="enrich_loc_<?= $ec['id'] ?>"><?= e($attrs['location'] ?? '-') ?></td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="enrichSingleContact(<?= $ec['id'] ?>)" style="padding: 4px 10px; font-size: 11px; font-weight: 600;">⚡ Enrich Profile</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let searchActive = false;
let extractedLeads = [];
let mapsLeads = [];

function switchIntelTab(e, tabId) {
    document.querySelectorAll('.intel-tab-btn').forEach(btn => {
        btn.style.borderBottomColor = 'transparent';
        btn.style.color = 'var(--theme-dark-slate)';
        btn.classList.remove('active');
    });
    e.currentTarget.style.borderBottomColor = 'var(--theme-blurple)';
    e.currentTarget.style.color = 'var(--theme-blurple)';
    e.currentTarget.classList.add('active');
    
    document.querySelectorAll('.intel-tab-content').forEach(c => c.style.display = 'none');
    document.getElementById('tab-' + tabId).style.display = 'block';

    // Update address bar parameter dynamically
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabId);
    window.history.pushState({}, '', url);
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

function toggleAllScrapedCheckboxes(master, cls) {
    document.querySelectorAll('.' + cls).forEach(c => c.checked = master.checked);
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

    fetch(`<?= e($appUrl) ?>/scraper/run?keyword=${encodeURIComponent(keyword)}&depth=${depth}&channel=${channel}`)
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

function runMapsScraper() {
    const category = document.getElementById('maps_category').value.trim();
    const location = document.getElementById('maps_location').value.trim();
    const depth = document.getElementById('maps_depth').value;

    if (!category || !location) {
        alert("Category and location city are required.");
        return;
    }

    const btn = document.getElementById('start_maps_btn');
    btn.disabled = true;
    btn.textContent = "Crawl business listings...";
    
    document.getElementById('maps_review_card').style.display = 'none';
    const consoleId = 'maps_console';
    document.getElementById(consoleId).innerHTML = '';

    printConsole(consoleId, `> Initializing local business crawler...`);
    printConsole(consoleId, `> Querying Google Maps directory listings for: "${category} in ${location}"`);
    printConsole(consoleId, `> Extracting business websites and loading homepage content...`);

    fetch(`<?= e($appUrl) ?>/maps/run?category=${encodeURIComponent(category)}&location=${encodeURIComponent(location)}&depth=${depth}`)
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = "🗺️ Find Local Agencies & Studios →";

            if (data.status === 'success') {
                printConsole(consoleId, `> Search finished. Crawled local pages successfully.`);
                printConsole(consoleId, `> Found ${data.count} local contacts with active website emails.`);
                mapsLeads = data.data;
                renderMapsLeads(mapsLeads);
            } else {
                printConsole(consoleId, `> Error: ${data.message}`);
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.textContent = "🗺️ Find Local Agencies & Studios →";
            printConsole(consoleId, `> Finder error: ${err.message}`);
        });
}

function renderMapsLeads(leads) {
    const tbody = document.getElementById('maps_leads_tbody');
    tbody.innerHTML = '';

    if (!leads || leads.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--theme-dark-slate);">No local leads found. Try a different city or broader category.</td></tr>';
        document.getElementById('maps_review_card').style.display = 'block';
        return;
    }

    leads.forEach((item, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="text-align:center;"><input type="checkbox" class="maps-lead-checkbox" data-idx="${idx}" checked></td>
            <td style="font-weight:600; color:var(--theme-dark);">${escapeHtml(item.company)}</td>
            <td><span class="badge" style="background-color:rgba(245,158,11,0.15); color:#d97706; font-weight:600;">${escapeHtml(item.role)}</span></td>
            <td style="font-weight:600;">${escapeHtml(item.email)}</td>
            <td>${escapeHtml(item.phone || '-')}</td>
            <td><code style="font-family:monospace; font-size:11px;">${escapeHtml(item.domain)}</code></td>
            <td style="font-size:11px; color:var(--theme-dark-slate);">${escapeHtml(item.source)}</td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('maps_review_card').style.display = 'block';
}

function verifySingleEmailAddress() {
    const email = document.getElementById('verify_single_email').value.trim();
    if (!email) {
        alert("Please enter an email address to verify.");
        return;
    }

    const reportCard = document.getElementById('verifier_output_card');
    const badge = document.getElementById('verifier_status_badge');
    const outEmail = document.getElementById('verifier_output_email');
    const outReason = document.getElementById('verifier_output_reason');

    reportCard.style.display = 'block';
    badge.className = '';
    badge.style.backgroundColor = 'var(--theme-border)';
    badge.style.color = 'var(--theme-dark-slate)';
    badge.textContent = "VERIFYING...";
    outEmail.textContent = email;
    outReason.textContent = "Checking deliverability syntaxes, domain MX lookup, and SMTP ping response...";

    fetch(`<?= e($appUrl) ?>/verifier/verify-single?email=${encodeURIComponent(email)}`)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                if (data.status === 'success' && data.status_type !== 'invalid' && data.status_type !== 'bounced') {
                    // Let's check status return key
                    const stat = data.status_type || data.status; 
                }
                
                // Read deliverability state
                const isGood = data.status === 'success' && (data.status_type === 'valid' || data.reason === 'MX records verified & deliverable');
                if (isGood) {
                    badge.style.backgroundColor = 'rgba(16,185,129,0.15)';
                    badge.style.color = '#059669';
                    badge.textContent = "DELIVERABLE";
                    outReason.innerHTML = `<strong>Result:</strong> ${escapeHtml(data.reason)}<br><small style="color:var(--success);">Email is safe to target for outgoing campaigns.</small>`;
                } else {
                    badge.style.backgroundColor = 'rgba(239,68,68,0.15)';
                    badge.style.color = '#b91c1c';
                    badge.textContent = "BOUNCED / INVALID";
                    outReason.innerHTML = `<strong>Result:</strong> ${escapeHtml(data.reason || 'Verification failed')}<br><small style="color:var(--danger);">Avoid mailing to prevent server bouncing.</small>`;
                }
            } else {
                badge.style.backgroundColor = 'rgba(239,68,68,0.15)';
                badge.style.color = '#b91c1c';
                badge.textContent = "ERROR";
                outReason.textContent = data.message || "An error occurred.";
            }
        })
        .catch(err => {
            badge.textContent = "ERROR";
            outReason.textContent = err.message;
        });
}

function runBulkVerifierAudit() {
    const btn = document.getElementById('start_bulk_verifier_btn');
    const consoleBox = document.getElementById('bulk_verifier_console');
    
    btn.disabled = true;
    btn.textContent = "Running deliverability audit...";
    consoleBox.style.display = 'block';
    consoleBox.innerHTML = '';

    printConsole('bulk_verifier_console', `> Starting CRM deliverability validation process...`);
    printConsole('bulk_verifier_console', `> Scanning active subscriber emails...`);

    fetch(`<?= e($appUrl) ?>/verifier/verify-bulk`)
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = "⚡ Start Bulk Deliverability Audit";

            if (data.status === 'success') {
                printConsole('bulk_verifier_console', `> Audit complete.`);
                printConsole('bulk_verifier_console', `> Processed: ${data.processed} contacts.`);
                printConsole('bulk_verifier_console', `> Clean & Deliverable: ${data.verified} contacts.`);
                printConsole('bulk_verifier_console', `> Flagged & Removed: ${data.flagged} contacts.`);
                
                if (data.flagged > 0) {
                    alert(`Audit complete! Flagged and removed ${data.flagged} invalid/bouncing email addresses from your active list.`);
                } else {
                    alert(`Audit complete! All active contact deliverability verified successfully.`);
                }
                window.location.reload();
            } else {
                printConsole('bulk_verifier_console', `> Error: ${data.message}`);
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.textContent = "⚡ Start Bulk Deliverability Audit";
            printConsole('bulk_verifier_console', `> Audit error: ${err.message}`);
        });
}

function enrichSingleContact(id) {
    const row = document.getElementById('enrich_row_' + id);
    const btn = row.querySelector('button');
    btn.disabled = true;
    btn.textContent = "Enriching...";

    fetch(`<?= e($appUrl) ?>/enrichment/run?id=${id}`)
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = "⚡ Enrich Profile";

            if (data.status === 'success') {
                document.getElementById('enrich_name_' + id).textContent = data.profile.company_name;
                
                const indTd = document.getElementById('enrich_ind_' + id);
                indTd.innerHTML = `<span class="badge" style="background-color:var(--theme-blurple-light); color:var(--theme-blurple); font-weight:600;">${escapeHtml(data.profile.industry)}</span>`;
                
                document.getElementById('enrich_loc_' + id).textContent = data.profile.location;
                
                alert(`Successfully enriched contact! Captured company "${data.profile.company_name}" (${data.profile.industry}) based in ${data.profile.location}.`);
            } else {
                alert(`Enrichment failed: ${data.message}`);
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.textContent = "⚡ Enrich Profile";
            alert(`Enrichment error: ${err.message}`);
        });
}

function enrichAllCorporateContacts() {
    const btn = document.getElementById('btn_enrich_all');
    btn.disabled = true;
    btn.textContent = "Batch processing profiles...";

    const rows = document.querySelectorAll('tr[id^="enrich_row_"]');
    if (rows.length === 0) {
        alert("No corporate contacts found to enrich.");
        btn.disabled = false;
        btn.textContent = "⚡ Batch Process & Enrich All Corporate Contacts";
        return;
    }

    let processedCount = 0;
    const processNext = (index) => {
        if (index >= rows.length) {
            alert(`Batch enrichment complete! Processed ${processedCount} corporate contacts.`);
            window.location.reload();
            return;
        }

        const row = rows[index];
        const id = row.id.replace('enrich_row_', '');

        fetch(`<?= e($appUrl) ?>/enrichment/run?id=${id}`)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    processedCount++;
                    document.getElementById('enrich_name_' + id).textContent = data.profile.company_name;
                    const indTd = document.getElementById('enrich_ind_' + id);
                    indTd.innerHTML = `<span class="badge" style="background-color:var(--theme-blurple-light); color:var(--theme-blurple); font-weight:600;">${escapeHtml(data.profile.industry)}</span>`;
                    document.getElementById('enrich_loc_' + id).textContent = data.profile.location;
                }
                processNext(index + 1);
            })
            .catch(() => processNext(index + 1));
    };

    processNext(0);
}

function escapeHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function importSelectedLeads(tbodyId) {
    const isMaps = (tbodyId === 'maps_leads_tbody');
    const checkedClass = isMaps ? '.maps-lead-checkbox:checked' : '.scraped-lead-checkbox:checked';
    const leadsSource = isMaps ? mapsLeads : extractedLeads;

    const checkboxes = document.querySelectorAll(checkedClass);
    const selected = [];
    checkboxes.forEach(cb => {
        const idx = cb.getAttribute('data-idx');
        if (leadsSource[idx]) {
            selected.push(leadsSource[idx]);
        }
    });

    if (selected.length === 0) {
        alert("Please check at least one lead to import.");
        return;
    }

    fetch(`<?= e($appUrl) ?>/scraper/import`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ leads: selected })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            alert(`Successfully imported ${res.imported} leads into your CRM database! (${res.skipped} skipped/duplicates).`);
            window.location.reload();
        } else {
            alert(`Import failed: ${res.message}`);
        }
    })
    .catch(err => alert(`Import error: ${err.message}`));
}
</script>
