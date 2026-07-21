<?php
declare(strict_types=1);

require_once __DIR__ . '/../lead_scrapers/Scraper.php';
require_once __DIR__ . '/../deliverability_suite/Verifier.php';

// Route: /lead-intelligence
if ($routePath === '/lead-intelligence') {
    $db = Database::getConnection();

    // Fetch Stats
    $totalVerifier = (int)$db->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();
    $activeVerifier = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE status = 'active'")->fetchColumn();
    $bouncedVerifier = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE status = 'bounced'")->fetchColumn();
    $unsubsVerifier = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE status = 'unsubscribed'")->fetchColumn();

    $enrichableContacts = $db->query("
        SELECT s.* 
        FROM subscribers s
        WHERE s.email LIKE '%@%' AND s.email NOT LIKE '%@gmail.com' AND s.email NOT LIKE '%@yahoo.%' AND s.email NOT LIKE '%@hotmail.%' AND s.email NOT LIKE '%@outlook.%'
        ORDER BY s.created_at DESC
        LIMIT 50
    ")->fetchAll();

    $campaigns = $db->query("SELECT id, name, subject FROM campaigns ORDER BY created_at DESC")->fetchAll();

    $tab = $_GET['tab'] ?? 'scraper';
    $title = 'Lead Acquisition & Intelligence Hub';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

// Redirect aliases
if (in_array($routePath, ['/scraper', '/maps-scraper', '/enrichment', '/deliverability'], true)) {
    $aliasTab = match($routePath) {
        '/scraper' => 'scraper',
        '/maps-scraper' => 'maps',
        '/enrichment' => 'enrichment',
        '/deliverability' => 'verifier',
        default => 'scraper'
    };
    header('Location: ' . getSetting('app_url') . '/lead-intelligence?tab=' . $aliasTab);
    exit;
}
