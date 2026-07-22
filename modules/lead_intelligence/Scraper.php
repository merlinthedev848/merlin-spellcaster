<?php
declare(strict_types=1);

/**
 * Commercial Buyer Intent Lead Finder Engine
 * 
 * Specifically designed to find BUYERS & CLIENTS WHO HIRE YOUR SERVICES
 * (e.g. Video Production Agencies, E-Learning Studios, Ad Agencies, Casting Directors, Audio Producers)
 * rather than competitors or directories. Filter out self-domain & competitor listings.
 */
class BuyerLeadScraper {
    /**
     * Known actor/freelancer directory sites & competitors to exclude from buyer lead results
     */
    private static array $excludedDomains = [
        'voices.com', 'voice123.com', 'spotlight.com', 'mandy.com', 'backstage.com', 
        'fiverr.com', 'upwork.com', 'starnow.com', 'castitnow.com', 'actorsequity.org',
        'audible.com', 'imdb.com', 'wikipedia.org', 'youtube.com', 'facebook.com', 
        'twitter.com', 'x.com', 'instagram.com', 'pinterest.com', 'tiktok.com'
    ];

    /**
     * Main Buyer Lead Scraper Entry Point
     */
    public static function scrape(string $userOffering, int $depth = 2, string $channel = 'all', string $buyerType = 'all'): array {
        $found = [];
        $rawQuery = trim($userOffering);
        if (empty($rawQuery)) return [];

        // Identify self-emails & self-domains to exclude
        $selfEmails = array_filter([
            strtolower(trim($_SESSION['user_email'] ?? '')),
            strtolower(trim(getSetting('smtp_from', ''))),
            strtolower(trim(getSetting('admin_email', '')))
        ]);
        
        $selfDomain = strtolower(parse_url(getSetting('app_url', ''), PHP_URL_HOST) ?: '');

        // 1. Generate Target Buyer Search Queries
        $buyerQueries = self::generateBuyerQueries($rawQuery, $buyerType);

        $urls = [];
        foreach ($buyerQueries as $q) {
            $encodedQuery = urlencode($q);
            for ($page = 1; $page <= $depth; $page++) {
                $offset = ($page - 1) * 10 + 1;
                
                if ($channel === 'all' || $channel === 'google') {
                    $urls[] = ['url' => "https://www.google.com/search?q={$encodedQuery}&start={$offset}", 'source' => 'Google Engine', 'intent' => $q];
                }
                if ($channel === 'all' || $channel === 'bing') {
                    $urls[] = ['url' => "https://www.bing.com/search?q={$encodedQuery}&first={$offset}", 'source' => 'Bing Engine', 'intent' => $q];
                }
                if ($channel === 'all' || $channel === 'duckduckgo') {
                    $urls[] = ['url' => "https://html.duckduckgo.com/html/?q={$encodedQuery}", 'source' => 'DuckDuckGo Engine', 'intent' => $q];
                }
                if ($channel === 'all' || $channel === 'yahoo') {
                    $urls[] = ['url' => "https://search.yahoo.com/search?p={$encodedQuery}&b={$offset}", 'source' => 'Yahoo Engine', 'intent' => $q];
                }
            }
        }

        $crawledHosts = [];

        foreach ($urls as $item) {
            $html = self::fetch($item['url']);
            if (empty($html)) continue;

            // Extract external domain links for site crawling
            preg_match_all('/href=["\'](https?:\/\/[^"\']+)["\']/i', $html, $matches);
            $links = array_unique($matches[1] ?? []);

            $domainCount = 0;
            foreach ($links as $link) {
                if ($domainCount >= 12) break;

                $host = strtolower((string)parse_url($link, PHP_URL_HOST));
                if (empty($host) || in_array($host, $crawledHosts, true)) continue;

                // Skip self-domain
                if ($selfDomain !== '' && (str_contains($host, $selfDomain) || str_contains($selfDomain, $host))) {
                    continue;
                }

                // Skip directories & social platforms
                if (self::isExcludedDomain($host)) {
                    continue;
                }

                $crawledHosts[] = $host;
                $domainCount++;

                // Fetch Buyer Website Home Page
                $homeHtml = self::fetch($link);
                if (empty($homeHtml)) continue;

                $companyTitle = self::extractTitle($homeHtml);
                $phone = self::extractPhone($homeHtml);
                $homeEmails = self::extractEmails($homeHtml);

                foreach ($homeEmails as $email) {
                    $emailLower = strtolower($email);

                    // Skip self-email
                    if (in_array($emailLower, $selfEmails, true)) continue;

                    if (!isset($found[$emailLower])) {
                        $roleInfo = self::determineBuyerRole($companyTitle, $homeHtml);
                        $found[$emailLower] = [
                            'email' => $emailLower,
                            'name' => $roleInfo['name'] ?: self::deriveNameFromEmail($emailLower),
                            'company' => $companyTitle ?: ucwords(str_replace(['www.', '.com', '.co.uk', '.net', '.org'], '', $host)),
                            'role' => $roleInfo['role'],
                            'phone' => $phone,
                            'domain' => $host,
                            'source' => $item['source'],
                            'buyer_score' => $roleInfo['score']
                        ];
                    }
                }

                // Level 2: Crawl Contact / Team / About / Hiring / Projects pages on the site
                preg_match_all('/href=["\'](\/[^"\']+|https?:\/\/' . preg_quote($host, '/') . '[^"\']+)["\']/i', $homeHtml, $innerMatches);
                $innerLinks = array_unique($innerMatches[1] ?? []);

                $contactCrawled = 0;
                foreach ($innerLinks as $inner) {
                    if ($contactCrawled >= 3) break;

                    if (!preg_match('/contact|about|team|people|producers|hiring|careers|projects|clients|work-with-us/i', $inner)) {
                        continue;
                    }

                    $targetUrl = str_starts_with($inner, '/') ? 'https://' . $host . $inner : $inner;
                    $innerHtml = self::fetch($targetUrl);
                    if (!empty($innerHtml)) {
                        $innerEmails = self::extractEmails($innerHtml);
                        $innerPhone = self::extractPhone($innerHtml);
                        foreach ($innerEmails as $email) {
                            $emailLower = strtolower($email);
                            if (in_array($emailLower, $selfEmails, true)) continue;

                            if (!isset($found[$emailLower])) {
                                $roleInfo = self::determineBuyerRole($companyTitle, $innerHtml);
                                $found[$emailLower] = [
                                    'email' => $emailLower,
                                    'name' => $roleInfo['name'] ?: self::deriveNameFromEmail($emailLower),
                                    'company' => $companyTitle ?: ucwords(str_replace(['www.', '.com', '.co.uk', '.net', '.org'], '', $host)),
                                    'role' => $roleInfo['role'],
                                    'phone' => $innerPhone ?: $phone,
                                    'domain' => $host,
                                    'source' => $item['source'] . ' (Deep Crawl)',
                                    'buyer_score' => $roleInfo['score']
                                ];
                            }
                        }
                        $contactCrawled++;
                    }
                }
            }
        }

        // Sort by buyer_score descending
        usort($found, fn($a, $b) => $b['buyer_score'] <=> $a['buyer_score']);
        return $found;
    }

