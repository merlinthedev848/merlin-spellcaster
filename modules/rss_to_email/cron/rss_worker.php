<?php
declare(strict_types=1);

/**
 * modules/rss_to_email/cron/rss_worker.php
 * Run this via CLI: php rss_worker.php
 */

if (php_sapi_name() !== 'cli' && !isset($_GET['secret'])) {
    die("CLI only or requires secret");
}

require_once dirname(__DIR__, 3) . '/config.php';

$feeds = $db->query("SELECT * FROM mod_rss_feeds")->fetchAll();

foreach ($feeds as $feed) {
    echo "Checking feed: {$feed['feed_url']}\n";
    
    // Very simple RSS parse logic using SimpleXML
    $content = @file_get_contents($feed['feed_url']);
    if (!$content) {
        echo "Could not fetch feed.\n";
        continue;
    }
    
    try {
        $xml = new SimpleXMLElement($content);
        $item = $xml->channel->item[0] ?? null; // Get latest post
        
        if ($item) {
            $title = (string)$item->title;
            $link = (string)$item->link;
            $desc = strip_tags((string)$item->description);
            $pubDate = strtotime((string)$item->pubDate);
            
            // Check if we already created a campaign for this post
            $subject = "New Post: " . $title;
            $exists = $db->prepare("SELECT id FROM campaigns WHERE subject = ?")->execute([$subject]);
            $exists = $db->query("SELECT id FROM campaigns WHERE subject = " . $db->quote($subject))->fetchColumn();
            
            if (!$exists) {
                // Create Campaign!
                $html = "<h2>$title</h2><p>$desc</p><br><a href='$link' style='padding:10px 20px; background:#4f46e5; color:white; text-decoration:none; border-radius:5px;'>Read Full Article</a>";
                
                $db->prepare("INSERT INTO campaigns (subject, body_html, body_text, status, scheduled_at) VALUES (?, ?, ?, 'scheduled', NOW())")
                   ->execute([$subject, $html, $desc]);
                   
                $campId = $db->lastInsertId();
                
                // Link to list
                $db->prepare("INSERT INTO campaign_lists (campaign_id, list_id) VALUES (?, ?)")->execute([$campId, $feed['list_id']]);
                
                echo "Generated campaign for '$title'!\n";
            } else {
                echo "No new posts.\n";
            }
        }
    } catch (Exception $e) {
        echo "Error parsing XML.\n";
    }
    
    $db->prepare("UPDATE mod_rss_feeds SET last_checked_at = NOW() WHERE id = ?")->execute([$feed['id']]);
}

echo "RSS Worker complete.\n";
