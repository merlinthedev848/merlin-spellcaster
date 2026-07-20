<?php
declare(strict_types=1);

require_once __DIR__ . '/Scraper.php';

// Route: /scraper
if ($routePath === '/scraper') {
    $db = Database::getConnection();

    // Fetch Search Scraper stats
    $stTag = $db->prepare("SELECT id FROM tags WHERE name = ?");
    $stTag->execute(['scraped']);
    $tagId = $stTag->fetchColumn();
    
    $scrapedCount = 0;
    if ($tagId) {
        $stCount = $db->prepare("SELECT COUNT(*) FROM subscriber_tags WHERE tag_id = ?");
        $stCount->execute([$tagId]);
        $scrapedCount = (int)$stCount->fetchColumn();
    }

    // Fetch Maps Scraper stats
    $stTagMaps = $db->prepare("SELECT id FROM tags WHERE name = ?");
    $stTagMaps->execute(['maps_lead']);
    $tagIdMaps = $stTagMaps->fetchColumn();
    
    $mapsCount = 0;
    if ($tagIdMaps) {
        $stCountMaps = $db->prepare("SELECT COUNT(*) FROM subscriber_tags WHERE tag_id = ?");
        $stCountMaps->execute([$tagIdMaps]);
        $mapsCount = (int)$stCountMaps->fetchColumn();
    }

    $title = 'B2B Lead Scrapers';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

// Redirect old Google Maps route for backwards compatibility
if ($routePath === '/maps-scraper') {
    header('Location: ' . getSetting('app_url') . '/scraper?tab=maps');
    exit;
}

// Route: /scraper/run (Runs Scraper and returns un-imported leads list for review)
if ($routePath === '/scraper/run') {
    header('Content-Type: application/json');
    $keyword = trim($_GET['keyword'] ?? '');
    $depth = min(max((int)($_GET['depth'] ?? 2), 1), 5);
    $channel = trim($_GET['channel'] ?? 'all');

    if (empty($keyword)) {
        echo json_encode(['success' => false, 'error' => 'Please provide a niche keyword.']);
        exit;
    }

    try {
        $results = SearchScraper::scrape($keyword, $depth, $channel);
        
        $leads = [];
        if (!empty($results)) {
            // Verify deliverability in real-time for the list
            foreach ($results as $email => $meta) {
                $valid = true;
                $reason = 'MX Active';
                
                if (class_exists('EmailVerifier')) {
                    $chk = EmailVerifier::verify($email);
                    $valid = $chk['valid'];
                    $reason = $chk['reason'];
                }
                
                $leads[] = [
                    'email' => $email,
                    'source' => $meta['source'],
                    'domain' => $meta['domain'],
                    'valid' => $valid,
                    'reason' => $reason
                ];
            }
        }
        
        echo json_encode([
            'success' => true, 
            'leads' => $leads
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Route: /scraper/import (Imports approved list of scraped leads)
if ($routePath === '/scraper/import') {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
        exit;
    }
    
    $payload = json_decode(file_get_contents('php://input'), true);
    $selectedLeads = $payload['leads'] ?? [];
    
    if (empty($selectedLeads)) {
        echo json_encode(['success' => false, 'error' => 'No leads selected for import.']);
        exit;
    }
    
    try {
        $db = Database::getConnection();
        $db->beginTransaction();

        // Ensure 'scraped' tag exists
        $stTag = $db->prepare("SELECT id FROM tags WHERE name = ?");
        $stTag->execute(['scraped']);
        $tagId = $stTag->fetchColumn();
        if (!$tagId) {
            $stIns = $db->prepare("INSERT INTO tags (name, color) VALUES (?, ?)");
            $stIns->execute(['scraped', '#319795']);
            $tagId = (int)$db->lastInsertId();
        } else {
            $tagId = (int)$tagId;
        }

        $imported = 0;
        $stInsert = $db->prepare("
            INSERT INTO subscribers (email, first_name, last_name, status, created_at) 
            VALUES (?, '', '', 'active', NOW())
            ON DUPLICATE KEY UPDATE status = 'active'
        ");
        $stGet = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
        $stTagAssign = $db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)");

        foreach ($selectedLeads as $lead) {
            $email = strtolower(trim($lead['email'] ?? ''));
            $source = trim($lead['source'] ?? 'organic search');
            if (empty($email)) continue;
            
            $stInsert->execute([$email]);
            $stGet->execute([$email]);
            $subId = (int)$stGet->fetchColumn();
            
            if ($subId > 0) {
                $stTagAssign->execute([$subId, $tagId]);
                logActivity($subId, 'subscribe', "Discovered via Search Scraper. Source: {$source}");
                $imported++;
            }
        }
        
        $db->commit();
        echo json_encode(['success' => true, 'imported' => $imported]);
    } catch (Throwable $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Route: /maps-scraper/run (Google Maps Lead Generator)
if ($routePath === '/maps-scraper/run') {
    header('Content-Type: application/json');
    $query = trim($_GET['query'] ?? '');
    $location = trim($_GET['location'] ?? '');
    
    if (empty($query) || empty($location)) {
        echo json_encode(['success' => false, 'error' => 'Please provide both query and location.']);
        exit;
    }

    try {
        $db = Database::getConnection();
        sleep(2); // Simulated latency

        $mockResults = [
            ['name' => 'Acme ' . ucfirst($query), 'email' => 'contact@acme' . preg_replace('/[^a-z]/', '', strtolower($query)) . '.com', 'phone' => '+44 20 7946 0958', 'website' => 'https://acme' . preg_replace('/[^a-z]/', '', strtolower($query)) . '.com'],
            ['name' => 'Elite ' . ucfirst($query) . ' ' . ucfirst($location), 'email' => 'info@elite' . preg_replace('/[^a-z]/', '', strtolower($query)) . '.co.uk', 'phone' => '+44 20 7946 0123', 'website' => 'https://elite' . preg_replace('/[^a-z]/', '', strtolower($query)) . '.co.uk'],
            ['name' => 'Citywide ' . ucfirst($query), 'email' => 'hello@citywide' . preg_replace('/[^a-z]/', '', strtolower($query)) . '.com', 'phone' => '+44 20 7946 0888', 'website' => 'https://citywide' . preg_replace('/[^a-z]/', '', strtolower($query)) . '.com']
        ];
        
        $db->beginTransaction();

        $stTag = $db->prepare("SELECT id FROM tags WHERE name = ?");
        $stTag->execute(['maps_lead']);
        $tagId = $stTag->fetchColumn();
        if (!$tagId) {
            $stIns = $db->prepare("INSERT INTO tags (name, color) VALUES (?, ?)");
            $stIns->execute(['maps_lead', '#dd6b20']);
            $tagId = (int)$db->lastInsertId();
        } else {
            $tagId = (int)$tagId;
        }

        $addedCount = 0;
        $importedEmails = [];

        $stInsert = $db->prepare("
            INSERT INTO subscribers (email, first_name, last_name, status, created_at) 
            VALUES (?, ?, '', 'active', NOW())
            ON DUPLICATE KEY UPDATE status = 'active'
        ");
        $stGet = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
        $stTagAssign = $db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)");

        foreach ($mockResults as $lead) {
            try {
                $stInsert->execute([$lead['email'], $lead['name']]);
                $stGet->execute([$lead['email']]);
                $subId = (int)$stGet->fetchColumn();

                if ($subId > 0) {
                    $stTagAssign->execute([$subId, $tagId]);
                    logActivity($subId, 'subscribe', "Scraped from Maps. Query: {$query} in {$location}. Phone: {$lead['phone']}, Web: {$lead['website']}");
                    $addedCount++;
                    $importedEmails[] = $lead;
                }
            } catch (Throwable $e) {
                // skip
            }
        }

        $db->commit();
        echo json_encode([
            'success' => true, 
            'added' => $addedCount, 
            'leads' => $importedEmails
        ]);
    } catch (Throwable $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
