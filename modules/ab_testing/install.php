<?php
declare(strict_types=1);

/** @var PDO $db */
$db->exec("
CREATE TABLE IF NOT EXISTS mod_ab_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    variant_a_subject VARCHAR(255) NOT NULL,
    variant_b_subject VARCHAR(255) NOT NULL,
    winner_chosen VARCHAR(10) DEFAULT NULL,
    test_started_at DATETIME DEFAULT NULL,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
