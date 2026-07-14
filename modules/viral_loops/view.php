<?php
declare(strict_types=1);

$baseUrl = rtrim(getSetting('app_url'), '/');
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Viral growth program</h1>
        <p>Turn your list into a growth engine by tracking subscriber recommendations and reward milestones.</p>
    </div>
</div>

<div class="grid grid-2">
    <!-- Instructions Card -->
    <div class="card" style="display: flex; flex-direction: column; gap: 20px;">
        <div class="card-header" style="border-bottom: 1px solid var(--stripe-border); padding-bottom: 16px; margin-bottom: 0;">
            <span class="card-title">How referral loops work</span>
        </div>
        
        <p style="font-size: 13px; color: var(--stripe-dark-slate); line-height: 1.6; margin: 0;">
            Every subscriber receives a unique referral code. When sending newsletters or automated sequences, you can insert their personal share link by placing this tag:
        </p>

        <div style="background-color: #fafbfc; border: 1px solid var(--stripe-border); border-radius: 8px; padding: 12px; font-family: monospace; font-size: 13px; color: var(--stripe-blurple); font-weight: 700; text-align: center;">
            {{referral_link}}
        </div>

        <p style="font-size: 13px; color: var(--stripe-dark-slate); line-height: 1.6; margin: 0;">
            When a recipient clicks this link, the system saves their code and links any successful subscription back to the referrer, incrementing their referral score automatically.
        </p>

        <div style="background-color: rgba(99,91,255,0.04); border: 1px solid rgba(99,91,255,0.1); border-radius: 8px; padding: 16px;">
            <span style="font-size: 12px; font-weight: 700; color: var(--stripe-dark); display: block; margin-bottom: 6px;">💡 Pro Tip: Referral Milestones</span>
            <span style="font-size: 12px; color: var(--stripe-dark-slate); line-height: 1.5; display: block;">
                Use the **Automations** tool to trigger milestone rewards! For example, set up an automation event <code>referral_count_updated</code> or check scores to auto-send discount codes when referrers hit target milestones.
            </span>
        </div>
    </div>

    <!-- Leaderboard Card -->
    <div class="card" style="display: flex; flex-direction: column; gap: 16px;">
        <div class="card-header" style="border-bottom: 1px solid var(--stripe-border); padding-bottom: 16px; margin-bottom: 0;">
            <span class="card-title">Top Referrers Leaderboard</span>
        </div>

        <div class="table-wrapper" style="border: 1px solid var(--stripe-border);">
            <table>
                <thead>
                    <tr>
                        <th>Subscriber Name</th>
                        <th>Sharing Code</th>
                        <th style="width: 120px; text-align: center;">Successful Invites</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topReferrers)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: var(--stripe-dark-slate); padding: 40px;">No recommendations logged yet. Send a campaign with {{referral_link}} to begin!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topReferrers as $referrer): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: var(--stripe-dark);"><?= e($referrer['first_name'] . ' ' . $referrer['last_name']) ?: '—' ?></div>
                                    <div style="font-size: 11px; color: var(--stripe-dark-slate); font-family: monospace;"><?= e($referrer['email']) ?></div>
                                </td>
                                <td style="font-family: monospace; font-size: 12px; font-weight: 600; color: var(--stripe-dark-slate);"><?= e($referrer['referral_code']) ?></td>
                                <td style="text-align: center;">
                                    <span class="badge badge-active" style="font-size: 12px; font-weight: 700; background-color: rgba(99, 91, 255, 0.1); color: var(--stripe-blurple); padding: 4px 12px; border-radius: 12px; border: 1px solid rgba(99, 91, 255, 0.15);">
                                        <?= (int)$referrer['referral_count'] ?> referred
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
