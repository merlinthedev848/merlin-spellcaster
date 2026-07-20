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
    $secret = getSetting('app_secret');
    if (empty($secret)) {
        $secret = bin2hex(random_bytes(32));
        setSetting('app_secret', $secret);
    }
    return hash_hmac('sha256', "{$email}:{$campaignId}:{$subscriberId}", $secret);
}

/**
 * Perform free GeoIP API lookup for location details
 */
function sc_geoip_lookup(string $ip): array {
    if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') {
        return ['country_code' => '', 'country_name' => '', 'city' => 'Localhost'];
    }
    $url = "https://ip-api.com/json/" . urlencode($ip);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    
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
    if (empty($ip)) return;
    try {
        $db = Database::getConnection();
        $geo = sc_geoip_lookup($ip);
        $st = $db->prepare("UPDATE subscribers SET ip_address = ?, country_code = ?, country_name = ?, city = ? WHERE id = ?");
        $st->execute([$ip, $geo['country_code'], $geo['country_name'], $geo['city'], $subscriberId]);
    } catch (Throwable $e) {
        error_log('sc_update_subscriber_geoip error: ' . $e->getMessage());
    }
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
            status VARCHAR(32) DEFAULT 'draft',
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
            step_type VARCHAR(64) NOT NULL,
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
    // Version guard — only run migrations when schema is outdated
    $currentVersion = (int)getSetting('schema_version', '0');
    if ($currentVersion >= 16) return;

    // Ensure campaigns.status is VARCHAR(32) so 'active' and 'inactive' are valid
    try {
        $db->exec("ALTER TABLE campaigns MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'draft'");
    } catch (Throwable $e) {
        error_log("Migration Error (campaigns status varchar): " . $e->getMessage());
    }
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
    
    // Convert automation_steps.step_type from ENUM to VARCHAR(64) to support modular expansion
    try {
        $db->exec("ALTER TABLE automation_steps MODIFY COLUMN step_type VARCHAR(64) NOT NULL DEFAULT 'wait'");
    } catch (Throwable $e) {
        error_log("Migration Error (step_type varchar): " . $e->getMessage());
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

    // 3. Ensure automation_steps.step_type is VARCHAR(64) — never ENUM
    // (ENUM was removed; this is a no-op guard for databases that already have VARCHAR)

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

    // 7. Create contact_visits table for Web Tracking module
    try {
        $db->query("SELECT 1 FROM contact_visits LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS contact_visits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                subscriber_id INT NOT NULL,
                url VARCHAR(2048) NOT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                visited_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e) {
            error_log("Migration Error (contact_visits): " . $e->getMessage());
        }
    }

    // 8. Create seo_reports table for SEO Auditor module
    try {
        $db->query("SELECT 1 FROM seo_reports LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS seo_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                url VARCHAR(2048) NOT NULL,
                score INT DEFAULT 0,
                title VARCHAR(255) DEFAULT NULL,
                meta_description TEXT,
                h1_tags TEXT,
                word_count INT DEFAULT 0,
                recommendations TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e) {
            error_log("Migration Error (seo_reports): " . $e->getMessage());
        }
    }

    // 9. Create backlink_submissions table for Backlink Builder module
    try {
        $db->query("SELECT 1 FROM backlink_submissions LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS backlink_submissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                directory_name VARCHAR(128) NOT NULL,
                directory_url VARCHAR(2048) NOT NULL,
                target_url VARCHAR(2048) NOT NULL,
                status ENUM('pending', 'submitted', 'live', 'rejected') DEFAULT 'pending',
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e) {
            error_log("Migration Error (backlink_submissions): " . $e->getMessage());
        }
    }

    // 10. Add exclude_tag_id to automations table
    try {
        $db->query("SELECT exclude_tag_id FROM automations LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("ALTER TABLE automations ADD COLUMN exclude_tag_id INT DEFAULT NULL, ADD FOREIGN KEY (exclude_tag_id) REFERENCES tags(id) ON DELETE SET NULL");
        } catch (Throwable $e) {
            error_log("Migration Error (automations exclude_tag_id): " . $e->getMessage());
        }
    }

    // 11. Add phone and lead_score columns to subscribers
    try { $db->query("SELECT phone FROM subscribers LIMIT 1"); } catch (PDOException) {
        try { $db->exec("ALTER TABLE subscribers ADD COLUMN phone VARCHAR(30) DEFAULT NULL"); } catch (Throwable $e) { error_log('Migration Error (phone): ' . $e->getMessage()); }
    }
    try { $db->query("SELECT lead_score FROM subscribers LIMIT 1"); } catch (PDOException) {
        try { $db->exec("ALTER TABLE subscribers ADD COLUMN lead_score INT DEFAULT 0"); } catch (Throwable $e) { error_log('Migration Error (lead_score): ' . $e->getMessage()); }
    }

    // 12. Add status column to subscriber_lists
    try { $db->query("SELECT status FROM subscriber_lists LIMIT 1"); } catch (PDOException) {
        try { $db->exec("ALTER TABLE subscriber_lists ADD COLUMN status VARCHAR(20) DEFAULT 'active'"); } catch (Throwable $e) { error_log('Migration Error (subscriber_lists.status): ' . $e->getMessage()); }
    }

    // 13. Add ab_subject column to email_queue
    try { $db->query("SELECT ab_subject FROM email_queue LIMIT 1"); } catch (PDOException) {
        try { $db->exec("ALTER TABLE email_queue ADD COLUMN ab_subject VARCHAR(500) DEFAULT NULL"); } catch (Throwable $e) { error_log('Migration Error (ab_subject): ' . $e->getMessage()); }
    }

    // 14. Create mod_sms_logs table
    try {
        $db->query("SELECT 1 FROM mod_sms_logs LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS mod_sms_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                subscriber_id INT DEFAULT NULL,
                message TEXT,
                status VARCHAR(20) DEFAULT 'sent',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e) {
            error_log('Migration Error (mod_sms_logs): ' . $e->getMessage());
        }
    }

    // 15. Add tracking metadata columns to campaign_clicks
    try {
        $db->query("SELECT user_agent FROM campaign_clicks LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("ALTER TABLE campaign_clicks ADD COLUMN user_agent VARCHAR(512) DEFAULT '' AFTER ip_address");
        } catch (Throwable $e) {
            error_log('Migration Error (campaign_clicks.user_agent): ' . $e->getMessage());
        }
    }
    try {
        $db->query("SELECT referrer FROM campaign_clicks LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("ALTER TABLE campaign_clicks ADD COLUMN referrer VARCHAR(1024) DEFAULT '' AFTER user_agent");
        } catch (Throwable $e) {
            error_log('Migration Error (campaign_clicks.referrer): ' . $e->getMessage());
        }
    }
    try {
        $db->query("SELECT click_type FROM campaign_clicks LIMIT 1");
    } catch (PDOException) {
        try {
            $db->exec("ALTER TABLE campaign_clicks ADD COLUMN click_type VARCHAR(32) DEFAULT 'js' AFTER referrer");
        } catch (Throwable $e) {
            error_log('Migration Error (campaign_clicks.click_type): ' . $e->getMessage());
        }
    }

    // Mark schema as up-to-date
    setSetting('schema_version', '16');
}

/**
 * Flash a session message
 */
function flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_' . $type] = $message;
}

/**
 * Redirect to a specific URL path and exit
 */
function sc_redirect(string $path): void {
    $url = getSetting('app_url', (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]");
    $url = rtrim($url, '/');
    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }
    header('Location: ' . $url . $path);
    exit;
}
