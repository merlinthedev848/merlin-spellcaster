<?php
declare(strict_types=1);

/**
 * modules/market_research/cron/scraper_job.php
 * Run this via CLI: php scraper_job.php
 * Or it can be included by the main cron automation if we add a hook.
 */

if (php_sapi_name() !== 'cli' && !isset($_GET['secret'])) {
    die("CLI only or requires secret");
}

require_once dirname(__DIR__, 3) . '/config.php';

// Find pending jobs
$jobs = $db->query("SELECT * FROM mr_jobs WHERE status = 'pending' LIMIT 1")->fetchAll();
if (!$jobs) {
    echo "No pending jobs.\n";
    exit;
}

foreach ($jobs as $job) {
    echo "Starting job: {$job['keyword']}\n";
    $db->prepare("UPDATE mr_jobs SET status='running' WHERE id=?")->execute([$job['id']]);
    
    // Very basic DuckDuckGo HTML scrape
    $query = urlencode($job['keyword'] . ' "email"');
    $url = "https://html.duckduckgo.com/html/?q={$query}";
    
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
        ]
    ];
    $context = stream_context_create($opts);
    $html = @file_get_contents($url, false, $context);
    
    if (!$html) {
        $db->prepare("UPDATE mr_jobs SET status='failed' WHERE id=?")->execute([$job['id']]);
        continue;
    }
    
    // Extract URLs from results
    preg_match_all('/<a class="result__url" href="([^"]+)">/', $html, $matches);
    $urls = $matches[1] ?? [];
    
    $emailsFound = 0;
    foreach (array_slice($urls, 0, 5) as $link) { // limit to 5 pages per job to avoid massive runtime
        $link = urldecode(preg_replace('/^\/\//', 'https://', $link));
        if (!str_starts_with($link, 'http')) continue;
        
        echo "Scraping: $link\n";
        $pageHtml = @file_get_contents($link, false, $context);
        if (!$pageHtml) continue;
        
        // Extract emails
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $pageHtml, $emailMatches);
        $emails = array_unique($emailMatches[0] ?? []);
        
        foreach ($emails as $email) {
            $email = strtolower(trim($email));
            if (strlen($email) > 255) continue;
            // Basic filter out image extensions or common false positives
            if (preg_match('/\.(png|jpg|jpeg|gif|css|js|webp)$/', $email)) continue;
            
            try {
                $db->prepare("INSERT INTO mr_leads (email, source_url) VALUES (?, ?)")
                   ->execute([$email, $link]);
                $emailsFound++;
                echo "Found: $email\n";
            } catch (PDOException $e) {
                // duplicate email, ignore
            }
        }
        sleep(1); // polite delay
    }
    
    $db->prepare("UPDATE mr_jobs SET status='completed', pages_scraped=?, emails_found=?, updated_at=NOW() WHERE id=?")
       ->execute([count($urls), $emailsFound, $job['id']]);
}
echo "Done.\n";