    /**
     * Generate search queries focused strictly on BUYERS (Agencies, Producers, Studios, Hiring Notices)
     */
    private static function generateBuyerQueries(string $userOffering, string $buyerType): array {
        $clean = trim($userOffering);
        $queries = [];

        // If user enters a service like "Voiceover", "British Voice Over Actor", "Newcastle Voiceover", "Web Designer"
        if (preg_match('/voice\s*over|voiceover|actor|narrator|audiobook|geordie|newcastle/i', $clean)) {
            $location = '';
            if (preg_match('/newcastle|northeast|geordie|london|uk|manchester|england/i', $clean, $lm)) {
                $location = ' ' . $lm[0];
            }

            // Buyer Types: Video Production, E-Learning, Advertising Agencies, Game Studios, Audiobook Publishers
            $queries[] = '"video production agency"' . $location . ' "contact"';
            $queries[] = '"explainer video company"' . $location . ' "producer"';
            $queries[] = '"e-learning development company"' . $location . ' "contact"';
            $queries[] = '"creative ad agency"' . $location . ' "contact"';
            $queries[] = '"hiring voice actor"' . $location;
            $queries[] = '"voiceover casting"' . $location;
            $queries[] = '"audiobook publisher"' . $location . ' "contact"';
            $queries[] = '"animation studio"' . $location . ' "producer"';
        } else {
            // General service offering -> Buyer Agencies & Companies
            $queries[] = '"' . $clean . '" "video production" OR "agency" "contact"';
            $queries[] = '"hiring" "' . $clean . '"';
            $queries[] = '"looking for" "' . $clean . '" "contact"';
            $queries[] = '"agency" "' . $clean . '" "email"';
        }

        return array_unique($queries);
    }

