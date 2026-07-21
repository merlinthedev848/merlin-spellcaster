<?php
declare(strict_types=1);

/**
 * Advanced Intent-Based Lead Scraper & Search Engine Crawler Engine
 * 
 * Searches Bing, DuckDuckGo, Yahoo, Ask, and Google for target buyer intent queries 
 * (e.g. "Looking for British Voice Over Actor", "Newcastle Voiceover", "Hiring Voice Actor"),
 * extracts real email addresses, phone numbers, contact names, and deep-crawls target domain pages.
 */
class SearchScraper {
    /**
     * Run multi-engine intent search scraping and deep-crawl resulting target websites.
     */
    public static function scrape(string $keyword, int $depth = 2, string $channel = 'all'): array {
        $found = [];
        $rawQuery = trim($keyword);
        
        // Search URLs
        $urls = [];
        $encodedQuery = urlencode($rawQuery);

        for ($page = 1; $page <= $depth; $page++) {
            $offset = ($page - 1) * 10 + 1;
            
            if ($channel === 'all' || $channel === 'bing') {
                $urls[] = ['url' => "https://www.bing.com/search?q={$encodedQuery}&first={$offset}", 'source' => 'Bing Engine'];
            }
            if ($channel === 'all' || $channel === 'duckduckgo') {
                $urls[] = ['url' => "https://html.duckduckgo.com/html/?q={$encodedQuery}", 'source' => 'DuckDuckGo Engine'];
            }
            if ($channel === 'all' || $channel === 'yahoo') {
                $urls[] = ['url' => "https://search.yahoo.com/search?p={$encodedQuery}&b={$offset}", 'source' => 'Yahoo Engine'];
            }
            if ($channel === 'all' || $channel === 'ask') {
                $urls[] = ['url' => "https://www.ask.com/web?q={$encodedQuery}&page={$page}", 'source' => 'Ask.com Engine'];
            }
        }

        $crawledHosts = [];

        foreach ($urls as $item) {
            $html = self::fetch($item['url']);
            if (empty($html)) continue;

            // 1. Extract raw emails directly from search engine snippets
            $snippetEmails = self::extractEmails($html);
            foreach ($snippetEmails as $email) {
                if (!isset($found[$email])) {
                    $found[$email] = [
                        'email' => $email,
                        'name' => self::deriveNameFromEmail($email),
                        'source' => $item['source'] . ' (Search Snippet)',
                        'domain' => explode('@', $email)[1] ?? 'unknown',
                        'phone' => self::extractPhone($html)
                    ];
                }
            }

            // 2. Extract target external website links to deep crawl
            preg_match_all('/href=["\'](https?:\/\/[^"\']+)["\']/i', $html, $matches);
            $links = array_unique($matches[1] ?? []);

            $domainCount = 0;
            foreach ($links as $link) {
                if ($domainCount >= 12) break; // Limit top 12 domains per batch for fast response

                // Skip major search engines and social portals
                if (preg_match('/duckduckgo\.com|bing\.com|yahoo\.com|ask\.com|google\.|facebook\.com|twitter\.com|youtube\.com|linkedin\.com|wikipedia\.org|pinterest\.com|instagram\.com|microsoft\.com/i', $link)) {
                    continue;
                }

                $host = parse_url($link, PHP_URL_HOST);
                if (empty($host) || in_array($host, $crawledHosts, true)) continue;
                $crawledHosts[] = $host;
                $domainCount++;

                // Fetch Home Page
                $homeHtml = self::fetch($link);
                if (empty($homeHtml)) continue;

                $pageTitle = self::extractTitle($homeHtml);
                $phone = self::extractPhone($homeHtml);
                $homeEmails = self::extractEmails($homeHtml);

                foreach ($homeEmails as $email) {
                    if (!isset($found[$email])) {
                        $found[$email] = [
                            'email' => $email,
                            'name' => $pageTitle ?: self::deriveNameFromEmail($email),
                            'source' => $item['source'] . ' → ' . $host,
                            'domain' => $host,
                            'phone' => $phone
                        ];
                    }
                }

                // Level 2: Crawl Contact / About / Booking pages on the site
                preg_match_all('/href=["\'](\/[^"\']+|https?:\/\/' . preg_quote($host, '/') . '[^"\']+)["\']/i', $homeHtml, $innerMatches);
                $innerLinks = array_unique($innerMatches[1] ?? []);

                $contactCrawled = 0;
                foreach ($innerLinks as $inner) {
                    if ($contactCrawled >= 3) break; // Check up to 3 contact pages

                    if (!preg_match('/contact|about|hire|booking|team|services|voice|cast|portfolio/i', $inner)) {
                        continue;
                    }

                    $targetUrl = $inner;
                    if (str_starts_with($inner, '/')) {
                        $targetUrl = 'https://' . $host . $inner;
                    }

                    $innerHtml = self::fetch($targetUrl);
                    if (!empty($innerHtml)) {
                        $innerEmails = self::extractEmails($innerHtml);
                        $innerPhone = self::extractPhone($innerHtml);
                        foreach ($innerEmails as $email) {
                            if (!isset($found[$email])) {
                                $found[$email] = [
                                    'email' => $email,
                                    'name' => $pageTitle ?: self::deriveNameFromEmail($email),
                                    'source' => $item['source'] . ' → Deep Crawl (' . $host . ')',
                                    'domain' => $host,
                                    'phone' => $innerPhone ?: $phone
                                ];
                            }
                        }
                        $contactCrawled++;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Extracts email addresses matching standard pattern from HTML text.
     */
    public static function extractEmails(string $text): array {
        $pattern = '/[a-zA-Z0-9_\-\+\.]+@[a-zA-Z0-9\-]+\.[a-zA-Z0-9\-\.]+/i';
        preg_match_all($pattern, $text, $matches);
        
        $clean = [];
        if (!empty($matches[0])) {
            foreach ($matches[0] as $email) {
                $email = strtolower(trim($email, '.'));
                // Skip static assets or image extensions mistakenly matched
                if (preg_match('/\.(jpg|png|gif|jpeg|svg|css|js|webp|ico|woff|ttf|eot)$/i', $email)) {
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
     * Extract phone number pattern from page text
     */
    private static function extractPhone(string $text): string {
        if (preg_match('/(\+44\s?\(0\)\s?\d{2,5}\s?\d{3,6}\d{3,4}|\+44\s?\d{2,5}\s?\d{3,6}\d{3,4}|07\d{3}\s?\d{6}|01\d{2,4}\s?\d{5,7}|\+1\s?\d{3}\s?\d{3}\s?\d{4})/i', $text, $m)) {
            return trim($m[0]);
        }
        return '';
    }

    /**
     * Extract title tag from HTML page
     */
    private static function extractTitle(string $html): string {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1])));
            $title = preg_replace('/\s+/', ' ', $title);
            return mb_substr($title, 0, 60);
        }
        return '';
    }

    /**
     * Helper to derive readable contact name from email string
     */
    private static function deriveNameFromEmail(string $email): string {
        $parts = explode('@', $email);
        $user = $parts[0] ?? '';
        $user = preg_replace('/[0-9_\-\.]/', ' ', $user);
        $user = ucwords(trim($user));
        return $user !== '' ? $user : 'Lead Contact';
    }

    /**
     * Fetch raw HTML with real user-agent header
     */
    private static function fetch(string $url): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36');
        
        $html = curl_exec($ch);
        curl_close($ch);
        return $html !== false ? (string)$html : '';
    }
}
