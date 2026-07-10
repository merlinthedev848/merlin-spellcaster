<?php
declare(strict_types=1);

/**
 * core/Auth.php — Session-based authentication
 * PHP 8.5+ compatible. No external dependencies.
 */

class Auth
{
    public static function requireLogin(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php'));
            exit();
        }
    }

    public static function login(string $email, string $password, PDO $db): bool
    {
        try {
            $stmt = $db->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([trim(strtolower($email))]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                return false;
            }

            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);

            $_SESSION['user_id']    = (int)$user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];

            // Update last login
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
               ->execute([$user['id']]);

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    public static function currentUser(): array
    {
        return [
            'id'    => $_SESSION['user_id']    ?? null,
            'name'  => $_SESSION['user_name']  ?? 'Guest',
            'email' => $_SESSION['user_email'] ?? '',
            'role'  => $_SESSION['user_role']  ?? 'viewer',
        ];
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }

    public static function createUser(PDO $db, string $name, string $email, string $password, string $role = 'admin'): int
    {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        if ($hash === false) {
            throw new RuntimeException('Could not hash the admin password on this PHP build.');
        }
        $token = bin2hex(random_bytes(32));
        $db->prepare("INSERT INTO users (name, email, password_hash, role, api_token) VALUES (?, ?, ?, ?, ?)")
           ->execute([trim($name), trim(strtolower($email)), $hash, $role, $token]);
        return (int)$db->lastInsertId();
    }

    public static function verifyApiToken(string $token, PDO $db): ?array
    {
        if (empty($token)) return null;
        $stmt = $db->prepare("SELECT id, name, email, role FROM users WHERE api_token = ? LIMIT 1");
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public static function checkCsrf(): bool
    {
        $token = $_POST['_csrf'] ?? '';
        return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::csrfToken() . '">';
    }
}
