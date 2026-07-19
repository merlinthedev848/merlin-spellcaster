<?php
declare(strict_types=1);

/**
 * Authentication Gate for Merlin V2.
 * Manages user logins and secure routing permissions using Argon2id hashing.
 */
class Auth {
    /**
     * Check if a session has an authenticated administrator
     */
    public static function check(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
    }

    /**
     * Attempt login credentials authentication
     */
    public static function login(string $email, string $password): bool {
        $db = Database::getConnection();

        try {
            $st = $db->prepare("SELECT id, password FROM users WHERE email = ? LIMIT 1");
            $st->execute([strtolower(trim($email))]);
            $user = $st->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                // Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_email'] = strtolower(trim($email));
                return true;
            }
        } catch (Throwable $e) {
            error_log("Auth login error: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Terminate the user session and clear variables
     */
    public static function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        session_destroy();
    }

    /**
     * Generate a CSRF token field for forms
     */
    public static function csrfField(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
    }

    /**
     * Validate the CSRF token from POST
     */
    public static function checkCsrf(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token'])) {
            return false;
        }
        $valid = hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
        if ($valid) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $valid;
    }
}
