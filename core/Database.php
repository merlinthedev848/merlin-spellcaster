<?php
declare(strict_types=1);

/**
 * Advanced Multi-Tenant Database router
 */
class Database {
    private static ?PDO $instance = null;
    private static ?string $resolvedDbName = null;

    /**
     * Resolve the current tenant subdomain from the HTTP host header
     */
    public static function getTenantSubdomain(): ?string {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (empty($host)) {
            return null;
        }

        $host = explode(':', $host)[0];
        $parts = explode('.', $host);
        
        // Skip IP addresses
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        if (count($parts) === 2 && $parts[1] === 'localhost') {
            return $parts[0];
        }
        
        if (count($parts) >= 3) {
            $sub = $parts[0];
            if ($sub === 'www' || $sub === 'mail' || $sub === 'api') {
                return null;
            }
            return $sub;
        }

        return null;
    }

    public static function getConnection(): PDO {
        $subdomain = self::getTenantSubdomain();
        
        $localConfig = dirname(__DIR__) . '/config.local.php';
        if (!file_exists($localConfig)) {
            throw new RuntimeException("Database not configured. config.local.php missing.");
        }

        require $localConfig;
        
        $masterDb = $db_name ?? 'merlin';
        $targetDb = $masterDb;

        if ($subdomain !== null) {
            $targetDb = $masterDb . '_tenant_' . preg_replace('/[^a-zA-Z0-9_]/', '', $subdomain);
        }

        if (self::$instance === null || self::$resolvedDbName !== $targetDb) {
            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4",
                $db_host ?? 'localhost',
                $db_port ?? 3306,
                $targetDb
            );
            
            try {
                self::$instance = new PDO($dsn, $db_user ?? '', $db_pass ?? '', [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                self::$instance->exec("SET time_zone = '+00:00'");
                self::$resolvedDbName = $targetDb;
            } catch (PDOException $e) {
                // If database does not exist (1049), automatically create it on demand
                if ($e->getCode() == 1049) {
                    $dsnNoDb = sprintf(
                        "mysql:host=%s;port=%d;charset=utf8mb4",
                        $db_host ?? 'localhost',
                        $db_port ?? 3306
                    );
                    $pdoNoDb = new PDO($dsnNoDb, $db_user ?? '', $db_pass ?? '', [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);
                    $pdoNoDb->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace("`","",$targetDb) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    
                    // Re-connect to new database
                    self::$instance = new PDO($dsn, $db_user ?? '', $db_pass ?? '', [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]);
                    self::$instance->exec("SET time_zone = '+00:00'");
                    self::$resolvedDbName = $targetDb;
                } else {
                    throw $e;
                }
            }
        }

        return self::$instance;
    }
}
