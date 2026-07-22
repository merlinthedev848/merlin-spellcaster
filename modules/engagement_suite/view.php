<?php
declare(strict_types=1);

$currentTab = $_GET['tab'] ?? 'abtesting';
?>

<div class="header-actions" style="margin-bottom: 20px;">
    <div class="page-title">
        <h1>Engagement & Survey Powerhouse Suite</h1>
        <p>Run split tests on subject lines and body copy, collect user feedback with surveys, and optimize conversions.</p>
    </div>
</div>

<div style="display: flex; gap: 4px; border-bottom: 1px solid var(--theme-border); margin-bottom: 24px;">
    <button class="btn engagement-tab-btn <?= $currentTab === 'abtesting' ? 'active' : '' ?>" onclick="switchEngagementTab(event, 'tab-abtesting')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'abtesting' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'abtesting' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        🧪 A/B Split Testing
    </button>
    <button class="btn engagement-tab-btn <?= $currentTab === 'surveys' ? 'active' : '' ?>" onclick="switchEngagementTab(event, 'tab-surveys')" style="border: none; border-bottom: 2px solid <?= $currentTab === 'surveys' ? 'var(--theme-blurple)' : 'transparent' ?>; background: transparent; padding: 12px 18px; border-radius: 0; color: <?= $currentTab === 'surveys' ? 'var(--theme-blurple)' : 'var(--theme-dark-slate)' ?>; font-weight: 600; cursor: pointer;">
        📋 Interactive Web Surveys
    </button>
</div>

<div id="tab-abtesting" class="engagement-tab-content" style="display: <?= $currentTab === 'abtesting' ? 'block' : 'none' ?>;">
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
            <span class="card-title">A/B Split Test Experiments</span>
        </div>
        <p style="font-size: 13px; color: var(--theme-dark-slate); line-height: 1.5;">
            Test variant subject lines and body copy on sample audiences before sending to your full broadcast list.
        </p>
    </div>
</div>

<div id="tab-surveys" class="engagement-tab-content" style="display: <?= $currentTab === 'surveys' ? 'block' : 'none' ?>;">
    <div class="card" style="padding: 24px;">
        <div class="card-header" style="margin-bottom: 16px; border-bottom: 1px solid var(--theme-border); padding-bottom: 12px;">
            <span class="card-title">Interactive Surveys & Forms</span>
        </div>
        <p style="font-size: 13px; color: var(--theme-dark-slate); line-height: 1.5;">
            Build interactive multi-question surveys and gather audience feedback directly into contact profiles.
        </p>
    </div>
</div>

<script>
function switchEngagementTab(e, tabId) {
    document.querySelectorAll('.engagement-tab-btn').forEach(btn => {
        btn.style.borderBottomColor = 'transparent';
        btn.style.color = 'var(--theme-dark-slate)';
    });
    e.currentTarget.style.borderBottomColor = 'var(--theme-blurple)';
    e.currentTarget.style.color = 'var(--theme-blurple)';
    document.querySelectorAll('.engagement-tab-content').forEach(c => c.style.display = 'none');
    document.getElementById(tabId).style.display = 'block';
}
</script>
