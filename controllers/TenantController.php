<?php
declare(strict_types=1);

/**
 * Controller to manage SaaS tenant provisioning
 */
class TenantController {
    /**
     * Show Super Admin Tenants panel
     */
    public function index(): void {
        // Tenants panel is only accessible on the master domain
        if (Database::getTenantSubdomain() !== null) {
            header('Location: ' . getSetting('app_url') . '/');
            exit;
        }

        if (!Auth::check()) {
            header('Location: ' . getSetting('app_url') . '/login');
            exit;
        }

        $db = Database::getConnection();

        // Ensure master database has the tenants table
        $db->exec("
            CREATE TABLE IF NOT EXISTS tenants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(100) UNIQUE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $tenants = $db->query("SELECT * FROM tenants ORDER BY created_at DESC")->fetchAll();

        // Calculate metadata for each tenant
        $localConfig = dirname(dirname(__DIR__)) . '/config.local.php';
        require $localConfig;
        
        foreach ($tenants as &$t) {
            $tDbName = ($db_name ?? 'merlin') . '_tenant_' . preg_replace('/[^a-zA-Z0-9_]/', '', $t['slug']);
            
            try {
                $dsnTenant = "mysql:host=" . ($db_host ?? 'localhost') . ";port=" . ($db_port ?? 3306) . ";dbname=" . $tDbName . ";charset=utf8mb4";
                $pdoT = new PDO($dsnTenant, $db_user ?? '', $db_pass ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                
                $t['contacts'] = (int)$pdoT->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();
                $t['campaigns'] = (int)$pdoT->query("SELECT COUNT(*) FROM campaigns")->fetchColumn();
            } catch (Throwable $e) {
                $t['contacts'] = 0;
                $t['campaigns'] = 0;
            }
        }
        unset($t);

        $title = 'Tenant Directory & Provisioning';
        $viewPath = dirname(__DIR__) . '/views/tenants.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    /**
     * Create a new tenant database and seed admin account
     */
    public function create(): void {
        if (Database::getTenantSubdomain() !== null) {
            header('Location: ' . getSetting('app_url') . '/');
            exit;
        }

        if (!Auth::check()) {
            header('Location: ' . getSetting('app_url') . '/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf()) {
                $_SESSION['flash_error'] = 'CSRF validation failed.';
                header('Location: ' . getSetting('app_url') . '/super/tenants');
                exit;
            }

            $name = trim($_POST['name'] ?? '');
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_POST['slug'] ?? ''));
            $email = strtolower(trim($_POST['admin_email'] ?? ''));
            $password = $_POST['admin_password'] ?? '';

            if ($name === '' || $slug === '' || $email === '' || $password === '') {
                $_SESSION['flash_error'] = 'All fields are required.';
                header('Location: ' . getSetting('app_url') . '/super/tenants');
                exit;
            }

            $db = Database::getConnection();

            try {
                // 1. Verify uniqueness of tenant slug in master table
                $stmtCheck = $db->prepare("SELECT id FROM tenants WHERE slug = ?");
                $stmtCheck->execute([$slug]);
                if ($stmtCheck->fetch()) {
                    $_SESSION['flash_error'] = 'Tenant subdomain slug must be unique.';
                    header('Location: ' . getSetting('app_url') . '/super/tenants');
                    exit;
                }

                // 2. Connect to MySQL server and provision the database
                $localConfig = dirname(dirname(__DIR__)) . '/config.local.php';
                require $localConfig;
                
                $tDbName = ($db_name ?? 'merlin') . '_tenant_' . $slug;
                
                $dsnNoDb = "mysql:host=" . ($db_host ?? 'localhost') . ";port=" . ($db_port ?? 3306) . ";charset=utf8mb4";
                $pdoT = new PDO($dsnNoDb, $db_user ?? '', $db_pass ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdoT->exec("CREATE DATABASE IF NOT EXISTS `" . $tDbName . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdoT->exec("USE `" . $tDbName . "`");

                // 3. Run migrations on the new database
                _bootstrapSchema($pdoT);
                _runMigrations($pdoT);

                // 4. Seed default admin user
                $hashed = password_hash($password, PASSWORD_ARGON2ID);
                $stAdmin = $pdoT->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
                $stAdmin->execute([$email, $hashed]);

                // 5. Update settings table with tenant URL details
                $primaryUrl = getSetting('app_url');
                $parsed = parse_url($primaryUrl);
                $scheme = $parsed['scheme'] ?? 'http';
                $host = $parsed['host'] ?? 'localhost';
                $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
                
                $tenantUrl = $scheme . '://' . $slug . '.' . $host . $port;
                
                $stUrl = $pdoT->prepare("UPDATE settings SET value = ? WHERE `key` = 'app_url'");
                $stUrl->execute([$tenantUrl]);

                // 6. Insert tenant record into master database
                $stTenant = $db->prepare("INSERT INTO tenants (name, slug) VALUES (?, ?)");
                $stTenant->execute([$name, $slug]);

                $_SESSION['flash_success'] = "Tenant '{$name}' successfully provisioned at {$tenantUrl}";
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = 'Provisioning error: ' . $e->getMessage();
            }

            header('Location: ' . getSetting('app_url') . '/super/tenants');
            exit;
        }
    }
}
