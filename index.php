<?php
declare(strict_types=1);

/**
 * index.php — Smart entry point
 * Redirects to: setup wizard → login → dashboard
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

if ($db === null || !isSetupComplete()) {
    header('Location: /setup/');
    exit();
}

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

header('Location: /admin/dashboard.php');
exit();