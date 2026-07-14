<?php
declare(strict_types=1);

/**
 * Controller for installation, database setup, and admin account seeding
 */
class SetupController {
    public function index(): void {
        $error = null;
        $success = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $host = trim($_POST['db_host'] ?? 'localhost');
            $port = (int)($_POST['db_port'] ?? 3306);
            $name = trim($_POST['db_name'] ?? '');
            $user = trim($_POST['db_user'] ?? '');
            $pass = $_POST['db_pass'] ?? '';
            $url  = rtrim(trim($_POST['app_url'] ?? 'http://localhost/merlin-spellcaster'), '/');
            
            $adminEmail = strtolower(trim($_POST['admin_email'] ?? ''));
            $adminPass  = $_POST['admin_pass'] ?? '';

            if (empty($name) || empty($user) || empty($adminEmail) || empty($adminPass)) {
                $error = 'All fields, including admin account credentials, are required.';
            } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid administrator email address.';
            } else {
                try {
                    // 1. Test database connection
                    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
                    $pdo = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_TIMEOUT => 5,
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);
                    
                    // Create database if not exists
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("USE `{$name}`");

                    // 2. Write config.local.php
                    $configContent = "<?php\n"
                        . "declare(strict_types=1);\n\n"
                        . "/**\n"
                        . " * config.local.php — Generated Local Database Configuration\n"
                        . " */\n\n"
                        . "\$db_host = '" . addslashes($host) . "';\n"
                        . "\$db_name = '" . addslashes($name) . "';\n"
                        . "\$db_user = '" . addslashes($user) . "';\n"
                        . "\$db_pass = '" . addslashes($pass) . "';\n"
                        . "\$db_port = {$port};\n";

                    $written = file_put_contents(dirname(__DIR__) . '/config.local.php', $configContent);
                    if ($written === false) {
                        throw new RuntimeException("Could not write config.local.php. Verify directory write permissions.");
                    }

                    // 3. Reload config and bootstrap schema
                    require dirname(__DIR__) . '/config.local.php';
                    $dsnFull = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
                    $dbFull = new PDO($dsnFull, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);
                    
                    _bootstrapSchema($dbFull);
                    
                    // 4. Seed Administrator User (Argon2id hashing)
                    $hashedPass = password_hash($adminPass, PASSWORD_ARGON2ID);
                    $stAdmin = $dbFull->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
                    $stAdmin->execute([$adminEmail, $hashedPass]);
                    
                    // Set app url setting
                    $stUrl = $dbFull->prepare("UPDATE settings SET value = ? WHERE `key` = 'app_url'");
                    $stUrl->execute([$url]);

                    $_SESSION['flash_success'] = 'System setup successfully! Please sign in using your admin credentials.';
                    $success = true;
                    
                    header("Location: {$url}/login");
                    exit;

                } catch (Throwable $e) {
                    $error = 'Setup failed: ' . $e->getMessage();
                }
            }
        }

        $viewPath = dirname(__DIR__) . '/views/setup.php';
        include $viewPath;
    }
}
