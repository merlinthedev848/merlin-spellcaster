<?php
declare(strict_types=1);

/**
 * Database wrapper class
 */
class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        global $db;
        if ($db !== null) {
            return $db;
        }

        if (self::$instance === null) {
            $localConfig = dirname(__DIR__) . '/config.local.php';
            if (!file_exists($localConfig)) {
                throw new RuntimeException("Database not configured. config.local.php missing.");
            }

            require $localConfig;

            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4",
                $db_host ?? 'localhost',
                $db_port ?? 3306,
                $db_name ?? ''
            );
            
            self::$instance = new PDO($dsn, $db_user ?? '', $db_pass ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            self::$instance->exec("SET time_zone = '+00:00'");
        }

        return self::$instance;
    }
}
