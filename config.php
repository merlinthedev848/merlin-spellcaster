<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep clean in production

require_once __DIR__ . '/core/ModuleManager.php';

// Core Application Configuration
define('APP_URL', 'https://mailer.chriskendall.media');

// Enhance MariaDB Credentials
$db_host = '127.0.0.1'; 
$db_name = 'enhance_db_name';
$db_user = 'enhance_db_user';
$db_pass = 'enhance_db_pass';

try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("Merlin encountered a database error: " . $e->getMessage());
}

// Auto-scaffold tables if missing
$tablesExist = $db->query("SHOW TABLES LIKE 'subscribers'")->rowCount() > 0;

if (!$tablesExist) {
    $db->exec("
        CREATE TABLE subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            attributes LONGTEXT DEFAULT '{}',
            status VARCHAR(50) DEFAULT 'active',
            CONSTRAINT fk_attributes_json CHECK (JSON_VALID(attributes)),
            INDEX idx_status (status)
        );
        CREATE TABLE campaigns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body_html LONGTEXT NOT NULL,
            status VARCHAR(50) DEFAULT 'draft'
        );
        CREATE TABLE email_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT,
            subscriber_id INT,
            status VARCHAR(50) DEFAULT 'pending',
            send_at INT NOT NULL,
            INDEX idx_delivery (status, send_at),
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
            FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
        );
        CREATE TABLE campaign_clicks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT,
            subscriber_id INT,
            clicked_at INT NOT NULL,
            INDEX idx_analytics (campaign_id, subscriber_id)
        );
        CREATE TABLE modules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            folder_name VARCHAR(255) UNIQUE NOT NULL,
            is_active TINYINT DEFAULT 0
        );
    ");

    // Seed dummy data for the dashboard UI
    $db->exec("INSERT IGNORE INTO modules (folder_name, is_active) VALUES ('slack_notify', 1)");
    $db->exec("INSERT IGNORE INTO subscribers (email, attributes) VALUES ('demo@example.com', '{\"tags\":[\"vip\"],\"score\":99}')");
}

// Load Active Modules into the Hook Engine
try {
    $stmt = $db->query("SELECT folder_name FROM modules WHERE is_active = 1");
    ModuleManager::loadModules($stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (Exception $e) {}