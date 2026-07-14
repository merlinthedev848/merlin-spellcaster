<?php
declare(strict_types=1);

/**
 * Public diagnostic test page for verifying Subscription Forms and Email Deliverability checks
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/core/Hook.php';
require_once dirname(__DIR__) . '/core/ModuleManager.php';

// Enable error display for diagnostics
ini_set('display_errors', '1');
error_reporting(E_ALL);

$db = Database::getConnection();

// Bootstrap schema in case it hasn't run yet
_runMigrations($db);

$results = [];

// 1. Database Table verification
try {
    $st = $db->query("DESCRIBE forms");
    $columns = $st->fetchAll(PDO::FETCH_COLUMN);
    $required = ['id', 'name', 'list_id', 'headline', 'description', 'button_text', 'success_message', 'redirect_url', 'download_url', 'show_name', 'require_name', 'double_optin', 'created_at'];
    $missing = array_diff($required, $columns);
    
    if (empty($missing)) {
        $results['database'] = ['status' => 'pass', 'msg' => 'forms table exists with all required columns.'];
    } else {
        $results['database'] = ['status' => 'fail', 'msg' => 'forms table is missing columns: ' . implode(', ', $missing)];
    }
} catch (PDOException $e) {
    $results['database'] = ['status' => 'fail', 'msg' => 'forms table not found. Error: ' . $e->getMessage()];
}

// 2. Routing checks (index.php inspection)
$indexPath = dirname(__DIR__) . '/index.php';
if (file_exists($indexPath)) {
    $content = file_get_contents($indexPath);
    $hasSubscribeRoute = str_contains($content, "'/subscribe'") || str_contains($content, '"/subscribe"');
    $hasBaseConstant = str_contains($content, "define('BASE_PATH'");
    
    if ($hasSubscribeRoute && $hasBaseConstant) {
        $results['routing'] = ['status' => 'pass', 'msg' => '/subscribe route and BASE_PATH constant are configured.'];
    } else {
        $results['routing'] = ['status' => 'fail', 'msg' => 'Routing configurations missing. /subscribe: ' . ($hasSubscribeRoute?'OK':'MISSING') . ', BASE_PATH: ' . ($hasBaseConstant?'OK':'MISSING')];
    }
} else {
    $results['routing'] = ['status' => 'fail', 'msg' => 'index.php file not found.'];
}

// 3. Email Deliverability Hook verification
// Make sure EmailVerifier class is loaded
$verifierRoutes = dirname(__DIR__) . '/modules/email_verifier/routes.php';
if (file_exists($verifierRoutes)) {
    require_once $verifierRoutes;
}

$results['deliverability'] = [];
if (class_exists('EmailVerifier')) {
    // Test 3.1: Valid Email address
    $dataValid = ['email' => 'chris.kendall@google.com', 'valid' => true, 'error' => ''];
    Hook::fire('before_add_contact', $dataValid);
    if ($dataValid['valid']) {
        $results['deliverability'][] = ['status' => 'pass', 'msg' => 'Valid email (chris.kendall@google.com) passed deliverability check.'];
    } else {
        $results['deliverability'][] = ['status' => 'fail', 'msg' => 'Valid email was incorrectly flagged: ' . $dataValid['error']];
    }

    // Test 3.2: Invalid Syntax
    $dataSyntax = ['email' => 'not-an-email', 'valid' => true, 'error' => ''];
    Hook::fire('before_add_contact', $dataSyntax);
    if (!$dataSyntax['valid']) {
        $results['deliverability'][] = ['status' => 'pass', 'msg' => 'Invalid syntax (not-an-email) was successfully blocked: ' . $dataSyntax['error']];
    } else {
        $results['deliverability'][] = ['status' => 'fail', 'msg' => 'Invalid syntax was not blocked.'];
    }

    // Test 3.3: Disposable Domain email
    $dataDisposable = ['email' => 'spamuser@mailinator.com', 'valid' => true, 'error' => ''];
    Hook::fire('before_add_contact', $dataDisposable);
    if (!$dataDisposable['valid']) {
        $results['deliverability'][] = ['status' => 'pass', 'msg' => 'Disposable provider (spamuser@mailinator.com) was successfully blocked: ' . $dataDisposable['error']];
    } else {
        $results['deliverability'][] = ['status' => 'fail', 'msg' => 'Disposable email provider was not blocked.'];
    }
    
    // Test 3.4: Non-existent Domain MX check
    $dataNoMx = ['email' => 'test@thisdomaindoesnotexistatall12345.com', 'valid' => true, 'error' => ''];
    Hook::fire('before_add_contact', $dataNoMx);
    if (!$dataNoMx['valid']) {
        $results['deliverability'][] = ['status' => 'pass', 'msg' => 'Non-existent MX record domain was successfully blocked: ' . $dataNoMx['error']];
    } else {
        $results['deliverability'][] = ['status' => 'fail', 'msg' => 'Non-existent domain was not blocked.'];
    }
} else {
    $results['deliverability'][] = ['status' => 'warn', 'msg' => 'EmailVerifier module is not enabled or class not found. Skipping verification tests.'];
}

// 4. Form Insertion CRUD check
try {
    // Insert mock
    $db->prepare("INSERT INTO forms (name, headline, description, button_text, success_message, redirect_url, download_url) VALUES (?, ?, ?, ?, ?, ?, ?)")
       ->execute(['Mock Test Form', 'Get Free Ebook', 'Sign up below', 'Get File Now', 'Done!', 'https://example.com/redirect', 'https://example.com/ebook.pdf']);
    $formId = (int)$db->lastInsertId();

    // Verify
    $stCheck = $db->prepare("SELECT * FROM forms WHERE id = ?");
    $stCheck->execute([$formId]);
    $fetched = $stCheck->fetch();

    if ($fetched && $fetched['download_url'] === 'https://example.com/ebook.pdf') {
        $results['crud'] = ['status' => 'pass', 'msg' => 'Mock subscription form inserted, verified, and fetched successfully.'];
        // Clean up
        $db->prepare("DELETE FROM forms WHERE id = ?")->execute([$formId]);
    } else {
        $results['crud'] = ['status' => 'fail', 'msg' => 'Form record fetched, but column values did not match.'];
    }
} catch (Throwable $e) {
    $results['crud'] = ['status' => 'fail', 'msg' => 'Form CRUD operations failed. Error: ' . $e->getMessage()];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forms & Deliverability Tests</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f8f9fc; padding: 40px; color: #1e293b; }
        .container { max-width: 700px; margin: auto; background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 32px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        h1 { font-size: 20px; font-weight: 800; margin-top: 0; margin-bottom: 24px; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; }
        .test-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 12px 0; border-bottom: 1px dashed #f1f5f9; font-size: 14px; }
        .test-row:last-child { border-bottom: none; }
        .status-badge { display: inline-block; padding: 3px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .status-pass { background-color: #d1fae5; color: #065f46; }
        .status-fail { background-color: #fee2e2; color: #991b1b; }
        .status-warn { background-color: #fef3c7; color: #92400e; }
        .sub-test { padding-left: 20px; color: #475569; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Forms & Deliverability Integration Test Diagnostics</h1>
        
        <!-- Database Verification -->
        <div class="test-row">
            <div>
                <strong>Forms Database Schema Check</strong>
                <div style="font-size: 12px; color: #64748b; margin-top: 2px;"><?= e($results['database']['msg']) ?></div>
            </div>
            <span class="status-badge status-<?= $results['database']['status'] ?>"><?= $results['database']['status'] ?></span>
        </div>

        <!-- Routing Verification -->
        <div class="test-row">
            <div>
                <strong>Core Routing & Definitions</strong>
                <div style="font-size: 12px; color: #64748b; margin-top: 2px;"><?= e($results['routing']['msg']) ?></div>
            </div>
            <span class="status-badge status-<?= $results['routing']['status'] ?>"><?= $results['routing']['status'] ?></span>
        </div>

        <!-- CRUD Operations -->
        <div class="test-row">
            <div>
                <strong>Forms CRUD Integration Check</strong>
                <div style="font-size: 12px; color: #64748b; margin-top: 2px;"><?= e($results['crud']['msg']) ?></div>
            </div>
            <span class="status-badge status-<?= $results['crud']['status'] ?>"><?= $results['crud']['status'] ?></span>
        </div>

        <!-- Deliverability checks -->
        <div style="padding: 12px 0; border-bottom: 1px dashed #f1f5f9;">
            <strong>Email Verification Hook Checks</strong>
            <div style="margin-top: 8px;">
                <?php foreach ($results['deliverability'] as $t): ?>
                    <div class="test-row sub-test" style="border: none; padding: 4px 0;">
                        <span><?= e($t['msg']) ?></span>
                        <span class="status-badge status-<?= $t['status'] ?>" style="font-size: 9px; padding: 2px 6px;"><?= $t['status'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div style="margin-top: 24px; text-align: center;">
            <a href="<?= e(getSetting('app_url')) ?>/forms" style="display: inline-block; background-color: #635bff; color: white; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; font-size: 13px; box-shadow: 0 4px 12px rgba(99, 91, 255, 0.25);">
                Go to Forms Directory
            </a>
        </div>
    </div>
</body>
</html>
