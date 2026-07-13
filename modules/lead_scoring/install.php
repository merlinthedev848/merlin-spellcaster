<?php
declare(strict_types=1);

/** @var PDO $db */
try {
    $db->exec("ALTER TABLE subscribers ADD COLUMN IF NOT EXISTS lead_score INT DEFAULT 0");
} catch (PDOException $e) {
    // Ignore if already exists or fails
}
