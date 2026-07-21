<?php
declare(strict_types=1);

$currentTab = $_GET['tab'] ?? 'scraper';
?>

<div class="header-actions" style="margin-bottom: 20px;">
    <div class="page-title">
        <h1>Lead Acquisition & Intelligence Hub</h1>
        <p>Omnipresent intent scraper, Google Maps business finder, email deliverability verifier, and B2B enrichment in one place.</p>
    </div>
</div>

<!-- Suite Navigation Tabs -->
<div style="display: flex; gap: 4px; border-bottom: 1px solid var(--theme-border); margin-bottom: 24px; flex-wrap: wrap;">
    <button class="btn intel-tab-btn <?= $currentTab === 'scraper' ? 'active' : '' ?>" onclick="switchIntelTab(event, 'tab-scraper')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'scraper' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'scraper' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        🎯 7-Engine Intent Scraper
    </button>
    <button class="btn intel-tab-btn <?= $currentTab === 'maps' ? 'active' : '' ?>" onclick="switchIntelTab(event, 'tab-maps')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'maps' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'maps' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        🗺️ Google Maps B2B Generator
    </button>
    <button class="btn intel-tab-btn <?= $currentTab === 'verifier' ? 'active' : '' ?>" onclick="switchIntelTab(event, 'tab-verifier')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'verifier' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'verifier' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        🔍 Email Verifier & Spam Audit
    </button>
    <button class="btn intel-tab-btn <?= $currentTab === 'enrichment' ? 'active' : '' ?>" onclick="switchIntelTab(event, 'tab-enrichment')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'enrichment' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'enrichment' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        🏢 B2B Data Enrichment
    </button>
</div>

<!-- TAB 1: 7-Engine Intent Scraper -->
<div id="tab-scraper" class="intel-tab-content" style="display: <?= $currentTab === 'scraper' ? 'block' : 'none' ?>;">
    <div class="grid grid-2" style="align-items: start; gap: 24px; margin-bottom: 24px;">
        <div class="card" style="padding: 24px; min-height: 300px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">Omnipresent Intent Search Scraper</span>
            </div>
            <div class="form-group">
                <label class="form-label" for="keyword">Buyer Intent Search Phrase</label>
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
                    <label class="form-label" for="channel">Search Provider</label>
                    <select class="form-control" id="channel">
                        <option value="all" selected>All 7 Engines (Google + DuckDuckGo + Bing + Yahoo + Ask + Mojeek + Brave)</option>
                        <option value="google">Google Engine Only</option>
                        <option value="duckduckgo">DuckDuckGo Engine Only</option>
                        <option value="bing">Bing Search Engine Only</option>
                        <option value="yahoo">Yahoo Search Engine Only</option>
                        <option value="ask">Ask.com Engine Only</option>
                        <option value="mojeek">Mojeek Engine Only</option>
                        <option value="brave">Brave Engine Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="depth">Crawling Depth</label>
                    <select class="form-control" id="depth">
                        <option value="1">1 Page (Fast Scan)</option>
                        <option value="2" selected>2 Pages (Deep Crawl)</option>
                        <option value="3">3 Pages (Extensive Audit)</option>
                    </select>
                </div>
            </div>
            <div style="margin-top: auto;">
                <button type="button" id="start_search_btn" class="btn btn-primary" onclick="runSearchScraper()" style="font-weight: 600; width: 100%; padding: 12px; justify-content: center;">
                    ⚡ Search Buyer Intent & Extract Leads →
                </button>
            </div>
        </div>

        <div class="card" style="padding: 24px; min-height: 300px; display: flex; flex-direction: column;">
            <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">Live Scraper Terminal</span>
            </div>
            <div id="search_console" style="background-color: #0f172a; color: #38bdf8; font-family: monospace; font-size: 12px; padding: 16px; border-radius: 6px; flex-grow: 1; min-height: 200px; max-height: 250px; overflow-y: auto; line-height: 1.6; border: 1px solid #1e293b;">
                <div style="color: #64748b;">> Omnipresent Scraper idle. Enter phrase and click search...</div>
            </div>
        </div>
    </div>
</div>

<!-- TAB 2: Google Maps B2B Generator -->
<div id="tab-maps" class="intel-tab-content" style="display: <?= $currentTab === 'maps' ? 'block' : 'none' ?>;">
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
            <span class="card-title">Google Maps B2B Lead Generator</span>
        </div>
        <p style="font-size: 13px; color: var(--theme-dark-slate); line-height: 1.5; margin-bottom: 20px;">
            Extract local business leads, studios, and agencies matching your target niche and location directly into your CRM.
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
</script>
