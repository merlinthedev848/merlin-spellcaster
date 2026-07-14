<?php
declare(strict_types=1);

if ($routePath === '/maps-scraper') {
    $title = 'Google Maps B2B Lead Generator';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

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
        
        // Mock scraping delay
        sleep(2);
        
        // Mock results based on query
        $mockResults = [
            ['name' => 'Acme ' . ucfirst($query), 'email' => 'contact@acme' . preg_replace('/[^a-z]/', '', strtolower($query)) . '.com', 'phone' => '+44 20 7946 0958', 'website' => 'https://acme' . preg_replace('/[^a-z]/', '', strtolower($query)) . '.com'],
            ['name' => 'Elite ' . ucfirst($query) . ' ' . ucfirst($location), 'email' => 'info@elite' . preg_replace('/[^a-z]/', '', strtolower($query)) . '.co.uk', 'phone' => '+44 20 7946 0123', 'website' => 'https://elite' . preg_replace('/[^a-z]/', '', strtolower($query)) . '.co.uk'],
            ['name' => 'Citywide ' . ucfirst($query), 'email' => 'hello@citywide' . preg_replace('/[^a-z]/', '', strtolower($query)) . '.com', 'phone' => '+44 20 7946 0888', 'website' => 'https://citywide' . preg_replace('/[^a-z]/', '', strtolower($query)) . '.com']
        ];
        
        $db->beginTransaction();

        // Ensure 'maps_lead' tag exists
        $stTag = $db->prepare("SELECT id FROM tags WHERE name = ?");
        $stTag->execute(['maps_lead']);
        $tagId = $stTag->fetchColumn();
        if (!$tagId) {
            $stIns = $db->prepare("INSERT INTO tags (name, color) VALUES (?, ?)");
            $stIns->execute(['maps_lead', '#dd6b20']); // orange
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
