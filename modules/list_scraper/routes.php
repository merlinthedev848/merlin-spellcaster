<?php
declare(strict_types=1);

require_once __DIR__ . '/Scraper.php';

if ($routePath === '/scraper') {
    $db = Database::getConnection();

    // Fetch stats
    $stTag = $db->prepare("SELECT id FROM tags WHERE name = ?");
    $stTag->execute(['scraped']);
    $tagId = $stTag->fetchColumn();
    
    $scrapedCount = 0;
    if ($tagId) {
        $stCount = $db->prepare("SELECT COUNT(*) FROM subscriber_tags WHERE tag_id = ?");
        $stCount->execute([$tagId]);
        $scrapedCount = (int)$stCount->fetchColumn();
    }

    $title = 'Search Scraper Engine';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

if ($routePath === '/scraper/run') {
    header('Content-Type: application/json');
    
    $keyword = trim($_GET['keyword'] ?? '');
    $depth = min(max((int)($_GET['depth'] ?? 2), 1), 5);

    if (empty($keyword)) {
        echo json_encode(['success' => false, 'error' => 'Please provide a niche keyword.']);
        exit;
    }

    try {
        $results = SearchScraper::scrape($keyword, $depth);
        
        if (empty($results)) {
            echo json_encode(['success' => true, 'added' => 0, 'skipped' => 0, 'emails' => []]);
            exit;
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        // 1. Ensure 'scraped' tag exists
        $stTag = $db->prepare("SELECT id FROM tags WHERE name = ?");
        $stTag->execute(['scraped']);
        $tagId = $stTag->fetchColumn();
        if (!$tagId) {
            $stIns = $db->prepare("INSERT INTO tags (name, color) VALUES (?, ?)");
            $stIns->execute(['scraped', '#319795']); // teal color
            $tagId = (int)$db->lastInsertId();
        } else {
            $tagId = (int)$tagId;
        }

        $addedCount = 0;
        $skippedCount = 0;
        $importedEmails = [];

        // 2. Insert and tag contacts
        $stInsert = $db->prepare("
            INSERT INTO subscribers (email, first_name, last_name, status, created_at) 
            VALUES (?, '', '', 'active', NOW())
            ON DUPLICATE KEY UPDATE status = 'active'
        ");

        $stGet = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
        $stTagAssign = $db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)");

        foreach ($results as $email => $source) {
            // Apply a pre-verify deliverability check if EmailVerifier is available
            if (class_exists('EmailVerifier')) {
                $chk = EmailVerifier::verify($email);
                if (!$chk['valid']) {
                    $skippedCount++;
                    continue;
                }
            }

            try {
                $stInsert->execute([$email]);
                $stGet->execute([$email]);
                $subId = (int)$stGet->fetchColumn();

                if ($subId > 0) {
                    $stTagAssign->execute([$subId, $tagId]);
                    logActivity($subId, 'subscribe', "Discovered via scraper. Source: {$source}");
                    $addedCount++;
                    $importedEmails[] = $email;
                }
            } catch (Throwable $e) {
                $skippedCount++;
            }
        }

        $db->commit();
        echo json_encode([
            'success' => true, 
            'added' => $addedCount, 
            'skipped' => $skippedCount,
            'emails' => $importedEmails
        ]);

    } catch (Throwable $e) {
        if ($db && $db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
