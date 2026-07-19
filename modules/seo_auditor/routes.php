<?php
declare(strict_types=1);

if ($routePath === '/seo-auditor') {
    $db = Database::getConnection();
    
    // Auto-create database tables
    $db->exec("CREATE TABLE IF NOT EXISTS seo_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        url VARCHAR(2048) NOT NULL,
        score INT DEFAULT 0,
        title VARCHAR(255) DEFAULT '',
        meta_description TEXT,
        h1_tags TEXT,
        word_count INT DEFAULT 0,
        recommendations TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $db->exec("CREATE TABLE IF NOT EXISTS backlink_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        directory_name VARCHAR(255) NOT NULL,
        directory_url VARCHAR(2048) NOT NULL,
        target_url VARCHAR(2048) DEFAULT '',
        status VARCHAR(32) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $title = 'SEO & Backlink Builder';
    $viewPath = __DIR__ . '/pages/ui.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}
