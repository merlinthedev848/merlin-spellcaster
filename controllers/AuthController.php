<?php
declare(strict_types=1);

/**
 * Controller for managing Authentication logins and session logouts
 */
class AuthController {
    public function login(): void {
        // Redirect to dashboard if already logged in
        if (Auth::check()) {
            header('Location: ' . getSetting('app_url') . '/dashboard');
            exit;
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $error = 'Please enter both your email address and password.';
            } else {
                if (Auth::login($email, $password)) {
                    $_SESSION['flash_success'] = 'Welcome back to Merlin!';
                    header('Location: ' . getSetting('app_url') . '/dashboard');
                    exit;
                } else {
                    $error = 'Invalid email address or password. Please try again.';
                }
            }
        }

        // Include login view
        include dirname(__DIR__) . '/views/login.php';
    }

    public function logout(): void {
        Auth::logout();
        
        // Start temp session just for flash message
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash_success'] = 'You have logged out successfully.';
        
        header('Location: ' . getSetting('app_url') . '/login');
        exit;
    }
}
