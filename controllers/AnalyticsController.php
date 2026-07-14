<?php
declare(strict_types=1);

/**
 * Controller for granular email marketing analytics and click tracking metrics
 */
class AnalyticsController {
    public function index(): void {
        $db = Database::getConnection();

        // 1. Fetch campaigns with unique opens and unique clicks comparison
        $stCampaigns = $db->query("
            SELECT c.id, c.name, c.send_count, c.sent_at,
                   (SELECT COUNT(DISTINCT subscriber_id) FROM campaign_opens WHERE campaign_id = c.id) as unique_opens,
                   (SELECT COUNT(DISTINCT subscriber_id) FROM campaign_clicks WHERE campaign_id = c.id) as unique_clicks
            FROM campaigns c
            WHERE c.status IN ('sent', 'sending')
            ORDER BY c.sent_at DESC
            LIMIT 10
        ");
        $campaigns = $stCampaigns->fetchAll();

        // 2. Aggregate link click counts (Total vs Unique clicks per link URL)
        $stLinks = $db->query("
            SELECT cc.url, c.name as campaign_name, 
                   COUNT(*) as total_clicks, 
                   COUNT(DISTINCT cc.subscriber_id) as unique_clicks
            FROM campaign_clicks cc
            JOIN campaigns c ON c.id = cc.campaign_id
            GROUP BY cc.url, cc.campaign_id, c.name
            ORDER BY total_clicks DESC
            LIMIT 20
        ");
        $linkStats = $stLinks->fetchAll();

        // 3. Granular log feed of individual subscriber clicks
        $stLogs = $db->query("
            SELECT cc.*, s.email, s.first_name, s.last_name, c.name as campaign_name
            FROM campaign_clicks cc
            JOIN subscribers s ON s.id = cc.subscriber_id
            JOIN campaigns c ON c.id = cc.campaign_id
            ORDER BY cc.clicked_at DESC
            LIMIT 50
        ");
        $clickLogs = $stLogs->fetchAll();

        // Render template
        $title = 'Campaign Analytics';
        $viewPath = dirname(__DIR__) . '/views/analytics.php';
        include dirname(__DIR__) . '/views/layout.php';
    }
}
