<?php
declare(strict_types=1);

/**
 * modules/maps_scraper/cron/maps_worker.php
 * Run this via CLI: php maps_worker.php
 */

if (php_sapi_name() !== 'cli' && !isset($_GET['secret'])) {
    die("CLI only or requires secret");
}

require_once dirname(__DIR__, 3) . '/config.php';

// In a real implementation, you'd fetch from a `maps_jobs` table similar to `mr_jobs`.
// Since we are mocking the structure, we simulate processing a job.

$apiKey = getSetting('outscraper_api_key', '');
if (!$apiKey) {
    echo "Outscraper API key is not configured. Aborting.\n";
    exit;
}

// Simulated job data
$query = "Plumbers in Chicago";

echo "Starting Maps Scrape for query: $query\n";

// Real Outscraper API implementation
// https://api.app.outscraper.com/maps/search-v2
$url = "https://api.app.outscraper.com/maps/search-v2?query=" . urlencode($query) . "&limit=20&async=false";

$opts = [
    "http" => [
        "method" => "GET",
        "header" => "X-API-KEY: $apiKey\r\n"
    ]
];
$context = stream_context_create($opts);

echo "Calling Outscraper API...\n";
$response = @file_get_contents($url, false, $context);

if (!$response) {
    echo "API request failed.\n";
    exit;
}

$data = json_decode($response, true);
$emailsFound = 0;

if (isset($data['data'][0])) {
    foreach ($data['data'][0] as $place) {
        if (!empty($place['emails'])) {
            foreach ($place['emails'] as $email) {
                // Insert into the MR Leads table so it shows up in the Research Hub!
                try {
                    $db->prepare("INSERT INTO mr_leads (email, name, source_url) VALUES (?, ?, ?)")
                       ->execute([$email, $place['name'] ?? null, $place['site'] ?? null]);
                    $emailsFound++;
                    echo "Found: $email from {$place['name']}\n";
                } catch (PDOException $e) {
                    // Ignore duplicates
                }
            }
        }
    }
}

echo "Scrape complete. Found $emailsFound new emails.\n";
