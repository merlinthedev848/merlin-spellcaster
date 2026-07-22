<?php
declare(strict_types=1);

// --- Viral Loops: Capture referral code globally ---
if (isset($_GET['ref']) && session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['referred_by_code'] = trim($_GET['ref']);
}

// --- Viral Loops: Bootstrap referral schema ---
try {
    $db = Database::getConnection();
    $db->exec("ALTER TABLE subscribers ADD COLUMN referral_code VARCHAR(20) UNIQUE DEFAULT NULL");
    $db->exec("ALTER TABLE subscribers ADD COLUMN referred_by INT DEFAULT NULL");
    $db->exec("ALTER TABLE subscribers ADD COLUMN referral_count INT DEFAULT 0");
} catch (PDOException $e) {
    // Columns already exist
}

// --- Link Rotator: Bootstrap rotators schema ---
try {
    $db = Database::getConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS mod_link_rotators (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) UNIQUE NOT NULL,
            destinations TEXT NOT NULL,
            clicks INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (PDOException $e) {
    // Table already exists
}

// Helper to generate unique referral code
function generateUniqueReferralCode(PDO $db): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $st = $db->prepare("SELECT COUNT(*) FROM subscribers WHERE referral_code = ?");
    do {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $st->execute([$code]);
        $exists = ((int)$st->fetchColumn()) > 0;
    } while ($exists);
    return $code;
}

// Hook: contact_added (Viral Loops)
Hook::register('contact_added', function($data) {
    try {
        $db = Database::getConnection();
        $subId = (int)($data['subscriber_id'] ?? 0);
        if ($subId <= 0) return;

        $myCode = generateUniqueReferralCode($db);
        $db->prepare("UPDATE subscribers SET referral_code = ? WHERE id = ? AND referral_code IS NULL")
           ->execute([$myCode, $subId]);

        $refCode = $_SESSION['referred_by_code'] ?? null;
        if ($refCode) {
            $stRef = $db->prepare("SELECT id, email, referral_count FROM subscribers WHERE referral_code = ?");
            $stRef->execute([$refCode]);
            $referrer = $stRef->fetch();

            if ($referrer && (int)$referrer['id'] !== $subId) {
                $refId = (int)$referrer['id'];
                $db->prepare("UPDATE subscribers SET referred_by = ? WHERE id = ?")->execute([$refId, $subId]);
                $db->prepare("UPDATE subscribers SET referral_count = referral_count + 1 WHERE id = ?")->execute([$refId]);

                logActivity($refId, 'subscribe', "Referred new user: subscriber ID #{$subId}");
                logActivity($subId, 'subscribe', "Signed up via referral code '{$refCode}' from subscriber ID #{$refId}");
                unset($_SESSION['referred_by_code']);
            }
        }
    } catch (Throwable $e) {
        error_log("ViralLoops contact_added hook error: " . $e->getMessage());
    }
});

// Hook: before_render_email (Viral Loops {{referral_link}})
Hook::register('before_render_email', function(&$data) {
    try {
        $db = Database::getConnection();
        $subId = (int)($data['subscriber_id'] ?? 0);
        if ($subId <= 0) return;

        $st = $db->prepare("SELECT referral_code FROM subscribers WHERE id = ?");
        $st->execute([$subId]);
        $code = $st->fetchColumn();

        if (!$code) {
            $code = generateUniqueReferralCode($db);
            $db->prepare("UPDATE subscribers SET referral_code = ? WHERE id = ?")->execute([$code, $subId]);
        }

        $appUrl = rtrim($data['vars']['app_url'], '/');
        $stForm = $db->query("SELECT id FROM forms ORDER BY id ASC LIMIT 1");
        $formId = $stForm->fetchColumn();
        
        if ($formId) {
            $refLink = "{$appUrl}/subscribe?form={$formId}&ref={$code}";
        } else {
            $refLink = "{$appUrl}/subscribe?ref={$code}";
        }
        $data['vars']['referral_link'] = $refLink;
    } catch (Throwable $e) {
        error_log("ViralLoops before_render_email hook error: " . $e->getMessage());
    }
});

// Hook: campaign_form_after_subject (FOMO Timer helper box)
Hook::register('campaign_form_after_subject', function() {
    $baseUrl = rtrim(getSetting('app_url'), '/');
    $sampleDate = date('Y-m-d', strtotime('+3 days')) . 'T23:59:59';
    $embedUrl = $baseUrl . '/fomo/render?end=' . urlencode($sampleDate);
    ?>
    <div class="form-group" style="margin-top: 16px; background-color: rgba(255, 91, 96, 0.05); border: 1px dashed rgba(255, 91, 96, 0.2); border-radius: 8px; padding: 16px;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
            <span style="font-size: 16px;">⏰</span>
            <strong style="font-size: 13px; color: var(--theme-dark);">Dynamic Countdown Timer (FOMO)</strong>
        </div>
        <p style="font-size: 12px; color: var(--theme-dark-slate); line-height: 1.5; margin-bottom: 8px;">
            Drive urgency by embedding a live countdown clock in your campaign email body. Copy the HTML code below and insert it into your HTML body where you want the clock to display:
        </p>
        <input class="form-control" type="text" readonly value='<img src="<?= e($embedUrl) ?>" alt="Countdown" width="600" style="display:block; max-width:100%; border-radius:8px;">' onclick="this.select()" style="font-family: monospace; font-size: 12px; margin-bottom: 0; background-color: white;">
        <span style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 4px; display: block;">You can change the <code>end</code> URL parameter date value to adjust your target deadline.</span>
    </div>
    <?php
});

