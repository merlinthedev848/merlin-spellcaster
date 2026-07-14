<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe — <?= e($appName) ?></title>
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
        .unsub-card {
            width: 100%;
            max-width: 480px;
            margin: auto;
            text-align: center;
        }
        .unsub-icon {
            font-size: 48px;
            margin-bottom: 16px;
            color: var(--stripe-blurple);
        }
        h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--stripe-dark);
            margin-bottom: 12px;
        }
        p {
            color: var(--stripe-dark-slate);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
        }
    </style>
</head>
<body>
    <div class="unsub-card card">
        <?php if ($success): ?>
            <div class="unsub-icon">✓</div>
            <h1>Unsubscribed Successfully</h1>
            <p>You have been removed from our marketing mailing lists and will no longer receive campaigns from <strong><?= e($appName) ?></strong>.</p>
            <span style="font-size: 12px; color: var(--stripe-dark-slate);">It may take up to 24 hours to process fully.</span>
        <?php elseif ($error): ?>
            <div class="unsub-icon" style="color: var(--danger);">⚠️</div>
            <h1>Unsubscribe Error</h1>
            <p><?= e($error) ?></p>
            <a href="/" class="btn btn-secondary" style="width: 100%;">Return to Home</a>
        <?php else: ?>
            <div class="unsub-icon">✉️</div>
            <h1>Confirm Unsubscribe</h1>
            <p>Are you sure you want to unsubscribe <strong><?= e($email) ?></strong> from all future campaign mailings from <strong><?= e($appName) ?></strong>?</p>
            
            <form method="post" action="">
                <button type="submit" class="btn btn-danger" style="width: 100%; padding: 12px; margin-bottom: 12px; font-weight: 600;">Yes, Unsubscribe Me</button>
                <a href="javascript:history.back()" class="btn btn-secondary" style="width: 100%; padding: 12px;">Go Back</a>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
