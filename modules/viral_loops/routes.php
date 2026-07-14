<?php
declare(strict_types=1);

// Capture referral code globally from GET parameters
if (isset($_GET['ref']) && session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['referred_by_code'] = trim($_GET['ref']);
}

// Bootstrap referral schema columns
try {
    $db = Database::getConnection();
    $db->exec("ALTER TABLE subscribers ADD COLUMN referral_code VARCHAR(20) UNIQUE DEFAULT NULL");
    $db->exec("ALTER TABLE subscribers ADD COLUMN referred_by INT DEFAULT NULL");
    $db->exec("ALTER TABLE subscribers ADD COLUMN referral_count INT DEFAULT 0");
} catch (PDOException $e) {
    // Columns already exist
}

// Helper function to generate unique referral code
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

// Hook: contact_added (Fires when any new subscriber is created)
Hook::register('contact_added', function($data) {
    try {
        $db = Database::getConnection();
        $subId = (int)($data['subscriber_id'] ?? 0);
        if ($subId <= 0) return;

        // 1. Generate referral code for the new subscriber
        $myCode = generateUniqueReferralCode($db);
        $db->prepare("UPDATE subscribers SET referral_code = ? WHERE id = ? AND referral_code IS NULL")
           ->execute([$myCode, $subId]);

        // 2. Process referred_by logic
        $refCode = $_SESSION['referred_by_code'] ?? null;
        if ($refCode) {
            // Find referrer
            $stRef = $db->prepare("SELECT id, email, referral_count FROM subscribers WHERE referral_code = ?");
            $stRef->execute([$refCode]);
            $referrer = $stRef->fetch();

            if ($referrer && (int)$referrer['id'] !== $subId) {
                $refId = (int)$referrer['id'];
                // Update new subscriber referred_by
                $db->prepare("UPDATE subscribers SET referred_by = ? WHERE id = ?")
                   ->execute([$refId, $subId]);

                // Increment referrer count
                $db->prepare("UPDATE subscribers SET referral_count = referral_count + 1 WHERE id = ?")
                   ->execute([$refId]);

                // Log activities
                logActivity($refId, 'subscribe', "Referred new user: subscriber ID #{$subId}");
                logActivity($subId, 'subscribe', "Signed up via referral code '{$refCode}' from subscriber ID #{$refId}");

                // Clean session
                unset($_SESSION['referred_by_code']);
            }
        }
    } catch (Throwable $e) {
        error_log("ViralLoops contact_added hook error: " . $e->getMessage());
    }
});

// Hook: before_render_email (Injects {{referral_link}} macro)
Hook::register('before_render_email', function(&$data) {
    try {
        $db = Database::getConnection();
        $subId = (int)($data['subscriber_id'] ?? 0);
        if ($subId <= 0) return;

        // Fetch or generate referral code
        $st = $db->prepare("SELECT referral_code FROM subscribers WHERE id = ?");
        $st->execute([$subId]);
        $code = $st->fetchColumn();

        if (!$code) {
            $code = generateUniqueReferralCode($db);
            $db->prepare("UPDATE subscribers SET referral_code = ? WHERE id = ?")->execute([$code, $subId]);
        }

        $appUrl = rtrim($data['vars']['app_url'], '/');
        // Fallback or default form ID if any forms exist
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

// Routes
if ($routePath === '/referrals') {
    $db = Database::getConnection();

    // Fetch top referrers
    $topReferrers = $db->query("
        SELECT email, first_name, last_name, referral_count, referral_code 
        FROM subscribers 
        WHERE referral_count > 0 
        ORDER BY referral_count DESC 
        LIMIT 15
    ")->fetchAll();

    $title = 'Viral growth program';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}
