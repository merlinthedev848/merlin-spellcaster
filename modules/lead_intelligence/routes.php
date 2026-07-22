<?php
declare(strict_types=1);

require_once __DIR__ . '/Scraper.php';
require_once __DIR__ . '/Verifier.php';

// Route: /scraper/run
if ($routePath === '/scraper/run') {
    header('Content-Type: application/json');
    
    $keyword = trim($_GET['keyword'] ?? '');
    $depth = max(1, min(5, (int)($_GET['depth'] ?? 2)));
    $channel = trim($_GET['channel'] ?? 'all');
    $buyerType = trim($_GET['buyer_type'] ?? 'all');

    if (empty($keyword)) {
        echo json_encode(['status' => 'error', 'message' => 'Keyword parameter required']);
        exit;
    }

    try {
        $results = BuyerLeadScraper::scrape($keyword, $depth, $channel, $buyerType);
        echo json_encode([
            'status' => 'success',
            'count' => count($results),
            'data' => array_values($results)
        ]);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Route: /scraper/import
if ($routePath === '/scraper/import') {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'POST method required']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $leads = $data['leads'] ?? [];

    if (empty($leads)) {
        echo json_encode(['status' => 'error', 'message' => 'No leads provided for import']);
        exit;
    }

    $db = Database::getConnection();
    $imported = 0;
    $skipped = 0;

    $stCheck = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
    $stInsert = $db->prepare("INSERT INTO subscribers (email, first_name, last_name, phone, status, lead_score, created_at) VALUES (?, ?, ?, ?, 'active', 50, NOW())");
    $stTagSelect = $db->prepare("SELECT id FROM tags WHERE name = 'scraped-buyer'");
    $stTagInsert = $db->prepare("INSERT INTO tags (name, color) VALUES ('scraped-buyer', '#3b82f6')");
    $stLinkTag = $db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)");

    $stTagSelect->execute();
    $tagId = $stTagSelect->fetchColumn();
    if (!$tagId) {
        $stTagInsert->execute();
        $tagId = (int)$db->lastInsertId();
    } else {
        $tagId = (int)$tagId;
    }

    foreach ($leads as $lead) {
        $email = strtolower(trim($lead['email'] ?? ''));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $skipped++;
            continue;
        }

        $stCheck->execute([$email]);
        $existingId = $stCheck->fetchColumn();

        if ($existingId) {
            $skipped++;
            continue;
        }

        $fullName = trim($lead['name'] ?? 'Buyer Contact');
        $parts = explode(' ', $fullName, 2);
        $firstName = $parts[0] ?? 'Buyer';
        $lastName = $parts[1] ?? 'Contact';
        $phone = trim($lead['phone'] ?? '');

        try {
            $stInsert->execute([$email, $firstName, $lastName, $phone]);
            $newId = (int)$db->lastInsertId();

            if ($tagId > 0 && $newId > 0) {
                $stLinkTag->execute([$newId, $tagId]);
            }

            $imported++;
        } catch (Throwable $e) {
            $skipped++;
        }
    }

    echo json_encode([
        'status' => 'success',
        'imported' => $imported,
        'skipped' => $skipped
    ]);
    exit;
}

// Route: /lead-intelligence
if ($routePath === '/lead-intelligence') {
    $db = Database::getConnection();

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
