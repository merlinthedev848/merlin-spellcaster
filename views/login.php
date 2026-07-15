<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in to Merlin Spellcaster</title>
    <link rel="stylesheet" href="<?= e(defined('BASE_PATH') ? BASE_PATH : '') ?>/assets/css/theme.css">
    <style>
        body {
            background-color: var(--theme-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            min-height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 440px;
            margin: auto;
            border-radius: 12px;
            box-shadow: 0 50px 100px -20px rgba(50,50,93,0.1), 0 30px 60px -30px rgba(0,0,0,0.15), 0 -18px 60px -10px rgba(0,0,0,0.018);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 24px;
            color: var(--theme-blurple);
        }
        .login-logo svg {
            width: 40px;
            height: 40px;
        }
        .login-header {
            margin-bottom: 28px;
        }
        .login-header h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--theme-dark);
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }
        .login-header p {
            color: var(--theme-dark-slate);
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="login-card card">
        <div class="login-logo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
            </svg>
        </div>
        
        <div class="login-header">
            <h1>Sign in to your account</h1>
            <p>Access your automated marketing flows and analytics dashboard.</p>
        </div>

        <?php if (isset($_SESSION['flash_success'])): ?>
            <div style="background-color: var(--success-light); color: var(--success); border: 1px solid rgba(0,212,178,0.1); border-radius: 6px; padding: 12px; font-size: 13px; font-weight: 500; margin-bottom: 20px; text-align: left;">
                <?= e($_SESSION['flash_success']) ?>
                <?php unset($_SESSION['flash_success']); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background-color: var(--danger-light); color: var(--danger); border: 1px solid rgba(255,91,96,0.1); border-radius: 6px; padding: 12px; font-size: 13px; font-weight: 500; margin-bottom: 20px; text-align: left;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input class="form-control" type="email" id="email" name="email" required placeholder="you@example.com">
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <label class="form-label" for="password" style="margin-bottom: 0;">Password</label>
                </div>
                <input class="form-control" type="password" id="password" name="password" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 14px; font-weight: 600;">Sign In →</button>
        </form>
    </div>
</body>
</html>
