<?php
declare(strict_types=1);
?>

<div class="header-actions" style="margin-bottom: 20px;">
    <div class="page-title">
        <h1>Lead Scoring & Predictive Analytics</h1>
        <p>Model contact purchase intent, view conversion statuses, and manage point scoring metrics rules.</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" style="font-weight: 600; padding: 10px 20px;" onclick="recalculateScores()">
            ⚡ Recalculate & Score All Contacts
        </button>
    </div>
</div>

<!-- Tabs Row -->
<div style="display: flex; gap: 4px; border-bottom: 1px solid var(--theme-border); margin-bottom: 24px;">
    <button class="btn scoring-tab-btn active" id="btn-tab-behavioral" onclick="switchScoringTab(event, 'tab-behavioral')" style="border: none; border-bottom: 2px solid var(--theme-blurple); background: transparent; padding: 12px 18px; border-radius: 0; color: var(--theme-blurple); font-weight: 600; cursor: pointer;">
        📈 Behavioral Scoring Rules
    </button>
    <button class="btn scoring-tab-btn" id="btn-tab-predictive" onclick="switchScoringTab(event, 'tab-predictive')" style="border: none; border-bottom: 2px solid transparent; background: transparent; padding: 12px 18px; border-radius: 0; color: var(--theme-dark-slate); font-weight: 600; cursor: pointer;">
        🧠 Predictive Intent Models
    </button>
</div>

<!-- TAB 1: Behavioral Scoring Rules -->
<div id="tab-behavioral" class="scoring-tab-content">
    <div class="grid grid-1-3" style="align-items: start; gap: 24px; margin-bottom: 24px;">
        <!-- Left: Rule Definition Card -->
        <div class="card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
                <span class="card-title">Default Points Rules</span>
            </div>
            
            <p style="font-size: 13px; color: var(--theme-dark-slate); line-height: 1.5; margin-bottom: 20px;">
                Behavioral rules dynamically adjust lead scores inside the CRM as contacts interact with your campaign content:
            </p>

            <ul style="list-style: none; padding: 0; margin: 0 0 24px 0; display: flex; flex-direction: column; gap: 10px;">
                <li style="display: flex; justify-content: space-between; padding: 10px 14px; background: var(--theme-bg); border: 1px solid var(--theme-border); border-radius: 6px; font-size: 13px; font-weight: 500;">
                    <span>Email Opened</span>
                    <span style="color: var(--success); font-weight: 700;">+1 Point</span>
                </li>
                <li style="display: flex; justify-content: space-between; padding: 10px 14px; background: var(--theme-bg); border: 1px solid var(--theme-border); border-radius: 6px; font-size: 13px; font-weight: 500;">
                    <span>Link Clicked</span>
                    <span style="color: var(--success); font-weight: 700;">+5 Points</span>
                </li>
                <li style="display: flex; justify-content: space-between; padding: 10px 14px; background: var(--theme-bg); border: 1px solid var(--theme-border); border-radius: 6px; font-size: 13px; font-weight: 500;">
                    <span>Campaign Unsubscribed</span>
                    <span style="color: var(--danger); font-weight: 700;">Reset (0)</span>
                </li>
            </ul>

            <div style="background-color: var(--theme-blurple-light); border: 1px solid rgba(99,91,255,0.1); border-radius: 6px; padding: 12px; font-size: 12px; color: var(--theme-dark-slate); line-height: 1.4;">
                💡 <strong>Automation Trigger:</strong> You can setup active workflows using the trigger condition <code>points_threshold:[number]</code> (e.g. <code>points_threshold:50</code>) to automate sales outreach when contacts get hot!
            </div>
        </div>

        <!-- Right: Top Scoring Contacts Table -->
        <div class="card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 16px; border-bottom: none; padding-bottom: 0;">
                <span class="card-title">Top Active Leads (CRM Points)</span>
            </div>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Email Address</th>
                            <th>Name</th>
                            <th style="text-align: center; width: 120px;">Lead Points</th>
                            <th style="text-align: right; width: 140px;">Score Rationale</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topLeads)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--theme-dark-slate);">No contacts scored yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topLeads as $lead): ?>
                                <tr>
                                    <td style="font-weight: 600; color: var(--theme-dark);"><?= e($lead['email']) ?></td>
                                    <td><?= e($lead['first_name'] . ' ' . $lead['last_name']) ?: '—' ?></td>
                                    <td style="text-align: center; font-weight: 700; color: var(--theme-blurple);">
                                        <?= (int)$lead['lead_score'] ?> pts
                                    </td>
                                    <td style="text-align: right;">
                                        <button type="button" class="btn btn-secondary" style="padding: 4px 10px; font-size: 11px; font-weight: 600;" onclick="viewScoreBreakdown(<?= (int)$lead['id'] ?>, '<?= e(addslashes($lead['email'])) ?>')">
                                            🔍 Breakdown
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Score Breakdown Modal -->
<div id="score_breakdown_modal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center;">
    <div class="card" style="max-width: 540px; width: 90%; max-height: 80vh; display: flex; flex-direction: column; position: relative; padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: var(--theme-dark);" id="modal_sub_email">Lead Score Rationale</h3>
            <button type="button" onclick="closeScoreBreakdown()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--theme-dark-slate);">✕</button>
        </div>
        <div id="modal_breakdown_content" style="overflow-y: auto; max-height: 50vh; display: flex; flex-direction: column; gap: 8px;">
            <!-- Loaded via JS -->
        </div>
    </div>
</div>

