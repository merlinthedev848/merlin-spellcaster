<?php
/**
 * config.php — Merlin Spellcaster
 * Shared-hosting compatible: PHP 7.4+, MySQL 5.7+/MariaDB 10.2+
 * No Composer, no external dependencies required.
 *
 * Database credentials: create config.local.php (not committed to git) with:
 *   <?php
 *   $db_host = 'localhost';
 *   $db_name = 'yourdatabase';
 *   $db_user = 'youruser';
 *   $db_pass = 'yourpassword';
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/core/ModuleManager.php';

// ─── Constants ─────────────────────────────────────────────────────────────────
define('APP_VERSION', '2.0.0');
define('APP_ROOT',    __DIR__);
define('UPLOAD_DIR',  __DIR__ . '/uploads/');
define('UPLOAD_URL',  '/uploads/');

// ─── Database Credentials ─────────────────────────────────────────────────────
// Defaults — override via config.local.php
$db_host = 'localhost';
$db_name = 'spellcaster';
$db_user = 'root';
$db_pass = '';
$db_port = 3306;

// Local override (Enhance / shared hosting credentials go here)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// Environment variable support
if (getenv('DB_HOST') !== false) $db_host = getenv('DB_HOST');
if (getenv('DB_NAME') !== false) $db_name = getenv('DB_NAME');
if (getenv('DB_USER') !== false) $db_user = getenv('DB_USER');
if (getenv('DB_PASS') !== false) $db_pass = getenv('DB_PASS');
if (getenv('DB_PORT') !== false) $db_port = (int)getenv('DB_PORT');

// ─── PDO Connection ───────────────────────────────────────────────────────────
$db = null;
$GLOBALS['app_settings'] = [];
$GLOBALS['db_error'] = '';

try {
    $db = new PDO(
        "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 10,
        ]
    );
} catch (PDOException $e) {
    $GLOBALS['db_error'] = $e->getMessage();
    $uri = $_SERVER['PHP_SELF'] ?? '';
    $isSetup = (strpos($uri, '/setup') !== false);
    $isApi   = (strpos($uri, '/api/')  !== false);

    if ($isApi) {
        http_response_code(503);
        header('Content-Type: application/json');
        die(json_encode(['error' => 'Database unavailable']));
    } elseif (!$isSetup) {
        http_response_code(503);
        $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        die('<!DOCTYPE html><html><head><title>Database Error — Merlin Spellcaster</title>
<style>*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#0b0f19;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center}
.box{background:#111827;border:1px solid #1e293b;border-radius:16px;padding:48px;max-width:520px;text-align:center}
.icon{font-size:48px;margin-bottom:16px}
h1{color:#f87171;font-size:22px;margin-bottom:12px}
p{color:#94a3b8;line-height:1.7;margin-bottom:8px;font-size:15px}
code{background:#0b0f19;padding:8px 16px;border-radius:8px;display:block;margin:16px 0;font-size:13px;color:#fb923c;word-break:break-all}
a{display:inline-block;margin-top:20px;background:#6366f1;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px}
</style></head><body><div class="box">
<div class="icon">⚡</div>
<h1>Database Connection Error</h1>
<p>Merlin could not connect to the database. Please run the setup wizard to configure your credentials.</p>
<code>' . $msg . '</code>
<a href="/setup/">Run Setup Wizard</a>
</div></body></html>');
    }
}

// ─── Schema Bootstrap ─────────────────────────────────────────────────────────
if ($db !== null) {
    _sc_bootstrapSchema($db);
    $GLOBALS['app_settings'] = _sc_loadSettings($db);
    _sc_loadModules($db);
}

// ─────────────────────────────────────────────────────────────────────────────

function _sc_bootstrapSchema(PDO $db): void
{
    // Check schema version
    $version = 0;
    try {
        $chk = $db->query("SELECT COUNT(*) FROM information_schema.TABLES 
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings'");
        if ($chk && (int)$chk->fetchColumn() > 0) {
            $row = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'schema_version'")->fetch();
            $version = (int)($row['setting_value'] ?? 0);
        }
    } catch (Throwable $e) {
        $version = 0;
    }

    if ($version >= 2) return;

    // Use CREATE TABLE IF NOT EXISTS for idempotent migrations
    $tables = [];

    $tables[] = "CREATE TABLE IF NOT EXISTS `settings` (
        `setting_key`   VARCHAR(100)  NOT NULL,
        `setting_value` LONGTEXT,
        `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `users` (
        `id`            INT          NOT NULL AUTO_INCREMENT,
        `name`          VARCHAR(255) NOT NULL,
        `email`         VARCHAR(255) NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `role`          VARCHAR(50)  NOT NULL DEFAULT 'admin',
        `api_token`     VARCHAR(64)  DEFAULT NULL,
        `last_login`    DATETIME     DEFAULT NULL,
        `created_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `subscribers` (
        `id`          INT          NOT NULL AUTO_INCREMENT,
        `email`       VARCHAR(255) NOT NULL,
        `first_name`  VARCHAR(100) NOT NULL DEFAULT '',
        `last_name`   VARCHAR(100) NOT NULL DEFAULT '',
        `phone`       VARCHAR(50)  NOT NULL DEFAULT '',
        `attributes`  LONGTEXT              DEFAULT NULL COMMENT 'JSON key-value pairs',
        `tags`        TEXT                  DEFAULT NULL COMMENT 'JSON array of strings',
        `status`      VARCHAR(20)  NOT NULL DEFAULT 'active' COMMENT 'active|unsubscribed|bounced|complained',
        `source`      VARCHAR(100) NOT NULL DEFAULT 'manual',
        `ip_address`  VARCHAR(45)           DEFAULT NULL,
        `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_email` (`email`),
        KEY `idx_status`  (`status`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `lists` (
        `id`               INT          NOT NULL AUTO_INCREMENT,
        `name`             VARCHAR(255) NOT NULL,
        `description`      TEXT         DEFAULT NULL,
        `type`             VARCHAR(20)  NOT NULL DEFAULT 'public' COMMENT 'public|private',
        `optin_confirm`    TINYINT(1)   NOT NULL DEFAULT 0,
        `subscriber_count` INT          NOT NULL DEFAULT 0,
        `created_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `subscriber_lists` (
        `subscriber_id` INT         NOT NULL,
        `list_id`       INT         NOT NULL,
        `status`        VARCHAR(20) NOT NULL DEFAULT 'confirmed' COMMENT 'confirmed|pending|unsubscribed',
        `subscribed_at` DATETIME    DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`subscriber_id`, `list_id`),
        KEY `idx_list` (`list_id`),
        CONSTRAINT `fk_sl_subscriber` FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_sl_list`       FOREIGN KEY (`list_id`)       REFERENCES `lists`(`id`)       ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `templates` (
        `id`         INT          NOT NULL AUTO_INCREMENT,
        `name`       VARCHAR(255) NOT NULL,
        `subject`    VARCHAR(500) NOT NULL DEFAULT '',
        `body_html`  LONGTEXT     NOT NULL,
        `body_text`  LONGTEXT     DEFAULT NULL,
        `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `campaigns` (
        `id`           INT          NOT NULL AUTO_INCREMENT,
        `name`         VARCHAR(255) NOT NULL,
        `subject`      VARCHAR(500) NOT NULL,
        `from_name`    VARCHAR(255) NOT NULL DEFAULT '',
        `from_email`   VARCHAR(255) NOT NULL DEFAULT '',
        `reply_to`     VARCHAR(255) NOT NULL DEFAULT '',
        `template_id`  INT                   DEFAULT NULL,
        `body_html`    LONGTEXT     NOT NULL DEFAULT '',
        `body_text`    LONGTEXT              DEFAULT NULL,
        `status`       VARCHAR(20)  NOT NULL DEFAULT 'draft' COMMENT 'draft|scheduled|sending|sent|paused|cancelled',
        `type`         VARCHAR(20)  NOT NULL DEFAULT 'regular' COMMENT 'regular|automated|transactional',
        `scheduled_at` DATETIME              DEFAULT NULL,
        `started_at`   DATETIME              DEFAULT NULL,
        `sent_at`      DATETIME              DEFAULT NULL,
        `send_count`   INT          NOT NULL DEFAULT 0,
        `open_count`   INT          NOT NULL DEFAULT 0,
        `click_count`  INT          NOT NULL DEFAULT 0,
        `bounce_count` INT          NOT NULL DEFAULT 0,
        `unsub_count`  INT          NOT NULL DEFAULT 0,
        `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
        `updated_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `campaign_lists` (
        `campaign_id` INT NOT NULL,
        `list_id`     INT NOT NULL,
        PRIMARY KEY (`campaign_id`, `list_id`),
        CONSTRAINT `fk_cl_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_cl_list`     FOREIGN KEY (`list_id`)     REFERENCES `lists`(`id`)     ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `email_queue` (
        `id`            INT         NOT NULL AUTO_INCREMENT,
        `campaign_id`   INT         NOT NULL,
        `subscriber_id` INT         NOT NULL,
        `status`        VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending|sending|sent|failed|bounced',
        `send_at`       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `sent_at`       DATETIME             DEFAULT NULL,
        `error_message` TEXT                 DEFAULT NULL,
        `attempts`      INT         NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_delivery` (`status`, `send_at`),
        CONSTRAINT `fk_eq_campaign`   FOREIGN KEY (`campaign_id`)   REFERENCES `campaigns`(`id`)   ON DELETE CASCADE,
        CONSTRAINT `fk_eq_subscriber` FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `campaign_opens` (
        `id`            INT          NOT NULL AUTO_INCREMENT,
        `campaign_id`   INT          NOT NULL,
        `subscriber_id` INT          NOT NULL,
        `opened_at`     DATETIME     DEFAULT CURRENT_TIMESTAMP,
        `ip_address`    VARCHAR(45)  DEFAULT NULL,
        `user_agent`    VARCHAR(500) DEFAULT NULL,
        `is_unique`     TINYINT(1)   NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `idx_campaign`    (`campaign_id`),
        KEY `idx_subscriber`  (`subscriber_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `campaign_clicks` (
        `id`            INT           NOT NULL AUTO_INCREMENT,
        `campaign_id`   INT                    DEFAULT NULL,
        `subscriber_id` INT                    DEFAULT NULL,
        `url`           VARCHAR(2000)          DEFAULT NULL,
        `clicked_at`    DATETIME      DEFAULT CURRENT_TIMESTAMP,
        `ip_address`    VARCHAR(45)            DEFAULT NULL,
        `is_unique`     TINYINT(1)    NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `idx_analytics` (`campaign_id`, `subscriber_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `email_bounces` (
        `id`            INT          NOT NULL AUTO_INCREMENT,
        `campaign_id`   INT                   DEFAULT NULL,
        `subscriber_id` INT                   DEFAULT NULL,
        `email`         VARCHAR(255) NOT NULL,
        `type`          VARCHAR(10)  NOT NULL DEFAULT 'soft' COMMENT 'hard|soft',
        `reason`        TEXT                  DEFAULT NULL,
        `bounced_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `forms` (
        `id`              INT          NOT NULL AUTO_INCREMENT,
        `name`            VARCHAR(255) NOT NULL,
        `list_id`         INT                   DEFAULT NULL,
        `headline`        VARCHAR(500) NOT NULL DEFAULT 'Subscribe to our newsletter',
        `description`     TEXT                  DEFAULT NULL,
        `button_text`     VARCHAR(100) NOT NULL DEFAULT 'Subscribe',
        `success_message` TEXT         NOT NULL DEFAULT 'Thank you for subscribing!',
        `redirect_url`    VARCHAR(500) NOT NULL DEFAULT '',
        `show_name`       TINYINT(1)   NOT NULL DEFAULT 1,
        `require_name`    TINYINT(1)   NOT NULL DEFAULT 0,
        `double_optin`    TINYINT(1)   NOT NULL DEFAULT 0,
        `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `automation_sequences` (
        `id`             INT          NOT NULL AUTO_INCREMENT,
        `name`           VARCHAR(255) NOT NULL,
        `trigger_event`  VARCHAR(100) NOT NULL DEFAULT 'subscribe',
        `list_id`        INT                   DEFAULT NULL,
        `status`         VARCHAR(20)  NOT NULL DEFAULT 'paused' COMMENT 'active|paused',
        `enrolled_count` INT          NOT NULL DEFAULT 0,
        `created_at`     DATETIME     DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `automation_steps` (
        `id`          INT          NOT NULL AUTO_INCREMENT,
        `sequence_id` INT          NOT NULL,
        `step_order`  INT          NOT NULL DEFAULT 0,
        `type`        VARCHAR(20)  NOT NULL DEFAULT 'email' COMMENT 'email|wait|tag|webhook',
        `delay_days`  INT          NOT NULL DEFAULT 0,
        `delay_hours` INT          NOT NULL DEFAULT 0,
        `subject`     VARCHAR(500) NOT NULL DEFAULT '',
        `body_html`   LONGTEXT              DEFAULT NULL,
        `tag_action`  VARCHAR(50)  NOT NULL DEFAULT 'add',
        `tag_value`   VARCHAR(255) NOT NULL DEFAULT '',
        `webhook_url` VARCHAR(500) NOT NULL DEFAULT '',
        PRIMARY KEY (`id`),
        CONSTRAINT `fk_as_sequence` FOREIGN KEY (`sequence_id`) REFERENCES `automation_sequences`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `automation_queue` (
        `id`            INT         NOT NULL AUTO_INCREMENT,
        `sequence_id`   INT                  DEFAULT NULL,
        `step_id`       INT                  DEFAULT NULL,
        `subscriber_id` INT                  DEFAULT NULL,
        `scheduled_at`  DATETIME             DEFAULT NULL,
        `completed_at`  DATETIME             DEFAULT NULL,
        `status`        VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending|done|failed',
        PRIMARY KEY (`id`),
        KEY `idx_schedule` (`status`, `scheduled_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `modules` (
        `id`          INT          NOT NULL AUTO_INCREMENT,
        `folder_name` VARCHAR(255) NOT NULL,
        `is_active`   TINYINT(1)   NOT NULL DEFAULT 0,
        `description` TEXT                  DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_folder` (`folder_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `media` (
        `id`            INT          NOT NULL AUTO_INCREMENT,
        `filename`      VARCHAR(255) NOT NULL,
        `original_name` VARCHAR(255) NOT NULL,
        `file_path`     VARCHAR(500) NOT NULL,
        `file_size`     INT          NOT NULL DEFAULT 0,
        `mime_type`     VARCHAR(100) NOT NULL DEFAULT '',
        `width`         INT          NOT NULL DEFAULT 0,
        `height`        INT          NOT NULL DEFAULT 0,
        `uploaded_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables[] = "CREATE TABLE IF NOT EXISTS `activity_log` (
        `id`          INT          NOT NULL AUTO_INCREMENT,
        `user_id`     INT                   DEFAULT NULL,
        `action`      VARCHAR(255) NOT NULL DEFAULT '',
        `entity_type` VARCHAR(100) NOT NULL DEFAULT '',
        `entity_id`   INT                   DEFAULT NULL,
        `details`     TEXT                  DEFAULT NULL,
        `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_created` (`created_at`),
        KEY `idx_entity`  (`entity_type`, `entity_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    foreach ($tables as $sql) {
        try { $db->exec($sql); } catch (Throwable $e) { /* ignore already-exists */ }
    }

    // Seed default settings
    $defaults = [
        'schema_version'      => '2',
        'smtp_host'           => 'localhost',
        'smtp_port'           => '587',
        'smtp_user'           => '',
        'smtp_pass'           => '',
        'smtp_encryption'     => 'tls',
        'smtp_from_name'      => 'Merlin Spellcaster',
        'smtp_from_email'     => 'noreply@example.com',
        'app_name'            => 'Merlin Spellcaster',
        'app_url'             => 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
        'tracking_enabled'    => '1',
        'cron_batch_size'     => '50',
        'cron_secret'         => bin2hex(random_bytes(16)),
        'setup_complete'      => '0',
        'unsubscribe_message' => 'You have been successfully unsubscribed.',
        'company_name'        => '',
        'company_address'     => '',
    ];

    $stmt = $db->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    // Seed module
    $db->exec("INSERT IGNORE INTO modules (folder_name, is_active, description)
               VALUES ('slack_notify', 0, 'Sends Slack/webhook notifications when subscribers interact with campaigns.')");
}

