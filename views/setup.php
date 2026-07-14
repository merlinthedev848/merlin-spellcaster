<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Merlin Spellcaster</title>
    <link rel="stylesheet" href="<?= e(defined('BASE_PATH') ? BASE_PATH : '') ?>/assets/css/stripe.css">
    <style>
        body {
            background-color: var(--stripe-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            min-height: 100vh;
        }
        .setup-card {
            width: 100%;
            max-width: 520px;
            margin: auto;
        }
        .setup-logo {
            text-align: center;
            margin-bottom: 24px;
            color: var(--stripe-blurple);
        }
        .setup-logo svg {
            width: 48px;
            height: 48px;
        }
        .setup-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .setup-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--stripe-dark);
            margin-bottom: 8px;
        }
        .setup-header p {
            color: var(--stripe-dark-slate);
            font-size: 14px;
        }
        .setup-section-title {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--stripe-dark-slate);
            margin: 24px 0 12px;
            border-bottom: 1px solid var(--stripe-border);
            padding-bottom: 6px;
        }
    </style>
</head>
<body>
    <div class="setup-card card">
        <div class="setup-logo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
            </svg>
        </div>
        
        <div class="setup-header">
            <h1>Install Merlin Spellcaster</h1>
            <p>Configure your server and administrative account to begin.</p>
        </div>

        <?php if (isset($error)): ?>
            <div style="background-color: var(--danger-light); color: var(--danger); border: 1px solid rgba(255,91,96,0.1); border-radius: 6px; padding: 12px; font-size: 13px; font-weight: 500; margin-bottom: 24px;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="setup-section-title">Database Settings</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="db_host">Database Host</label>
                    <input class="form-control" type="text" id="db_host" name="db_host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="db_port">Database Port</label>
                    <input class="form-control" type="number" id="db_port" name="db_port" value="3306" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="db_name">Database Name</label>
                <input class="form-control" type="text" id="db_name" name="db_name" placeholder="e.g. merlin_db" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="db_user">Database Username</label>
                <input class="form-control" type="text" id="db_user" name="db_user" placeholder="e.g. root" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="db_pass">Database Password</label>
                <input class="form-control" type="password" id="db_pass" name="db_pass" placeholder="e.g. yourpassword">
            </div>

            <div class="setup-section-title">Admin Account</div>

            <div class="form-group">
                <label class="form-label" for="admin_email">Admin Email Address</label>
                <input class="form-control" type="email" id="admin_email" name="admin_email" placeholder="e.g. admin@domain.com" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="admin_pass">Admin Password</label>
                <input class="form-control" type="password" id="admin_pass" name="admin_pass" placeholder="Choose a secure password" required>
            </div>

            <div class="setup-section-title">General</div>

            <div class="form-group" style="margin-bottom: 32px;">
                <label class="form-label" for="app_url">App Installation URL</label>
                <?php
                $detectedUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
                ?>
                <input class="form-control" type="url" id="app_url" name="app_url" value="<?= e($detectedUrl) ?>" required>
                <p style="font-size: 11px; color: var(--stripe-dark-slate); margin-top: 4px;">Make sure this URL matches where the platform is located on your server.</p>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 14px; font-weight: 600;">Complete Installation & Setup →</button>
        </form>
    </div>
</body>
</html>
