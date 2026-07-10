<?php
declare(strict_types=1);

/**
 * logout.php — Destroys session and redirects to login
 */
require_once __DIR__ . '/core/Auth.php';
Auth::logout();
header('Location: /login.php?msg=logged_out');
exit();