function _sc_loadSettings(PDO $db): array
{
    $settings = [];
    try {
        foreach ($db->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $row) {
            $settings[$row['setting_key']] = (string)$row['setting_value'];
        }
    } catch (Throwable $e) {}
    return $settings;
}

function _sc_loadModules(PDO $db): void
{
    try {
        $mods = $db->query("SELECT folder_name FROM modules WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
        ModuleManager::loadModules($mods);
    } catch (Throwable $e) {}
}

define('APP_URL', $GLOBALS['app_settings']['app_url'] ?? 'http://localhost');

// ─── Helper Functions (PHP 7.4 compatible) ────────────────────────────────────

function getSetting(string $key, string $default = ''): string
{
    return (string)($GLOBALS['app_settings'][$key] ?? $default);
}

function setSetting(PDO $db, string $key, string $value): void
{
    $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
       ->execute([$key, $value]);
    $GLOBALS['app_settings'][$key] = $value;
}

function logActivity(PDO $db, ?int $userId, string $action, string $entityType, ?int $entityId = null, string $details = ''): void
{
    try {
        $db->prepare("INSERT INTO activity_log (user_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)")
           ->execute([$userId, $action, $entityType, $entityId, $details]);
    } catch (Throwable $e) {}
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function sc_redirect(string $url): void
{
    header("Location: $url");
    exit();
}

function isSetupComplete(): bool
{
    return getSetting('setup_complete') === '1';
}

function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/** HTML-escape shorthand */
function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function updateListCounts(PDO $db): void
{
    try {
        $db->exec("UPDATE lists l SET subscriber_count = (
            SELECT COUNT(*) FROM subscriber_lists sl
            WHERE sl.list_id = l.id AND sl.status = 'confirmed'
        )");
    } catch (Throwable $e) {}
}

function generateToken(string $email, int $campaignId, int $subscriberId): string
{
    return hash_hmac('sha256', "{$email}:{$campaignId}:{$subscriberId}", getSetting('cron_secret', 'secret'));
}

function formatNumber(int $n): string
{
    if ($n >= 1000000) return round($n / 1000000, 1) . 'M';
    if ($n >= 1000)    return round($n / 1000, 1) . 'K';
    return (string)$n;
}

function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}