<script>
async function viewScoreBreakdown(subId, email) {
    const modal = document.getElementById('score_breakdown_modal');
    const titleEl = document.getElementById('modal_sub_email');
    const contentEl = document.getElementById('modal_breakdown_content');
    
    titleEl.textContent = 'Score Breakdown: ' + email;
    contentEl.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--theme-dark-slate);">Loading score audit trail...</p>';
    modal.style.display = 'flex';

    try {
        const resp = await fetch('<?= getSetting("app_url") ?>/scoring/breakdown?id=' + subId);
        const data = await resp.json();

        if (data.success && data.logs && data.logs.length > 0) {
            contentEl.innerHTML = data.logs.map(log => {
                const pts = parseInt(log.points_changed);
                const isPos = pts > 0;
                return `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--theme-bg); border-radius: 8px; border: 1px solid var(--theme-border);">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-weight: 800; font-size: 12px; padding: 3px 8px; border-radius: 6px; ${isPos ? 'background: rgba(16,185,129,0.15); color: #10b981;' : 'background: rgba(239,68,68,0.15); color: #ef4444;'}">
                                ${isPos ? '+' + pts : pts} pts
                            </span>
                            <div>
                                <span style="font-weight: 600; font-size: 13px; color: var(--theme-dark); display: block;">${log.reason}</span>
                                <span style="font-size: 11px; color: var(--theme-dark-slate);">${log.created_at}</span>
                            </div>
                        </div>
                        <span style="font-size: 11px; font-weight: 700; color: var(--theme-dark-slate);">Result: ${log.new_total_score} pts</span>
                    </div>
                `;
            }).join('');
        } else {
            contentEl.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--theme-dark-slate);">No specific score logs recorded for this subscriber yet.</p>';
        }
    } catch (err) {
        contentEl.innerHTML = '<p style="text-align: center; color: var(--danger); padding: 20px;">Failed to load score breakdown.</p>';
    }
}

function closeScoreBreakdown() {
    document.getElementById('score_breakdown_modal').style.display = 'none';
}
</script>

<!-- TAB 2: Predictive Intent Models -->
<div id="tab-predictive" class="scoring-tab-content" style="display: none;">
    <!-- Recalculate CTA -->
    <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
        <button class="btn btn-primary" onclick="recalculateScores()" style="font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
            🌀 Recalculate Intent Engine Models
        </button>
    </div>

    <!-- Status Cards -->
    <div class="grid grid-3" style="margin-bottom: 24px; gap: 16px;">
        <div class="card" style="padding: 16px; text-align: center;">
            <h4 style="margin: 0; color: var(--theme-dark-slate); font-size: 11px; text-transform: uppercase;">Conversion Probability (High)</h4>
            <div style="font-size: 24px; font-weight: bold; color: #10b981; margin-top: 8px;">Hot Lead Status</div>
        </div>
        <div class="card" style="padding: 16px; text-align: center;">
            <h4 style="margin: 0; color: var(--theme-dark-slate); font-size: 11px; text-transform: uppercase;">Conversion Probability (Med)</h4>
            <div style="font-size: 24px; font-weight: bold; color: #f59e0b; margin-top: 8px;">Warm Lead Status</div>
        </div>
        <div class="card" style="padding: 16px; text-align: center;">
            <h4 style="margin: 0; color: var(--theme-dark-slate); font-size: 11px; text-transform: uppercase;">Conversion Probability (Low)</h4>
            <div style="font-size: 24px; font-weight: bold; color: #64748b; margin-top: 8px;">Cold Lead Status</div>
        </div>
    </div>

    <!-- Scoring breakdown -->
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="margin-bottom: 16px; padding-bottom: 0; border-bottom: none;">
            <span class="card-title">Predictive Conversion Breakdown</span>
        </div>
        
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Email Address</th>
                        <th>Name</th>
                        <th>Engagement Score</th>
                        <th>Intent Verdict Prediction</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subscribers)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--theme-dark-slate);">No contacts scored.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subscribers as $sub): ?>
                            <tr>
                                <td style="font-weight: 600; color: var(--theme-dark);"><?= e($sub['email']) ?></td>
                                <td><?= e($sub['first_name'] . ' ' . $sub['last_name']) ?: '—' ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 100px; background: var(--theme-border); height: 8px; border-radius: 4px; overflow: hidden;">
                                            <div style="width: <?= (int)$sub['predictive_score'] ?>%; background: <?= $sub['status_color'] ?>; height: 100%;"></div>
                                        </div>
                                        <span style="font-weight: bold; font-size: 12px; color: var(--theme-dark);"><?= (int)$sub['predictive_score'] ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <span style="color: <?= $sub['status_color'] ?>; font-weight: 700; font-size: 12px;">
                                        <?= e($sub['conversion_status']) ?>
                                    </span>
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
    // Tab switching controller
    function switchScoringTab(event, tabId) {
        const contents = document.querySelectorAll(".scoring-tab-content");
        contents.forEach(c => c.style.display = "none");

        const buttons = document.querySelectorAll(".scoring-tab-btn");
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

    // Auto-select tab on reload
    window.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab');
        if (tab && document.getElementById('btn-tab-' + tab)) {
            document.getElementById('btn-tab-' + tab).click();
        }
    });

    // Model recalculate AJAX trigger
    function recalculateScores() {
        if (confirm("Run machine learning scoring models over all contact history?")) {
            fetch('<?= e(BASE_PATH) ?>/scoring/recalculate')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert("Error: " + data.error);
                }
            })
            .catch(() => alert("Recalculation connection failure."));
        }
    }
</script>