    /**
     * Exclude directory websites & competitor marketplaces
     */
    private static function isExcludedDomain(string $host): bool {
        foreach (self::$excludedDomains as $ex) {
            if (str_contains($host, $ex)) return true;
        }
        return false;
    }

    /**
     * Analyze HTML to classify buyer role & intent score (1-100)
     */
    private static function determineBuyerRole(string $title, string $html): array {
        $score = 70;
        $role = 'Agency / Business Lead';
        $name = '';

        if (preg_match('/producer|executive producer|head of production/i', $html, $m)) {
            $role = 'Production Lead / Producer';
            $score = 95;
        } elseif (preg_match('/casting director|casting manager|talent manager/i', $html, $m)) {
            $role = 'Casting Director / Manager';
            $score = 98;
        } elseif (preg_match('/creative director|art director/i', $html, $m)) {
            $role = 'Creative Director';
            $score = 90;
        } elseif (preg_match('/marketing manager|head of marketing|managing director/i', $html, $m)) {
            $role = 'Marketing / Managing Director';
            $score = 85;
        } elseif (preg_match('/video production|media production/i', $html)) {
            $role = 'Video Production Agency';
            $score = 88;
        }

        return ['role' => $role, 'score' => $score, 'name' => $name];
    }

    public static function extractEmails(string $text): array {
        $pattern = '/[a-zA-Z0-9_\-\+\.]+@[a-zA-Z0-9\-]+\.[a-zA-Z0-9\-\.]+/i';
        preg_match_all($pattern, $text, $matches);
        $clean = [];
        if (!empty($matches[0])) {
            foreach ($matches[0] as $email) {
                $email = strtolower(trim($email, '.'));
                if (preg_match('/\.(jpg|png|gif|jpeg|svg|css|js|webp|ico|woff|ttf|eot)$/i', $email)) continue;
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $clean[] = $email;
                }
            }
        }
        return array_unique($clean);
    }

    private static function extractPhone(string $text): string {
        if (preg_match('/(\+44\s?\(0\)\s?\d{2,5}\s?\d{3,6}\d{3,4}|\+44\s?\d{2,5}\s?\d{3,6}\d{3,4}|07\d{3}\s?\d{6}|01\d{2,4}\s?\d{5,7}|\+1\s?\d{3}\s?\d{3}\s?\d{4})/i', $text, $m)) {
            return trim($m[0]);
        }
        return '';
    }

    private static function extractTitle(string $html): string {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1])));
            $title = preg_replace('/\s+/', ' ', $title);
            return mb_substr($title, 0, 60);
        }
        return '';
    }

    private static function deriveNameFromEmail(string $email): string {
        $parts = explode('@', $email);
        $user = $parts[0] ?? '';
        if (in_array(strtolower($user), ['info', 'contact', 'hello', 'admin', 'office', 'enquiries', 'sales', 'jobs', 'team'], true)) {
            return ucwords($user) . ' Desk';
        }
        $user = preg_replace('/[0-9_\-\.]/', ' ', $user);
        $user = ucwords(trim($user));
        return $user !== '' ? $user : 'Buyer Contact';
    }

    private static function fetch(string $url): string {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36');
        $html = curl_exec($ch);
        if (PHP_VERSION_ID < 80000) { @curl_close($ch); }
        return $html !== false ? (string)$html : '';
    }
}
