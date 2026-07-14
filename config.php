<?php
declare(strict_types=1);

// Set standardized UTC timezone globally
date_default_timezone_set('UTC');

/**
 * config.php — Core Configuration & Database Bootstrap
 */

if (session_status() === PHP_SESSION_NONE) {
    // Hashing session parameters securely to prevent CSRF / Session hijacking
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_only_cookies', '1');
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1 || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
        @ini_set('session.cookie_secure', '1');
    }
    @ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

$localConfig = __DIR__ . '/config.local.php';
$db = null;

if (file_exists($localConfig)) {
    require_once $localConfig;
    
    // Connect to database
    try {
        $dsn = sprintf(
            "mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4",
            $db_host ?? 'localhost',
            $db_port ?? 3306,
            $db_name ?? ''
        );
        $db = new PDO($dsn, $db_user ?? '', $db_pass ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $db->exec("SET time_zone = '+00:00'");
        
        // Auto-bootstrap schema if settings table is missing
        _bootstrapSchema($db);
        
        // Run migrations on existing databases safely
        _runMigrations($db);
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Helper to escape output
 */
function e(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Get setting value
 */
function getSetting(string $key, string $default = ''): string {
    try {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT value FROM settings WHERE `key` = ? LIMIT 1");
        $st->execute([$key]);
        $val = $st->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (Throwable) {
        return $default;
    }
}

/**
 * Set setting value
 */
function setSetting(string $key, string $value): void {
    try {
        $db = Database::getConnection();
        $st = $db->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
        $st->execute([$key, $value, $value]);
    } catch (Throwable $e) {
        error_log("setSetting error: " . $e->getMessage());
    }
}

/**
 * Log activity for a subscriber
 */
function logActivity(int $subscriberId, string $activityType, string $description): void {
    try {
        $db = Database::getConnection();
        $st = $db->prepare("INSERT INTO activity_log (subscriber_id, activity_type, description, created_at) VALUES (?, ?, ?, NOW())");
        $st->execute([$subscriberId, $activityType, $description]);
    } catch (Throwable $e) {
        error_log("logActivity error: " . $e->getMessage());
    }
}

/**
 * Generate HMAC token for tracking links and unsubscribes
 */
function generateToken(string $email, int $campaignId, int $subscriberId): string {
    $secret = getSetting('app_secret', 'merlin_fallback_secret_key');
    return hash_hmac('sha256', "{$email}:{$campaignId}:{$subscriberId}", $secret);
}

/**
 * Perform free GeoIP API lookup for location details
 */
function sc_geoip_lookup(string $ip): array {
    if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') {
        return ['country_code' => '', 'country_name' => '', 'city' => 'Localhost'];
    }
    $url = "http://ip-api.com/json/" . urlencode($ip);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['status']) && $data['status'] === 'success') {
            return [
                'country_code' => strtolower($data['countryCode'] ?? ''),
                'country_name' => $data['country'] ?? '',
                'city' => $data['city'] ?? ''
            ];
        }
    }
    return ['country_code' => '', 'country_name' => '', 'city' => ''];
}

/**
 * Capture IP and update subscriber details
 */
function sc_update_subscriber_geoip(int $subscriberId, string $ip): void {
    global $db;
    if (!$db || empty($ip)) return;
    
    $geo = sc_geoip_lookup($ip);
    
    $st = $db->prepare("UPDATE subscribers SET ip_address = ?, country_code = ?, country_name = ?, city = ? WHERE id = ?");
    $st->execute([$ip, $geo['country_code'], $geo['country_name'], $geo['city'], $subscriberId]);
}

/**
 * Bootstrap database schema if it doesn't exist
 */
function _bootstrapSchema(PDO $db): void {
    try {
        $db->query("SELECT 1 FROM settings LIMIT 1");
        return; 
    } catch (PDOException) {
        // Proceed with schema creation
    }

    $queries = [
        "CREATE TABLE IF NOT EXISTS settings (
            `key` VARCHAR(64) PRIMARY KEY,
            `value` TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS tags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(64) NOT NULL UNIQUE,
            color VARCHAR(7) DEFAULT '#635bff',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS lists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(128) NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            first_name VARCHAR(128) DEFAULT '',
            last_name VARCHAR(128) DEFAULT '',
            status ENUM('active', 'unsubscribed', 'bounced', 'complained') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS subscriber_lists (
            subscriber_id INT NOT NULL,
            list_id INT NOT NULL,
            PRIMARY KEY (subscriber_id, list_id),
            FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
            FOREIGN KEY (list_id) REFERENCES lists(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS subscriber_tags (
            subscriber_id INT NOT NULL,
            tag_id INT NOT NULL,
            PRIMARY KEY (subscriber_id, tag_id),
            FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
            FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            subject VARCHAR(255) DEFAULT '',
            body_html LONGTEXT NOT NULL,
            body_text LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS campaigns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body_html LONGTEXT NOT NULL,
            body_text LONGTEXT,
            status ENUM('draft', 'queued', 'sending', 'sent', 'paused') DEFAULT 'draft',
            send_count INT DEFAULT 0,
            open_count INT DEFAULT 0,
            click_count INT DEFAULT 0,
            unsub_count INT DEFAULT 0,
            bounce_count INT DEFAULT 0,
            scheduled_at DATETIME NULL,
            list_id INT DEFAULT 0,
            include_unsubscribe TINYINT DEFAULT 1,
            max_per_hour INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            sent_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS campaign_tags (
            campaign_id INT NOT NULL,
            tag_id INT NOT NULL,
            PRIMARY KEY (campaign_id, tag_id),
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
            FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS email_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT NOT NULL,
            subscriber_id INT NOT NULL,
            status ENUM('pending', 'sending', 'sent', 'failed') DEFAULT 'pending',
            attempts INT DEFAULT 0,
            error_message TEXT,
            send_at DATETIME NOT NULL,
            sent_at DATETIME NULL,
            UNIQUE KEY unique_campaign_sub (campaign_id, subscriber_id),
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
            FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS campaign_opens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT NOT NULL,
            subscriber_id INT NOT NULL,
            ip_address VARCHAR(45) DEFAULT '',
            user_agent VARCHAR(512) DEFAULT '',
            opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
            FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS campaign_clicks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT NOT NULL,
            subscriber_id INT NOT NULL,
            url VARCHAR(2048) NOT NULL,
            ip_address VARCHAR(45) DEFAULT '',
            clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
            FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS automations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(128) NOT NULL,
            trigger_event VARCHAR(64) DEFAULT 'subscribe',
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS automation_steps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            automation_id INT NOT NULL,
            step_type ENUM('wait', 'send_email', 'add_tag', 'remove_tag', 'send_if_opened', 'send_if_not_opened', 'tag_if_not_opened') NOT NULL,
            step_value VARCHAR(255) NOT NULL,
            order_num INT NOT NULL,
            FOREIGN KEY (automation_id) REFERENCES automations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS automation_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            automation_id INT NOT NULL,
            subscriber_id INT NOT NULL,
            step_id INT NOT NULL,
            status ENUM('pending', 'completed') DEFAULT 'pending',
            execute_at DATETIME NOT NULL,
            FOREIGN KEY (automation_id) REFERENCES automations(id) ON DELETE CASCADE,
            FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
            FOREIGN KEY (step_id) REFERENCES automation_steps(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subscriber_id INT NOT NULL,
            activity_type VARCHAR(64) NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS webhook_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider VARCHAR(64) NOT NULL,
            event_type VARCHAR(64) NOT NULL,
            payload LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS forms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(128) NOT NULL,
            list_id INT DEFAULT NULL,
            headline VARCHAR(255) DEFAULT 'Subscribe to our newsletter',
            description TEXT,
            button_text VARCHAR(64) DEFAULT 'Subscribe',
            success_message VARCHAR(255) DEFAULT 'Thank you for subscribing!',
            redirect_url VARCHAR(512) DEFAULT NULL,
            download_url VARCHAR(512) DEFAULT NULL,
            show_name TINYINT DEFAULT 1,
            require_name TINYINT DEFAULT 0,
            double_optin TINYINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (list_id) REFERENCES lists(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ];

    foreach ($queries as $query) {
        $db->exec($query);
    }

    // Default settings
    $defaultSettings = [
        'app_name' => 'Merlin Spellcaster',
        'app_url' => 'http://localhost/merlin-spellcaster',
        'app_secret' => bin2hex(random_bytes(32)),
        'smtp_host' => 'localhost',
        'smtp_port' => '587',
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_encryption' => 'tls',
        'smtp_from_name' => 'Merlin Spellcaster',
        'smtp_from_email' => 'noreply@localhost',
        'cron_secret' => bin2hex(random_bytes(16)),
        'cron_batch_size' => '50',
        'tracking_enabled' => '1',
    ];

    $st = $db->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)");
    foreach ($defaultSettings as $k => $v) {
        $st->execute([$k, $v]);
    }
}

/**
 * Dynamic DB alterations for running code updates safely on live hosting
 */
function _runMigrations(PDO $db): void {
    // 1. Add scheduled_at and list_id columns to campaigns if missing
    try {
        $db->query("SELECT scheduled_at FROM campaigns LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("ALTER TABLE campaigns ADD COLUMN scheduled_at DATETIME NULL AFTER bounce_count");
        } catch (Throwable $e) {
            error_log("Migration Error (scheduled_at): " . $e->getMessage());
        }
    }
    
    try {
        $db->query("SELECT list_id FROM campaigns LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("ALTER TABLE campaigns ADD COLUMN list_id INT DEFAULT 0 AFTER scheduled_at");
        } catch (Throwable $e) {
            error_log("Migration Error (list_id): " . $e->getMessage());
        }
    }

    try {
        $db->query("SELECT include_unsubscribe FROM campaigns LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("ALTER TABLE campaigns ADD COLUMN include_unsubscribe TINYINT DEFAULT 1 AFTER list_id");
        } catch (Throwable $e) {
            error_log("Migration Error (include_unsubscribe): " . $e->getMessage());
        }
    }

    try {
        $db->query("SELECT max_per_hour FROM campaigns LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("ALTER TABLE campaigns ADD COLUMN max_per_hour INT DEFAULT 0 AFTER include_unsubscribe");
        } catch (Throwable $e) {
            error_log("Migration Error (max_per_hour): " . $e->getMessage());
        }
    }

    try {
        $db->exec("ALTER TABLE email_queue ADD UNIQUE KEY unique_campaign_sub (campaign_id, subscriber_id)");
    } catch (Throwable $e) {
        // Log warning or ignore if unique key already exists
    }

    try {
        $db->exec("ALTER TABLE email_queue ADD INDEX idx_queue_status_send_at (status, send_at)");
    } catch (Throwable $e) {
        // Log warning or ignore if index already exists
    }

    try {
        $db->exec("ALTER TABLE subscriber_lists ADD INDEX idx_sublists_list_id (list_id)");
    } catch (Throwable $e) {}

    try {
        $db->exec("ALTER TABLE subscriber_tags ADD INDEX idx_subtags_tag_id (tag_id)");
    } catch (Throwable $e) {}

    try {
        $db->exec("ALTER TABLE subscribers ADD INDEX idx_subs_status (status)");
    } catch (Throwable $e) {}

    try {
        $db->exec("ALTER TABLE activity_log ADD INDEX idx_act_created (created_at)");
    } catch (Throwable $e) {}

    // Add GeoIP columns to subscribers if missing
    try {
        $db->query("SELECT ip_address FROM subscribers LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("ALTER TABLE subscribers 
                ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL,
                ADD COLUMN country_code VARCHAR(10) DEFAULT NULL,
                ADD COLUMN country_name VARCHAR(128) DEFAULT NULL,
                ADD COLUMN city VARCHAR(128) DEFAULT NULL");
        } catch (Throwable $e) {
            error_log("Migration Error (subscribers IP columns): " . $e->getMessage());
        }
    }

    try {
        $db->exec("ALTER TABLE email_queue ADD COLUMN ab_subject VARCHAR(255) DEFAULT NULL");
    } catch (Throwable $e) {
        // Ignore if column already exists
    }

    // 2. Create campaign_tags table if missing
    try {
        $db->query("SELECT 1 FROM campaign_tags LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS campaign_tags (
                campaign_id INT NOT NULL,
                tag_id INT NOT NULL,
                PRIMARY KEY (campaign_id, tag_id),
                FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
                FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e) {
            error_log("Migration Error (campaign_tags): " . $e->getMessage());
        }
    }

    // 3. Update automation_steps ENUM parameters
    try {
        $db->exec("ALTER TABLE automation_steps MODIFY COLUMN step_type ENUM('wait', 'send_email', 'add_tag', 'remove_tag', 'send_if_opened', 'send_if_not_opened', 'tag_if_not_opened') NOT NULL");
    } catch (Throwable $e) {
        error_log("Migration Error (automation_steps step_type enum): " . $e->getMessage());
    }

    // 4. Create forms table if missing in migrations
    try {
        $db->query("SELECT 1 FROM forms LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS forms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(128) NOT NULL,
                list_id INT DEFAULT NULL,
                headline VARCHAR(255) DEFAULT 'Subscribe to our newsletter',
                description TEXT,
                button_text VARCHAR(64) DEFAULT 'Subscribe',
                success_message VARCHAR(255) DEFAULT 'Thank you for subscribing!',
                redirect_url VARCHAR(512) DEFAULT NULL,
                download_url VARCHAR(512) DEFAULT NULL,
                show_name TINYINT DEFAULT 1,
                require_name TINYINT DEFAULT 0,
                double_optin TINYINT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (list_id) REFERENCES lists(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e) {
            error_log("Migration Error (forms): " . $e->getMessage());
        }
    }

    // 5. Add attributes column to subscribers table if missing
    try {
        $db->query("SELECT attributes FROM subscribers LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("ALTER TABLE subscribers ADD COLUMN attributes TEXT DEFAULT NULL AFTER status");
        } catch (Throwable $e) {
            error_log("Migration Error (attributes): " . $e->getMessage());
        }
    }

    // 6. Create smtp_servers table for Multi-SMTP Routing Engine
    try {
        $db->query("SELECT 1 FROM smtp_servers LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS smtp_servers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(128) NOT NULL,
                host VARCHAR(255) NOT NULL,
                port INT NOT NULL DEFAULT 587,
                username VARCHAR(255) DEFAULT NULL,
                password VARCHAR(255) DEFAULT NULL,
                encryption VARCHAR(10) DEFAULT 'tls',
                from_email VARCHAR(255) DEFAULT NULL,
                from_name VARCHAR(255) DEFAULT NULL,
                status TINYINT DEFAULT 1,
                daily_limit INT DEFAULT 0,
                sent_today INT DEFAULT 0,
                error_count INT DEFAULT 0,
                last_used DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e) {
            error_log("Migration Error (smtp_servers): " . $e->getMessage());
        }
    }
}
