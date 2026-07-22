<?php
declare(strict_types=1);

$currentTab = $_GET['tab'] ?? 'generator';
?>

<div class="header-actions" style="margin-bottom: 20px;">
    <div class="page-title">
        <h1>AI Assistant & Copywriter Powerhouse Suite</h1>
        <p>Generate high-converting B2B outreach emails, subject lines, and persuasive sales copy instantly with AI.</p>
    </div>
</div>

<div style="display: flex; gap: 4px; border-bottom: 1px solid var(--theme-border); margin-bottom: 24px;">
    <button class="btn ai-tab-btn <?= $currentTab === 'generator' ? 'active' : '' ?>" onclick="switchAiTab(event, 'tab-generator')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'generator' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'generator' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        ✍️ AI Email & Subject Generator
    </button>
    <button class="btn ai-tab-btn <?= $currentTab === 'settings' ? 'active' : '' ?>" onclick="switchAiTab(event, 'tab-settings')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'settings' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'settings' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        ⚙️ AI Provider API Keys
    </button>
</div>

<!-- TAB 1: Generator -->
<div id="tab-generator" class="ai-tab-content" style="display: <?= $currentTab === 'generator' ? 'block' : 'none' ?>;">
    <div class="grid grid-2" style="gap: 24px; align-items: start;">
        <!-- Input Form -->
        <div class="card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
                <span class="card-title">Campaign Offer & Audience Brief</span>
            </div>
            
            <div class="form-group">
                <label class="form-label">Target Audience / Client Role</label>
                <input class="form-control" type="text" id="ai_audience" value="Video Production Agencies & Creative Directors" placeholder="e.g. Video Producers, E-Learning Studios">
            </div>

            <div class="form-group">
                <label class="form-label">Your Service / Offer</label>
                <input class="form-control" type="text" id="ai_offer" value="British Voice Over & Audio Production" placeholder="e.g. British Voice Over Artist">
            </div>

            <div class="form-group">
                <label class="form-label">Copy Tone</label>
                <select class="form-control" id="ai_tone">
                    <option value="persuasive" selected>Persuasive & Professional</option>
                    <option value="urgent">Urgent & Direct</option>
                    <option value="storytelling">Storytelling & Social Proof</option>
                    <option value="casual">Casual & Conversational</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Core Benefits & Selling Points</label>
                <textarea class="form-control" id="ai_benefits" rows="3" placeholder="Broadcast quality studio, 24h turnaround, versatile tones">Broadcast quality acoustically treated studio, 24-hour turnaround, free revisions, remote direction via Zoom</textarea>
            </div>

            <button type="button" id="btn_generate_ai" class="btn btn-primary" onclick="generateAiCopy()" style="width: 100%; justify-content: center; font-weight: 700; padding: 12px;">
                ⚡ Generate AI Subject Lines & Email Variations
            </button>
        </div>

        <!-- Output Panel -->
        <div class="card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                <span class="card-title">Generated Copy Results</span>
                <span id="ai_engine_badge" class="badge" style="background: rgba(99,91,255,0.1); color: var(--theme-blurple); font-weight: 700;">AI Engine Ready</span>
            </div>

            <div id="ai_results_container" style="display: none;">
                <div style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 700; color: var(--theme-dark);">🎯 High-CTR Subject Line Ideas:</label>
                    <ul id="ai_subject_list" style="background: var(--theme-bg); padding: 12px 12px 12px 28px; border-radius: 6px; font-size: 13px; color: var(--theme-dark); line-height: 1.6; border: 1px solid var(--theme-border);">
                    </ul>
                </div>

                <div style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label class="form-label" style="font-weight: 700; color: var(--theme-dark); margin: 0;">📧 Pitch Email Variation A (Direct):</label>
                        <button class="btn btn-secondary btn-sm" onclick="copyToClipboard('ai_email_a')">Copy Code</button>
                    </div>
                    <textarea class="form-control" id="ai_email_a" rows="8" style="font-family: monospace; font-size: 12px; background: white;"></textarea>
                </div>

                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label class="form-label" style="font-weight: 700; color: var(--theme-dark); margin: 0;">📧 Pitch Email Variation B (Problem-Agitate-Solve):</label>
                        <button class="btn btn-secondary btn-sm" onclick="copyToClipboard('ai_email_b')">Copy Code</button>
                    </div>
                    <textarea class="form-control" id="ai_email_b" rows="8" style="font-family: monospace; font-size: 12px; background: white;"></textarea>
                </div>
            </div>

            <div id="ai_placeholder" style="text-align: center; color: var(--theme-dark-slate); padding: 40px 20px;">
                <span style="font-size: 32px; display: block; margin-bottom: 8px;">🤖</span>
                Fill in your offer details on the left and click "Generate AI Copy".
            </div>
        </div>
    </div>
</div>

<!-- TAB 2: Settings -->
<div id="tab-settings" class="ai-tab-content" style="display: <?= $currentTab === 'settings' ? 'block' : 'none' ?>;">
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
            <span class="card-title">AI Provider API Keys</span>
        </div>
        <form method="post" action="<?= e(getSetting('app_url')) ?>/settings">
            <?= Auth::csrfField() ?>
            <div class="form-group">
                <label class="form-label">OpenAI API Key (GPT-4o / GPT-3.5)</label>
                <input class="form-control" type="password" name="openai_api_key" value="<?= e(getSetting('openai_api_key')) ?>" placeholder="sk-proj-••••••••••••••••">
            </div>
            <button type="submit" class="btn btn-primary">Save API Credentials</button>
        </form>
    </div>
</div>

<script>
function switchAiTab(e, tabId) {
    document.querySelectorAll('.ai-tab-btn').forEach(btn => {
        btn.style.borderBottomColor = 'transparent';
        btn.style.color = 'var(--theme-dark-slate)';
    });
    e.currentTarget.style.borderBottomColor = 'var(--theme-blurple)';
    e.currentTarget.style.color = 'var(--theme-blurple)';
    document.querySelectorAll('.ai-tab-content').forEach(c => c.style.display = 'none');
    document.getElementById(tabId).style.display = 'block';
}

function generateAiCopy() {
    const btn = document.getElementById('btn_generate_ai');
    btn.disabled = true;
    btn.textContent = 'Generating AI Copy...';

    const payload = {
        audience: document.getElementById('ai_audience').value,
        offer: document.getElementById('ai_offer').value,
        tone: document.getElementById('ai_tone').value,
        benefits: document.getElementById('ai_benefits').value
    };

    fetch('<?= e(BASE_PATH) ?>/ai-copywriter/generate', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.textContent = '⚡ Generate AI Subject Lines & Email Variations';

        if (res.status === 'success') {
            document.getElementById('ai_placeholder').style.display = 'none';
            document.getElementById('ai_results_container').style.display = 'block';
            document.getElementById('ai_engine_badge').textContent = res.engine;

            const list = document.getElementById('ai_subject_list');
            list.innerHTML = '';
            res.subjects.forEach(subj => {
                const li = document.createElement('li');
                li.textContent = subj;
                list.appendChild(li);
            });

            document.getElementById('ai_email_a').value = res.email_a;
            document.getElementById('ai_email_b').value = res.email_b;
        } else {
            alert('AI Generation Error: ' + res.message);
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = '⚡ Generate AI Subject Lines & Email Variations';
        alert('Error generating copy: ' + err.message);
    });
}

function copyToClipboard(id) {
    const el = document.getElementById(id);
    el.select();
    document.execCommand('copy');
    alert('Copy snippet copied to clipboard!');
}
</script>