// Redirect aliases
if (in_array($routePath, ['/link-rotator', '/utm-builder', '/web-personalization'], true)) {
    $aliasTab = match($routePath) {
        '/link-rotator' => 'rotators',
        '/utm-builder' => 'utm',
        '/web-personalization' => 'personalization',
        default => 'timers'
    };
    header('Location: ' . getSetting('app_url') . '/conversions?tab=' . $aliasTab);
    exit;
}

// Route: /conversions
if ($routePath === '/conversions') {
    $db = Database::getConnection();
    
    // Action: CRUD for link rotators
    $action = $_GET['action'] ?? '';
    $id = (int)($_GET['id'] ?? 0);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::checkCsrf()) {
            $_SESSION['flash_error'] = 'CSRF validation failed.';
            header('Location: ' . getSetting('app_url') . '/conversions?tab=rotators');
            exit;
        }

        if ($action === 'rotator_create') {
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $destRaw = trim($_POST['destinations'] ?? '');

            $dests = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $destRaw))), function($url) {
                return filter_var($url, FILTER_VALIDATE_URL);
            });

            if ($name !== '' && $slug !== '' && !empty($dests)) {
                try {
                    $st = $db->prepare("INSERT INTO mod_link_rotators (name, slug, destinations) VALUES (?, ?, ?)");
                    $st->execute([$name, $slug, json_encode(array_values($dests))]);
                    $_SESSION['flash_success'] = 'Link rotator successfully registered.';
                } catch (PDOException $e) {
                    $_SESSION['flash_error'] = 'Error creating rotator: Slug must be unique.';
                }
            } else {
                $_SESSION['flash_error'] = 'Invalid parameters. Please specify unique slug and valid URLs.';
            }
            header('Location: ' . getSetting('app_url') . '/conversions?tab=rotators');
            exit;
        }

        if ($action === 'rotator_delete' && $id > 0) {
            $db->prepare("DELETE FROM mod_link_rotators WHERE id = ?")->execute([$id]);
            $_SESSION['flash_success'] = 'Link rotator removed.';
            header('Location: ' . getSetting('app_url') . '/conversions?tab=rotators');
            exit;
        }
    }

    // Fetch link rotators
    $rotators = $db->query("SELECT * FROM mod_link_rotators ORDER BY created_at DESC")->fetchAll();

    // Fetch top referrers
    $topReferrers = $db->query("
        SELECT email, first_name, last_name, referral_count, referral_code 
        FROM subscribers 
        WHERE referral_count > 0 
        ORDER BY referral_count DESC 
        LIMIT 100
    ")->fetchAll();

    $title = 'Conversion & Engagement Suite';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

// Redirect old routes for backwards compatibility
if ($routePath === '/rotators') {
    header('Location: ' . getSetting('app_url') . '/conversions?tab=rotators');
    exit;
}
if ($routePath === '/referrals') {
    header('Location: ' . getSetting('app_url') . '/conversions?tab=referrals');
    exit;
}

// Route: /fomo/render (GD dynamic countdown generator)
if ($routePath === '/fomo/render') {
    $endStr = $_GET['end'] ?? date('Y-m-d\T23:59:59');
    $endTime = strtotime($endStr);
    $now = time();
    $diff = $endTime - $now;
    if ($diff < 0) $diff = 0;

    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);
    $seconds = $diff % 60;

    $text = sprintf("%02d DAYS  %02d HRS  %02d MINS  %02d SECS", $days, $hours, $minutes, $seconds);
    if ($diff === 0) {
        $text = "OFFER EXPIRED";
    }

    $width = 600;
    $height = 100;

    if (function_exists('imagecreatetruecolor')) {
        $image = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($image, 11, 15, 25);
        $text_color = imagecolorallocate($image, 255, 91, 96);
        imagefill($image, 0, 0, $bg);

        $font = 5;
        $fw = imagefontwidth($font) * strlen($text);
        $fh = imagefontheight($font);

        $x = (int)(($width - $fw) / 2);
        $y = (int)(($height - $fh) / 2);

        imagestring($image, $font, $x, $y, $text, $text_color);
        header('Content-Type: image/png');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        imagepng($image);
        if (PHP_VERSION_ID < 80000) { @imagedestroy($image); }
    } else {
        header('Content-Type: text/plain');
        echo $text;
    }
    exit;
}

// Route: /go (Redirection endpoint for round-robin rotators)
if ($routePath === '/go') {
    $slug = $_GET['s'] ?? '';
    if ($slug === '') {
        header('Location: ' . getSetting('app_url') . '/');
        exit;
    }

    try {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id, destinations, clicks FROM mod_link_rotators WHERE slug = ?");
        $st->execute([$slug]);
        $rotator = $st->fetch();

        if ($rotator) {
            $dests = json_decode($rotator['destinations'], true);
            if (!empty($dests)) {
                $db->prepare("UPDATE mod_link_rotators SET clicks = clicks + 1 WHERE id = ?")->execute([(int)$rotator['id']]);
                $idx = ((int)$rotator['clicks']) % count($dests);
                $target = $dests[$idx];
                header("Location: " . $target);
                exit;
            }
        }
    } catch (Throwable $e) {
        error_log("Link rotator redirection error: " . $e->getMessage());
    }

    header('Location: ' . getSetting('app_url') . '/');
    exit;
}
