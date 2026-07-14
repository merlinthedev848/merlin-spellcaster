<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>AI Copywriter</h1>
        <p>Generate high-converting email copy and subject lines instantly.</p>
    </div>
</div>

<div class="grid grid-1-3" style="align-items: start; margin-bottom: 24px;">
    <!-- Left Pane: Input Parameters -->
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
            <span class="card-title">Campaign Brief</span>
        </div>
        
        <form id="ai_form">
            <div class="form-group">
                <label class="form-label" for="prompt">What is this email about?</label>
                <textarea class="form-control" id="prompt" rows="4" placeholder="e.g. Announcing our new summer sale with 20% off all items..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="audience">Target Audience</label>
                <input class="form-control" type="text" id="audience" placeholder="e.g. Small Business Owners">
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label" for="tone">Tone of Voice</label>
                <select class="form-control" id="tone">
                    <option value="professional">Professional & Direct</option>
                    <option value="witty">Witty & Casual</option>
                    <option value="urgent">Urgent & Action-Oriented</option>
                    <option value="empathetic">Empathetic & Caring</option>
                </select>
            </div>

            <button type="button" id="generate_btn" class="btn btn-primary" onclick="generateCopy()" style="font-weight: 600; width: 100%; padding: 10px;">
                <svg style="width: 16px; height: 16px; margin-right: 8px; display: inline-block; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                Generate Copy
            </button>
        </form>
    </div>

    <!-- Right Pane: Output -->
    <div class="card" style="padding: 24px; min-height: 400px; display: flex; flex-direction: column;">
        <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
            <span class="card-title">Generated Output</span>
        </div>
        
        <div id="output_area" style="display: none; flex-direction: column; gap: 16px;">
            <div>
                <label class="form-label">Subject Line</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="out_subject" class="form-control" readonly style="background: var(--bg-color);">
                    <button class="btn btn-secondary" onclick="copyToClipboard('out_subject')">Copy</button>
                </div>
            </div>
            <div>
                <label class="form-label">Email Body</label>
                <div style="display: flex; gap: 8px; align-items: flex-start;">
                    <textarea id="out_body" class="form-control" rows="10" readonly style="background: var(--bg-color); line-height: 1.6;"></textarea>
                    <button class="btn btn-secondary" onclick="copyToClipboard('out_body')">Copy</button>
                </div>
            </div>
            
            <div style="margin-top: 16px; padding: 16px; background: rgba(56, 189, 248, 0.1); border-radius: 8px; border: 1px solid rgba(56, 189, 248, 0.2);">
                <p style="margin: 0; color: #38bdf8; font-size: 0.9rem;"><strong>Tip:</strong> Copy these into the Campaign Editor. In the future, this will export directly to a draft campaign.</p>
            </div>
        </div>
        
        <div id="loading_area" style="display: none; flex-grow: 1; align-items: center; justify-content: center; flex-direction: column; color: var(--text-muted);">
            <svg style="animation: spin 1s linear infinite; width: 32px; height: 32px; margin-bottom: 16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p>Our AI is crafting the perfect message...</p>
        </div>
        
        <div id="empty_area" style="flex-grow: 1; display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
            <p>Fill out the brief and click Generate to see magic happen.</p>
        </div>
    </div>
</div>

<style>
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>

<script>
function generateCopy() {
    const prompt = document.getElementById('prompt').value.trim();
    if (!prompt) {
        alert("Please provide a prompt.");
        return;
    }
    
    document.getElementById('empty_area').style.display = 'none';
    document.getElementById('output_area').style.display = 'none';
    document.getElementById('loading_area').style.display = 'flex';
    
    const btn = document.getElementById('generate_btn');
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('prompt', prompt);
    formData.append('audience', document.getElementById('audience').value);
    formData.append('tone', document.getElementById('tone').value);
    
    fetch('/ai-copywriter/generate', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        document.getElementById('loading_area').style.display = 'none';
        
        if (data.success) {
            document.getElementById('out_subject').value = data.subject;
            document.getElementById('out_body').value = data.body;
            document.getElementById('output_area').style.display = 'flex';
        } else {
            alert("Error: " + data.error);
            document.getElementById('empty_area').style.display = 'flex';
        }
    })
    .catch(err => {
        btn.disabled = false;
        document.getElementById('loading_area').style.display = 'none';
        document.getElementById('empty_area').style.display = 'flex';
        alert("A network error occurred.");
    });
}

function copyToClipboard(id) {
    const el = document.getElementById(id);
    el.select();
    document.execCommand('copy');
    alert("Copied to clipboard!");
}
</script>
