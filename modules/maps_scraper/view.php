<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Google Maps B2B Lead Generator</h1>
        <p>Extract highly targeted B2B leads directly from Google Maps listings. Leads are automatically tagged as <strong>maps_lead</strong>.</p>
    </div>
</div>

<div class="grid grid-1-3" style="align-items: start; margin-bottom: 24px;">
    <!-- Left Pane: Run Search -->
    <div class="card" style="padding: 24px; min-height: 280px; display: flex; flex-direction: column;">
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
            <button type="button" id="start_scrape_btn" class="btn btn-primary" onclick="runMapsScraper()" style="font-weight: 600; width: 100%; padding: 10px;">
                Extract Leads →
            </button>
        </div>
    </div>

    <!-- Right Pane: Live Scrape Console -->
    <div class="card" style="padding: 24px; min-height: 280px; display: flex; flex-direction: column;">
        <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
            <span class="card-title">Real-time Extraction Terminal</span>
        </div>
        
        <div id="scrape_console" style="background-color: #0f172a; color: #38bdf8; font-family: monospace; font-size: 12px; padding: 16px; border-radius: 6px; flex-grow: 1; min-height: 200px; max-height: 300px; overflow-y: auto; line-height: 1.6; border: 1px solid #1e293b;">
            <div style="color: #64748b;">> Google Maps engine idle. Waiting for coordinates...</div>
        </div>
    </div>
</div>

<script>
let isScraping = false;

function printToConsole(msg, type = 'info') {
    const cons = document.getElementById('scrape_console');
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

function runMapsScraper() {
    if (isScraping) return;
    
    const query = document.getElementById('search_query').value.trim();
    const loc = document.getElementById('search_location').value.trim();
    
    if (!query || !loc) {
        alert("Please enter both Business Type and Location.");
        return;
    }
    
    isScraping = true;
    const btn = document.getElementById('start_scrape_btn');
    btn.disabled = true;
    btn.innerText = "Extracting...";
    
    printToConsole(`> Initializing Google Maps API connection...`);
    printToConsole(`> Setting viewport to: ${loc}`);
    printToConsole(`> Searching for: ${query}`);
    
    fetch(`/maps-scraper/run?query=${encodeURIComponent(query)}&location=${encodeURIComponent(loc)}`)
        .then(r => r.json())
        .then(data => {
            isScraping = false;
            btn.disabled = false;
            btn.innerText = "Extract Leads →";
            
            if (data.success) {
                printToConsole(`> Extraction complete! Found ${data.leads.length} businesses.`, 'success');
                data.leads.forEach(lead => {
                    printToConsole(`  -> Extracted: ${lead.name} | ${lead.email} | ${lead.phone}`, 'success');
                });
                printToConsole(`> Successfully imported ${data.added} leads to CRM with 'maps_lead' tag.`, 'success');
            } else {
                printToConsole(`> Error: ${data.error}`, 'error');
            }
        })
        .catch(err => {
            isScraping = false;
            btn.disabled = false;
            btn.innerText = "Extract Leads →";
            printToConsole(`> Network error occurred.`, 'error');
        });
}
</script>
