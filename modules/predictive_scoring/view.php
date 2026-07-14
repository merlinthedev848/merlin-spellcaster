<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Predictive Lead Scoring</h1>
        <p>Uses engagement velocity, click patterns, and timeline data to predict purchase intent and conversion probability.</p>
    </div>
    <button class="btn btn-primary" onclick="recalculateScores()">Recalculate Intent Engine</button>
</div>

<div class="grid grid-3" style="margin-bottom: 24px;">
    <div class="card" style="padding: 16px; text-align: center;">
        <h4 style="margin: 0; color: var(--text-muted); font-size: 0.85rem;">Ready to Buy</h4>
        <div style="font-size: 2rem; font-weight: bold; color: #10b981; margin-top: 8px;">24 Subscribers</div>
    </div>
    <div class="card" style="padding: 16px; text-align: center;">
        <h4 style="margin: 0; color: var(--text-muted); font-size: 0.85rem;">Nurturing Phase</h4>
        <div style="font-size: 2rem; font-weight: bold; color: #f59e0b; margin-top: 8px;">148 Subscribers</div>
    </div>
    <div class="card" style="padding: 16px; text-align: center;">
        <h4 style="margin: 0; color: var(--text-muted); font-size: 0.85rem;">Idle / Cold</h4>
        <div style="font-size: 2rem; font-weight: bold; color: #64748b; margin-top: 8px;">89 Subscribers</div>
    </div>
</div>

<div class="card" style="padding: 24px;">
    <div class="card-header" style="margin-bottom: 16px; padding-bottom: 0; border-bottom: none;">
        <span class="card-title">Subscriber Scoring Breakdown</span>
    </div>
    
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Email Address</th>
                    <th>Name</th>
                    <th>Engagement Score</th>
                    <th>Intent Prediction</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subscribers)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--text-muted);">No contacts available to score.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subscribers as $sub): ?>
                        <tr>
                            <td style="font-weight: 600;"><?= e($sub['email']) ?></td>
                            <td><?= e($sub['first_name'] . ' ' . $sub['last_name']) ?: '—' ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 100px; background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden;">
                                        <div style="width: <?= $sub['predictive_score'] ?>%; background: <?= $sub['status_color'] ?>; height: 100%;"></div>
                                    </div>
                                    <span style="font-weight: bold;"><?= $sub['predictive_score'] ?>%</span>
                                </div>
                            </td>
                            <td>
                                <span style="color: <?= $sub['status_color'] ?>; font-weight: 600;">
                                    <?= $sub['conversion_status'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function recalculateScores() {
    if (confirm("Run machine learning scoring models over all contact history?")) {
        fetch('/predictive-scoring/recalculate')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert("Error: " + data.error);
            }
        })
        .catch(err => alert("Network error."));
    }
}
</script>
