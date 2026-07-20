<?php
declare(strict_types=1);

/**
 * Scraper logic class.
 * Searches keywords on Ask.com search engine and fetches target pages to extract email addresses via regex.
 */
class SearchScraper {
    /**
     * Scrape search engines and match email regex patterns.
     */
    public static function scrape(string $keyword, int $depth = 3): array {
        $found = [];
        $query = urlencode($keyword . ' email contact "@gmail.com" OR "@yahoo.com" OR "@outlook.com"');
        
        // Use Ask.com as a fallback-free accessible endpoint for search scraping
        $searchUrls = [];
        for ($page = 1; $page <= $depth; $page++) {
            // Ask.com search pagination format
            $searchUrls[] = "https://www.ask.com/web?q={$query}&page={$page}";
        }

        foreach ($searchUrls as $url) {
            $html = self::fetch($url);
            if (empty($html)) continue;

            // Extract links to crawl further
            preg_match_all('/href=["\'](https?:\/\/[^"\']+)["\']/i', $html, $matches);
            $links = array_unique($matches[1] ?? []);

            // 1. Scrape raw text from search page snippets first
            $emails = self::extractEmails($html);
            foreach ($emails as $email) {
                $found[$email] = 'Search snippet result';
            }

            // 2. Crawl top 12 filtered links from search results (ignoring search engines & directories)
            $crawled = 0;
            foreach ($links as $link) {
                if ($crawled >= 12) break;
                
                // Skip common noise domains
                if (preg_match('/ask\.com|google\.com|bing\.com|yahoo\.com|youtube\.com|facebook\.com|twitter\.com|linkedin\.com|wikipedia\.org|pinterest\.com/i', $link)) {
                    continue;
                }

                $pageHtml = self::fetch($link);
                if (!empty($pageHtml)) {
                    $pageEmails = self::extractEmails($pageHtml);
                    foreach ($pageEmails as $email) {
                        if (!isset($found[$email])) {
                            $found[$email] = 'Crawled from: ' . parse_url($link, PHP_URL_HOST);
                        }
                    }
                    $crawled++;
                }
                
                // Rate limit slightly to prevent blocking
                usleep(200000); 
            }
        }

        return $found;
    }

    /**
     * Extracts email addresses from string contents using standard regex
     */
    private static function extractEmails(string $text): array {
        $pattern = '/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.[a-z0-9\-\.]+/i';
        preg_match_all($pattern, $text, $matches);
        
        $clean = [];
        if (!empty($matches[0])) {
            foreach ($matches[0] as $email) {
                $email = strtolower(trim($email, '.'));
                // Prevent extracting image references or assets
                if (preg_match('/\.(jpg|png|gif|jpeg|svg|css|js|webp)$/i', $email)) {
                    continue;
                }
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $clean[] = $email;
                }
            }
        }
        return array_unique($clean);
    }

    /**
     * Fetch raw HTML with user agent headers
     */
    private static function fetch(string $url): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36');
        
        $html = curl_exec($ch);
        curl_close($ch);
        return $html !== false ? (string)$html : '';
    }
}
