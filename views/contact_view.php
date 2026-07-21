<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <a href="<?= e(getSetting('app_url')) ?>/contacts" style="color: var(--theme-dark-slate); font-weight: 500; font-size: 13px; text-decoration: none; display: flex; align-items: center; gap: 4px; margin-bottom: 8px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Contacts
        </a>
        <h1><?= e($contact['first_name'] . ' ' . $contact['last_name']) ?: e($contact['email']) ?></h1>
        <p>HubSpot-style unified CRM contact profile and timeline history.</p>
    </div>
    <div style="display: flex; gap: 8px; align-items: center;">
        <a href="<?= e(getSetting('app_url')) ?>/contacts/view?action=export_contact&id=<?= e($contact['id']) ?>" class="btn btn-secondary" style="font-weight: 600;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align: middle; margin-right: 4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Export Profile Data (GDPR)
        </a>
        <form method="post" action="<?= e(getSetting('app_url')) ?>/contacts?action=delete_contact&id=<?= e($contact['id']) ?>" onsubmit="return confirm('Are you sure you want to permanently delete this contact? This will remove all their tag mappings, list memberships, and activity timelines.');" style="display: inline; margin: 0;">
            <?= Auth::csrfField() ?>
            <button type="submit" class="btn btn-danger"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>Delete Contact</button>
        </form>
    </div>
</div>

