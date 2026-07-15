<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($form['headline'] ?? 'Subscribe') ?> — <?= e($appName) ?></title>
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
        .subscribe-card {
            width: 100%;
            max-width: 480px;
            margin: auto;
            border-radius: 12px;
            box-shadow: 0 50px 100px -20px rgba(50,50,93,0.08), 0 30px 60px -30px rgba(0,0,0,0.12), 0 -18px 60px -10px rgba(0,0,0,0.015);
            padding: 32px;
            background-color: white;
            border: 1px solid var(--theme-border);
        }
        .subscribe-icon {
            font-size: 40px;
            margin-bottom: 20px;
            color: var(--theme-blurple);
            text-align: center;
        }
        .subscribe-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .subscribe-header h1 {
            font-size: 22px;
            font-weight: 800;
            color: var(--theme-dark);
            letter-spacing: -0.5px;
            margin-bottom: 8px;
            line-height: 1.3;
        }
        .subscribe-header p {
            color: var(--theme-dark-slate);
            font-size: 13px;
            line-height: 1.6;
        }
        .success-box {
            text-align: center;
        }
        .success-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background-color: var(--success-light);
            color: var(--success);
            font-size: 24px;
            margin-bottom: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="subscribe-card">
        <?php if ($success): ?>
            <div class="success-box">
                <div class="success-badge">✓</div>
                <h1 style="font-size: 20px; font-weight: 800; color: var(--theme-dark); margin-bottom: 12px; line-height: 1.3;">Subscription Confirmed</h1>
                <p style="color: var(--theme-dark-slate); font-size: 13px; line-height: 1.6; margin-bottom: 24px;"><?= e($form['success_message']) ?></p>
                
                <?php if ($form['download_url']): ?>
                    <a href="<?= e($form['download_url']) ?>" class="btn btn-primary" target="_blank" style="width: 100%; padding: 12px; font-size: 14px; font-weight: 600; justify-content: center; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(99, 91, 255, 0.25);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Download Lead Magnet Asset
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="subscribe-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 48px; height: 48px; display: inline-block;">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
            </div>
            
            <div class="subscribe-header">
                <h1><?= e($form['headline']) ?></h1>
                <?php if ($form['description']): ?>
                    <p><?= e($form['description']) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div style="background-color: var(--danger-light); color: var(--danger); border: 1px solid rgba(255,91,96,0.1); border-radius: 6px; padding: 12px; font-size: 13px; font-weight: 500; margin-bottom: 20px; text-align: left; line-height: 1.4;">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="form_id" value="<?= (int)$form['id'] ?>">
                
                <?php if ($form['show_name']): ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="first_name">First Name <?= $form['require_name'] ? '<span style="color:var(--danger)">*</span>' : '' ?></label>
                            <input class="form-control" type="text" id="first_name" name="first_name" placeholder="John" <?= $form['require_name'] ? 'required' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="last_name">Last Name</label>
                            <input class="form-control" type="text" id="last_name" name="last_name" placeholder="Doe">
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-label" for="email">Email Address *</label>
                    <input class="form-control" type="email" id="email" name="email" required placeholder="name@domain.com">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 14px; font-weight: 600; justify-content: center;"><?= e($form['button_text']) ?></button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
