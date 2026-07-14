<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <a href="<?= e(getSetting('app_url')) ?>/contacts" style="color: var(--stripe-dark-slate); font-weight: 500; font-size: 13px; text-decoration: none; display: flex; align-items: center; gap: 4px; margin-bottom: 8px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Contacts
        </a>
        <h1><?= e($contact['first_name'] . ' ' . $contact['last_name']) ?: e($contact['email']) ?></h1>
        <p>HubSpot-style unified CRM contact profile and timeline history.</p>
    </div>
    <div style="display: flex; gap: 8px; align-items: center;">
        <a href="<?= e(getSetting('app_url')) ?>/contacts/view?action=export_contact&id=<?= $contact['id'] ?>" class="btn btn-secondary" style="font-weight: 600;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align: middle; margin-right: 4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Export Profile Data (GDPR)
        </a>
        <form method="post" action="<?= e(getSetting('app_url')) ?>/contacts?action=delete_contact&id=<?= $contact['id'] ?>" onsubmit="return confirm('Are you sure you want to permanently delete this contact? This will remove all their tag mappings, list memberships, and activity timelines.');" style="display: inline; margin: 0;">
            <button type="submit" class="btn btn-danger">Delete Contact</button>
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
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--stripe-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Email Address</span>
                    <span style="font-weight: 600; color: var(--stripe-dark);"><?= e($contact['email']) ?></span>
                </div>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--stripe-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">First Name</span>
                    <span><?= e($contact['first_name']) ?: '—' ?></span>
                </div>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--stripe-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Last Name</span>
                    <span><?= e($contact['last_name']) ?: '—' ?></span>
                </div>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--stripe-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Deliverability Status</span>
                    <span class="badge badge-<?= e($contact['status']) ?>"><?= e($contact['status']) ?></span>
                </div>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--stripe-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Joined At</span>
                    <span style="color: var(--stripe-dark-slate);"><?= date('M j, Y H:i:s', strtotime($contact['created_at'])) ?></span>
                </div>
                <?php if (isset($contact['lead_score'])): ?>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--stripe-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Behavioral Lead Score</span>
                    <span class="badge" style="font-weight: 700; background-color: rgba(99, 91, 255, 0.1); color: var(--stripe-blurple); border: 1px solid rgba(99, 91, 255, 0.15);"><?= (int)$contact['lead_score'] ?> pts</span>
                </div>
                <?php endif; ?>

                <?php if (!empty($contact['ip_address'])): ?>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--stripe-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Last Known IP</span>
                    <code style="font-family: monospace; font-size: 12px; background-color: var(--stripe-bg); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--stripe-border);"><?= e($contact['ip_address']) ?></code>
                </div>
                <?php endif; ?>

                <?php if (!empty($contact['country_code'])): ?>
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; color: var(--stripe-dark-slate); font-weight: 600; display: block; margin-bottom: 4px;">Geographic Location</span>
                    <span style="display: inline-flex; align-items: center; gap: 8px; font-weight: 500; color: var(--stripe-dark);">
                        <img src="https://flagcdn.com/w20/<?= e(strtolower($contact['country_code'])) ?>.png" alt="<?= e($contact['country_name']) ?>" style="border-radius: 2px; border: 1px solid var(--stripe-border);" width="20">
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
                    <span style="color: var(--stripe-dark-slate); font-size: 13px;">No tags assigned.</span>
                <?php else: ?>
                    <?php foreach ($contactTags as $t): ?>
                        <span style="font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 20px; background-color: <?= e($t['color']) ?>20; color: <?= e($t['color']) ?>; border: 1px solid <?= e($t['color']) ?>30;">
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
                    <span style="color: var(--stripe-dark-slate); font-size: 13px;">No lists assigned.</span>
                <?php else: ?>
                    <?php foreach ($lists as $l): ?>
                        <span style="background-color: var(--stripe-blurple-light); color: var(--stripe-blurple); font-weight: 600; font-size: 12px; padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(99,91,255,0.1);"><?= e($l['name']) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Timeline Event Card -->
    <div class="card">
        <div class="card-header"><span class="card-title">Event Timeline</span></div>
        <?php if (empty($activities)): ?>
            <p style="text-align: center; color: var(--stripe-dark-slate); padding: 60px;">No timeline actions logged for this subscriber.</p>
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
                        <div class="timeline-content" style="color: var(--stripe-dark); font-weight: 500;">
                            <span class="badge" style="background-color: var(--stripe-bg); color: var(--stripe-dark-slate); font-size: 10px; padding: 2px 6px; border: 1px solid var(--stripe-border); margin-right: 6px; text-transform: uppercase;"><?= e($act['activity_type']) ?></span>
                            <?= e($act['description']) ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
