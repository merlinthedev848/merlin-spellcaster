<?php
declare(strict_types=1);
?>
<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 60vh; text-align: center;">
    <h1 style="font-size: 72px; font-weight: 700; color: var(--theme-blurple); margin-bottom: 12px;">404</h1>
    <h2 style="font-size: 20px; font-weight: 600; color: var(--theme-dark); margin-bottom: 8px;">Page Not Found</h2>
    <p style="color: var(--theme-dark-slate); margin-bottom: 24px; max-width: 400px;">The page you are looking for does not exist or has been moved to a new route.</p>
    <a href="<?= e(getSetting('app_url')) ?>/" class="btn btn-primary">Return to Dashboard</a>
</div>