<div class="grid" style="grid-template-columns: 1fr 2fr;">
    <!-- Profile Card Details -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <div class="card">
            <div class="card-header"><span class="card-title">Contact Information</span></div>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Email Address</span>
                    <span style="font-weight: 600; color: var(--theme-dark);"><?= e($contact['email']) ?></span>
                </div>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">First Name</span>
                    <span><?= e($contact['first_name']) ?: '—' ?></span>
                </div>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Last Name</span>
                    <span><?= e($contact['last_name']) ?: '—' ?></span>
                </div>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Deliverability Status</span>
                    <span class="badge badge-<?= e($contact['status']) ?>"><?= e($contact['status']) ?></span>
                </div>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Joined At</span>
                    <span style="color: var(--theme-dark-slate);"><?= date('M j, Y H:i:s', strtotime($contact['created_at'])) ?></span>
                </div>
                <?php if (isset($heatScore)): ?>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Engagement Heat Score</span>
                    <span class="badge" style="font-weight: 700; background-color: rgba(255, 99, 132, 0.1); color: #ff6384; border: 1px solid rgba(255, 99, 132, 0.15); font-size: 14px;">🔥 <?= (int)$heatScore ?> pts</span>
                </div>
                <!-- Heat Score Visualization Chart -->
                <div style="margin-top: 16px;">
                    <canvas id="heatChart" height="150"></canvas>
                </div>
                <script>
                    const ctx = document.getElementById('heatChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: <?= json_encode($dates ?? [], JSON_HEX_TAG | JSON_HEX_AMP) ?>,
                            datasets: [
                                { label: 'Opens', data: <?= json_encode($openSeries ?? [], JSON_HEX_TAG | JSON_HEX_AMP) ?>, borderColor: '#3b82f6', tension: 0.3, fill: false },
                                { label: 'Clicks', data: <?= json_encode($clickSeries ?? [], JSON_HEX_TAG | JSON_HEX_AMP) ?>, borderColor: '#10b981', tension: 0.3, fill: false },
                                { label: 'Visits', data: <?= json_encode($visitSeries ?? [], JSON_HEX_TAG | JSON_HEX_AMP) ?>, borderColor: '#ff6384', tension: 0.3, fill: false }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { display: false },
                                tooltip: { mode: 'index', intersect: false }
                            },
                            scales: {
                                y: { beginAtZero: true, ticks: { precision: 0 } },
                                x: { display: false }
                            }
                        }
                    });
                </script>
                <?php endif; ?>

                <?php if (!empty($contact['ip_address'])): ?>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Last Known IP</span>
                    <code style="font-family: monospace; font-size: 12px; background-color: var(--theme-bg); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--theme-border);"><?= e($contact['ip_address']) ?></code>
                </div>
                <?php endif; ?>

                <?php if (!empty($contact['country_code'])): ?>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--theme-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Geographic Location</span>
                    <span style="display: inline-flex; align-items: center; gap: 8px; font-weight: 500; color: var(--theme-dark);">
                        <img src="https://flagcdn.com/w20/<?= e(strtolower($contact['country_code'])) ?>.png" alt="<?= e($contact['country_name']) ?>" style="border-radius: 2px; border: 1px solid var(--theme-border);" width="20">
                        <?= e($contact['city']) ? e($contact['city']) . ', ' : '' ?><?= e($contact['country_name']) ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CRM Tags Card -->
        <div class="card">
            <div class="card-header"><span class="card-title">Assigned CRM Tags</span></div>
            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                <?php if (empty($contactTags)): ?>
                    <span style="color: var(--theme-dark-slate); font-size: 13px;">No tags assigned.</span>
                <?php else: ?>
                    <?php foreach ($contactTags as $t): 
                        $cColor = $t['color'] ?? '#635bff';
                    ?>
                        <span style="font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 20px; background-color: <?= e($cColor) ?>20; color: <?= e($cColor) ?>; border: 1px solid <?= e($cColor) ?>30;">
                            <?= e($t['name']) ?>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Assigned Lists Card -->
        <div class="card">
            <div class="card-header"><span class="card-title">Assigned Segments</span></div>
            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                <?php if (empty($lists)): ?>
                    <span style="color: var(--theme-dark-slate); font-size: 13px;">No lists assigned.</span>
                <?php else: ?>
                    <?php foreach ($lists as $l): ?>
                        <span style="background-color: var(--theme-blurple-light); color: var(--theme-blurple); font-weight: 600; font-size: 12px; padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(99,91,255,0.1);"><?= e($l['name']) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Web Pages Card -->
        <div class="card">
            <div class="card-header"><span class="card-title">Top Visited Pages</span></div>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php if (empty($topPages)): ?>
                    <span style="color: var(--theme-dark-slate); font-size: 13px;">No website visits tracked yet. Ensure the tracking pixel is installed on your site!</span>
                <?php else: ?>
                    <table style="width: 100%; font-size: 13px; text-align: left; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--theme-border);">
                                <th style="padding: 8px 4px; color: var(--theme-dark-slate);">URL</th>
                                <th style="padding: 8px 4px; color: var(--theme-dark-slate); width: 60px; text-align: right;">Visits</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topPages as $tp): ?>
                                <tr style="border-bottom: 1px solid var(--theme-border);">
                                    <td style="padding: 8px 4px; word-break: break-all;">
                                        <a href="<?= e($tp['url']) ?>" target="_blank" style="color: var(--theme-blurple); text-decoration: none; font-weight: 500;"><?= e($tp['url']) ?></a>
                                    </td>
                                    <td style="padding: 8px 4px; text-align: right; font-weight: 600; color: var(--theme-dark);"><?= (int)$tp['visit_count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Lead Score Breakdown Card -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <span class="card-title">⭐ Lead Score Rationale & Point Breakdown</span>
            <span style="font-weight: 700; font-size: 16px; color: var(--theme-blurple); background: var(--theme-blurple-light); padding: 4px 12px; border-radius: 20px;">
                Total Score: <?= (int)($contact['lead_score'] ?? 0) ?> pts
            </span>
        </div>
        
        <?php if (empty($scoreLogs)): ?>
            <p style="color: var(--theme-dark-slate); font-size: 13px; margin: 0; padding: 12px 0;">No detailed point logs recorded yet. Score adjustments from email opens (+1), link clicks (+5), and automations will automatically appear here with exact reasons.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 12px;">
                <?php foreach ($scoreLogs as $sLog): 
                    $pts = (int)$sLog['points_changed'];
                    $isPos = $pts > 0;
                ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--theme-bg); border-radius: 8px; border: 1px solid var(--theme-border);">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-weight: 800; font-size: 13px; padding: 4px 8px; border-radius: 6px; <?= $isPos ? 'background: rgba(16,185,129,0.15); color: #10b981;' : 'background: rgba(239,68,68,0.15); color: #ef4444;' ?>">
                                <?= $isPos ? "+{$pts}" : $pts ?> pts
                            </span>
                            <div>
                                <span style="font-weight: 600; font-size: 13px; color: var(--theme-dark); display: block;"><?= e($sLog['reason']) ?></span>
                                <span style="font-size: 11px; color: var(--theme-dark-slate);"><?= date('M j, Y H:i:s', strtotime($sLog['created_at'])) ?></span>
                            </div>
                        </div>
                        <span style="font-size: 12px; font-weight: 700; color: var(--theme-dark-slate);">
                            Resulting Score: <?= (int)$sLog['new_total_score'] ?> pts
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Timeline Event Card -->
    <div class="card">
        <div class="card-header"><span class="card-title">Event Timeline</span></div>
        <?php if (empty($activities)): ?>
            <p style="text-align: center; color: var(--theme-dark-slate); padding: 60px;">No timeline actions logged for this subscriber.</p>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($activities as $act): 
                    $class = '';
                    if ($act['activity_type'] === 'bounce') $class = 'bounce';
                    if ($act['activity_type'] === 'unsub') $class = 'unsub';
                    if ($act['activity_type'] === 'open' || $act['activity_type'] === 'click') $class = 'open';
                ?>
                    <li class="timeline-item <?= $class ?>">
                        <div class="timeline-time"><?= date('M j, Y H:i:s', strtotime($act['created_at'])) ?></div>
                        <div class="timeline-content" style="color: var(--theme-dark); font-weight: 500;">
                            <span class="badge" style="background-color: var(--theme-bg); color: var(--theme-dark-slate); font-size: 10px; padding: 2px 6px; border: 1px solid var(--theme-border); margin-right: 6px; text-transform: uppercase;"><?= e($act['activity_type']) ?></span>
                            <?= e($act['description']) ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
