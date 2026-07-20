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
        
        // Load config.local.php to check for primary host override
        $localConfig = dirname(__DIR__) . '/config.local.php';
        $primaryHost = null;
        if (file_exists($localConfig)) {
            $configVars = (static function($file) {
                include $file;
                return get_defined_vars();
            })($localConfig);
            
            if (isset($configVars['primary_host'])) {
                $primaryHost = (string)$configVars['primary_host'];
            }
        }

        // If current request host matches primary host exactly, it is NOT a tenant
        if ($primaryHost !== null && strcasecmp($host, $primaryHost) === 0) {
            return null;
        }

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
            // Ignore common administrative subdomains (www, mail, api, mailer)
            if ($sub === 'www' || $sub === 'mail' || $sub === 'api' || $sub === 'mailer') {
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
            // Connect to master database first to resolve the tenant's database name mappings
            $dsnMaster = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4",
                $db_host ?? 'localhost',
                $db_port ?? 3306,
                $masterDb
            );
            try {
                $pdoMaster = new PDO($dsnMaster, $db_user ?? '', $db_pass ?? '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $stmt = $pdoMaster->prepare("SELECT db_name FROM tenants WHERE slug = ? LIMIT 1");
                $stmt->execute([$subdomain]);
                $tenantDb = $stmt->fetchColumn();
                if ($tenantDb) {
                    $targetDb = $tenantDb;
                }
            } catch (Throwable $e) {
                // fall back to masterDb if lookup fails
            }
        }

        if (self::$instance === null || self::$resolvedDbName !== $targetDb) {
            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4",
                $db_host ?? 'localhost',
                $db_port ?? 3306,
                $targetDb
            );
            
            self::$instance = new PDO($dsn, $db_user ?? '', $db_pass ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            self::$instance->exec("SET time_zone = '+00:00'");
            self::$resolvedDbName = $targetDb;
        }

        return self::$instance;
    }
}
