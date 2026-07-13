<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$slug = $_GET['s'] ?? '';
if (!$slug) {
    header('Location: /');
    exit;
}

try {
    $stmt = $db->prepare("SELECT destinations, clicks FROM mod_link_rotators WHERE slug = ?");
    $stmt->execute([$slug]);
    $link = $stmt->fetch();

    if ($link) {
        $dests = json_decode($link['destinations'], true);
        if ($dests && count($dests) > 0) {
            // Update click count
            $db->prepare("UPDATE mod_link_rotators SET clicks = clicks + 1 WHERE slug = ?")->execute([$slug]);
            
            // Pick a destination based on total clicks (simple round robin)
            $index = $link['clicks'] % count($dests);
            $target = $dests[$index];
            
            header("Location: $target");
            exit;
        }
    }
} catch (Throwable $e) {}

// Fallback
header('Location: /');
exit;
