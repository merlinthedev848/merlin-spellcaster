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
     * Known search engines, directories, competitors, and system domains to exclude from buyer lead results
     */
    private static array $systemDomains = [
        'google.com', 'google.co.uk', 'google.ad', 'google.ae', 'google.com.ar', 'google.at', 'google.com.au',
        'google.be', 'google.com.br', 'google.ca', 'google.ch', 'google.cl', 'google.co', 'google.cz',
        'google.de', 'google.dk', 'google.es', 'google.fi', 'google.fr', 'google.gr', 'google.com.hk',
        'google.hu', 'google.co.id', 'google.ie', 'google.co.il', 'google.co.in', 'google.it', 'google.co.jp',
        'google.co.kr', 'google.com.mx', 'google.com.my', 'google.nl', 'google.no', 'google.nz', 'google.pl',
        'google.pt', 'google.ru', 'google.se', 'google.com.sg', 'google.co.th', 'google.com.tr', 'google.com.tw',
        'google.com.ua', 'google.co.ve', 'google.co.za',
        'duckduckgo.com', 'bing.com', 'yahoo.com', 'yahoo.co.uk', 'ask.com', 'mojeek.com', 'qwant.com', 'brave.com',
        'youtube.com', 'youtu.be', 'wikipedia.org', 'w3.org', 'schema.org', 'microsoft.com', 'apple.com', 'android.com',
        'github.com', 'gitlab.com', 'bitbucket.org', 'stackoverflow.com', 'pinterest.com', 'facebook.com',
        'instagram.com', 'twitter.com', 'x.com', 'linkedin.com', 'reddit.com', 'tumblr.com', 'medium.com',
        'voices.com', 'voice123.com', 'spotlight.com', 'mandy.com', 'backstage.com', 
        'fiverr.com', 'upwork.com', 'starnow.com', 'castitnow.com', 'actorsequity.org',
        'audible.com', 'imdb.com', 'youtube-nocookie.com', 'wordpress.org', 'w.org', 'gravatar.com'
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

            // Extract ONLY organic search result links
            $links = self::extractOrganicLinks($html, $item['source']);

            $domainCount = 0;
            foreach ($links as $link) {
                if ($domainCount >= 12) break;

                $host = strtolower((string)parse_url($link, PHP_URL_HOST));
                if (empty($host) || in_array($host, $crawledHosts, true)) continue;

                // Skip self-domain
                if ($selfDomain !== '' && (str_contains($host, $selfDomain) || str_contains($selfDomain, $host))) {
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
     * Extracts organic results URLs while ignoring search engine and scraper pages
     */
    private static function extractOrganicLinks(string $html, string $source): array {
        $organic = [];
        
        if (str_contains($source, 'Google')) {
            preg_match_all('/href=["\']\/url\?q=(https?:\/\/[^&"\']+)/i', $html, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $url) {
                    $organic[] = urldecode($url);
                }
            }
        } elseif (str_contains($source, 'Yahoo')) {
            preg_match_all('/RU=(https?%3a%2f%2f[^&"\']+)/i', $html, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $url) {
                    $organic[] = urldecode($url);
                }
            }
        }

        // Fallback generic extraction
        preg_match_all('/href=["\'](https?:\/\/[^"\']+)["\']/i', $html, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                $organic[] = $url;
            }
        }

        $filtered = [];
        foreach ($organic as $url) {
            $host = strtolower((string)parse_url($url, PHP_URL_HOST));
            if (empty($host)) continue;
            
            $cleanHost = preg_replace('/^www\./', '', $host);
            
            $isSystem = false;
            foreach (self::$systemDomains as $sys) {
                if ($cleanHost === $sys || str_ends_with($cleanHost, '.' . $sys)) {
                    $isSystem = true;
                    break;
                }
            }
            
            if (!$isSystem) {
                $filtered[] = $url;
            }
        }

        return array_unique($filtered);
    }

    /**
     * Generate search queries focused strictly on what the user actually searched for
     */
    private static function generateBuyerQueries(string $userOffering, string $buyerType): array {
        $clean = trim($userOffering);
        if (empty($clean)) return [];

        // Strip quotes if user entered them to avoid double nesting
        $clean = trim($clean, '"\'');

        $queries = [];
        
        // 1. Search the exact query the user typed
        $queries[] = '"' . $clean . '"';
        
        // 2. Search exact query + contact terms to find email listings
        $queries[] = '"' . $clean . '" "email"';
        $queries[] = '"' . $clean . '" "contact"';
        $queries[] = '"' . $clean . '" "hiring"';
        $queries[] = '"' . $clean . '" "looking for"';

        // 3. Fallback: if it's a multi-word phrase, also search without quotes to be broader
        if (str_contains($clean, ' ')) {
            $queries[] = $clean;
            $queries[] = $clean . ' "email"';
            $queries[] = $clean . ' "contact"';
        }

        return array_unique($queries);
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
                
                // Exclude invalid file extensions at the end of the email TLD
                $parts = explode('.', $email);
                $lastPart = end($parts);
                $exts = ['jpg', 'png', 'gif', 'jpeg', 'svg', 'css', 'js', 'webp', 'ico', 'woff', 'ttf', 'eot', 'avif', 'mp3', 'mp4', 'wav', 'pdf', 'zip', 'gz', 'tar', 'xml', 'json'];
                if (in_array(strtolower($lastPart), $exts, true)) continue;
                
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

    /**
     * Local Agency & Studio Finder Search
     */
    public static function scrapeLocal(string $category, string $location, int $depth = 2): array {
        $found = [];
        $queryStr = trim($category) . ' ' . trim($location);
        if (empty($queryStr)) return [];

        $selfEmails = array_filter([
            strtolower(trim($_SESSION['user_email'] ?? '')),
            strtolower(trim(getSetting('smtp_from', ''))),
            strtolower(trim(getSetting('admin_email', '')))
        ]);
        $selfDomain = strtolower(parse_url(getSetting('app_url', ''), PHP_URL_HOST) ?: '');

        // Formulate target local intent queries
        $queries = [
            '"' . trim($category) . '" "' . trim($location) . '" "email"',
            '"' . trim($category) . '" "' . trim($location) . '" "contact"',
            trim($category) . ' ' . trim($location) . ' agency OR studio'
        ];

        $urls = [];
        foreach ($queries as $q) {
            $encoded = urlencode($q);
            for ($page = 1; $page <= $depth; $page++) {
                $offset = ($page - 1) * 10 + 1;
                $urls[] = ['url' => "https://www.google.com/search?q={$encoded}&start={$offset}", 'source' => 'Google Maps Finder'];
                $urls[] = ['url' => "https://html.duckduckgo.com/html/?q={$encoded}", 'source' => 'DuckDuckGo Local'];
            }
        }

        $crawledHosts = [];
        foreach ($urls as $item) {
            $html = self::fetch($item['url']);
            if (empty($html)) continue;

            $links = self::extractOrganicLinks($html, $item['source']);
            $domainCount = 0;

            foreach ($links as $link) {
                if ($domainCount >= 10) break;

                $host = strtolower((string)parse_url($link, PHP_URL_HOST));
                if (empty($host) || in_array($host, $crawledHosts, true)) continue;

                if ($selfDomain !== '' && (str_contains($host, $selfDomain) || str_contains($selfDomain, $host))) {
                    continue;
                }

                $crawledHosts[] = $host;
                $domainCount++;

                $homeHtml = self::fetch($link);
                if (empty($homeHtml)) continue;

                $companyTitle = self::extractTitle($homeHtml);
                $phone = self::extractPhone($homeHtml);
                $homeEmails = self::extractEmails($homeHtml);

                foreach ($homeEmails as $email) {
                    $emailLower = strtolower($email);
                    if (in_array($emailLower, $selfEmails, true)) continue;

                    if (!isset($found[$emailLower])) {
                        $roleInfo = self::determineBuyerRole($companyTitle, $homeHtml);
                        $found[$emailLower] = [
                            'email' => $emailLower,
                            'name' => $roleInfo['name'] ?: self::deriveNameFromEmail($emailLower),
                            'company' => $companyTitle ?: ucwords(str_replace(['www.', '.com', '.co.uk', '.net', '.org'], '', $host)),
                            'role' => 'Local ' . ucwords(trim($category)),
                            'phone' => $phone,
                            'domain' => $host,
                            'source' => $item['source'] . ' (Local Search)',
                            'buyer_score' => 90
                        ];
                    }
                }
            }
        }

        return array_values($found);
    }

    /**
     * B2B Corporate Lead Profile Enrichment
     */
    public static function enrichDomain(string $domain): array {
        $domain = strtolower(trim($domain));
        if (empty($domain)) return [];

        $url = 'https://' . $domain;
        $html = self::fetch($url);
        if (empty($html)) {
            $url = 'http://' . $domain;
            $html = self::fetch($url);
        }

        if (empty($html)) {
            return [
                'company_name' => ucwords(str_replace(['www.', '.com', '.co.uk', '.net', '.org'], '', $domain)),
                'description' => 'Unable to crawl company website.',
                'industry' => 'Unknown',
                'location' => 'Unknown'
            ];
        }

        $title = self::extractTitle($html);
        
        $desc = '';
        if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\'](.*?)["\']/is', $html, $m)) {
            $desc = trim(html_entity_decode($m[1]));
        } elseif (preg_match('/<meta[^>]*content=["\'](.*?)["\'][^>]*name=["\']description["\']/is', $html, $m)) {
            $desc = trim(html_entity_decode($m[1]));
        } elseif (preg_match('/<meta[^>]*property=["\']og:description["\'][^>]*content=["\'](.*?)["\']/is', $html, $m)) {
            $desc = trim(html_entity_decode($m[1]));
        }

        if (empty($desc)) {
            if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $html, $m)) {
                $desc = trim(strip_tags($m[1]));
            }
        }
        $desc = mb_substr(preg_replace('/\s+/', ' ', $desc), 0, 180);

        // Classify Industry
        $industry = 'Business Services';
        $fullText = strtolower($title . ' ' . $desc . ' ' . $html);
        if (preg_match('/video|film|production|audio|recording|voice|actor|studio/i', $fullText)) {
            $industry = 'Media & Production';
        } elseif (preg_match('/marketing|agency|advertising|creative|branding|pr/i', $fullText)) {
            $industry = 'Marketing & Advertising';
        } elseif (preg_match('/software|app|web|technology|cloud|it\s|saas/i', $fullText)) {
            $industry = 'Technology';
        } elseif (preg_match('/learn|course|education|school|e-learning|training/i', $fullText)) {
            $industry = 'E-Learning & Education';
        } elseif (preg_match('/consulting|legal|financial|advisory/i', $fullText)) {
            $industry = 'Professional Services';
        }

        // Classify Location
        $location = 'Global / United States';
        if (str_ends_with($domain, '.uk') || str_ends_with($domain, '.co.uk')) {
            $location = 'United Kingdom';
        } elseif (str_ends_with($domain, '.ca')) {
            $location = 'Canada';
        } elseif (str_ends_with($domain, '.au') || str_ends_with($domain, '.com.au')) {
            $location = 'Australia';
        } elseif (str_ends_with($domain, '.de')) {
            $location = 'Germany';
        } elseif (str_ends_with($domain, '.eu')) {
            $location = 'Europe';
        }

        return [
            'company_name' => $title ?: ucwords(str_replace(['www.', '.com', '.co.uk', '.net', '.org'], '', $domain)),
            'description' => $desc ?: 'Corporate website crawled successfully.',
            'industry' => $industry,
            'location' => $location
        ];
    }
}
