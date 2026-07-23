<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modules/lead_intelligence/Scraper.php';

$offering = 'British Voiceover Services';
echo "Running test scrape for: {$offering}...\n";

$results = BuyerLeadScraper::scrape($offering, 1, 'all');

echo "Found " . count($results) . " results:\n";
print_r($results);
