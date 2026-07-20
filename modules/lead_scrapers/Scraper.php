<?php
declare(strict_types=1);

/**
 * Advanced Search Scraper Engine
 * Crawls Ask.com, Bing, and Yahoo Search to extract email addresses, 
 * with recursive deep crawling (2-levels) on target domains.
 */
class SearchScraper {
    /**
     * Run multi-channel search engine scraping and crawl domains recursively.
     */
    public static function scrape(string $keyword, int $depth = 2, string $channel = 'all'): array {
        $found = [];
        $query = urlencode($keyword . ' email contact "@gmail.com" OR "@yahoo.com" OR "@outlook.com"');
        
        $urls = [];
        for ($page = 1; $page <= $depth; $page++) {
            $offset = ($page - 1) * 10 + 1;
            
            if ($channel === 'all' || $channel === 'ask') {
                $urls[] = ['url' => "https://www.ask.com/web?q={$query}&page={$page}", 'source' => 'Ask.com'];
            }
            if ($channel === 'all' || $channel === 'bing') {
                $urls[] = ['url' => "https://www.bing.com/search?q={$query}&first={$offset}", 'source' => 'Bing'];
            }
            if ($channel === 'all' || $channel === 'yahoo') {
                $urls[] = ['url' => "https://search.yahoo.com/search?p={$query}&b={$offset}", 'source' => 'Yahoo'];
            }
        }

        $crawledLinks = [];
        foreach ($urls as $item) {
            $html = self::fetch($item['url']);
            if (empty($html)) continue;

            // 1. Extract links to crawl further
            preg_match_all('/href=["\'](https?:\/\/[^"\']+)["\']/i', $html, $matches);
            $links = array_unique($matches[1] ?? []);

            // 2. Extract emails from snippets first
            $emails = self::extractEmails($html);
            foreach ($emails as $email) {
                $found[$email] = [
                    'source' => $item['source'] . ' snippet search',
                    'domain' => explode('@', $email)[1] ?? 'unknown'
                ];
            }

            // 3. Crawl target domains recursively up to 10 unique domains per search query
            $domainsCount = 0;
            foreach ($links as $link) {
                if ($domainsCount >= 8) break;

                // Skip search engines and major portals
                if (preg_match('/ask\.com|google\.|bing\.com|yahoo\.com|youtube\.com|facebook\.com|twitter\.com|linkedin\.com|wikipedia\.org|pinterest\.com|instagram\.com|microsoft\.com/i', $link)) {
                    continue;
                }

                $host = parse_url($link, PHP_URL_HOST);
                if (empty($host) || in_array($host, $crawledLinks, true)) continue;
                $crawledLinks[] = $host;
                $domainsCount++;

                // Fetch Home Page
                $homeHtml = self::fetch($link);
                if (empty($homeHtml)) continue;

                // Extract emails from Home Page
                $homeEmails = self::extractEmails($homeHtml);
                foreach ($homeEmails as $email) {
                    if (!isset($found[$email])) {
                        $found[$email] = [
                            'source' => $item['source'] . ' crawl: ' . $host,
                            'domain' => $host
                        ];
                    }
                }

                // Level 2: Extract inner links for deep contact pages crawling
                preg_match_all('/href=["\'](\/[^"\']+|https?:\/\/' . preg_quote($host, '/') . '[^"\']+)["\']/i', $homeHtml, $innerMatches);
                $innerLinks = array_unique($innerMatches[1] ?? []);

                $innerCrawled = 0;
                foreach ($innerLinks as $inner) {
                    if ($innerCrawled >= 2) break; // Limit deep crawls to 2 pages per domain

                    // Filter only relevant contacts pages
                    if (!preg_match('/contact|about|support|team|help|info/i', $inner)) {
                        continue;
                    }

                    // Build absolute URL
                    $absoluteUrl = $inner;
                    if (str_starts_with($inner, '/')) {
                        $absoluteUrl = 'http://' . $host . $inner;
                    }

                    $innerHtml = self::fetch($absoluteUrl);
                    if (!empty($innerHtml)) {
                        $innerEmails = self::extractEmails($innerHtml);
                        foreach ($innerEmails as $email) {
                            if (!isset($found[$email])) {
                                $found[$email] = [
                                    'source' => $item['source'] . ' deep-crawl: ' . $host,
                                    'domain' => $host
                                ];
                            }
                        }
                        $innerCrawled++;
                    }
                    usleep(100000); // polite sleep
                }
            }
        }

        return $found;
    }

    /**
     * Helper to extract emails from raw string text content
     */
    private static function extractEmails(string $text): array {
        $pattern = '/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.[a-z0-9\-\.]+/i';
        preg_match_all($pattern, $text, $matches);
        
        $clean = [];
        if (!empty($matches[0])) {
            foreach ($matches[0] as $email) {
                $email = strtolower(trim($email, '.'));
                if (preg_match('/\.(jpg|png|gif|jpeg|svg|css|js|webp|ico|woff)$/i', $email)) {
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
     * Fetch HTML with user agent headers
     */
    private static function fetch(string $url): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36');
        
        $html = curl_exec($ch);
        curl_close($ch);
        return $html !== false ? (string)$html : '';
    }
}
