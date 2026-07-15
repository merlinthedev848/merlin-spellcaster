<?php
declare(strict_types=1);

/**
 * Controller for application dashboard statistics and activity log
 */
class DashboardController {
    public function index(): void {
        $db = Database::getConnection();

        // 1. Fetch general statistics
        $subCount = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE status = 'active'")->fetchColumn();
        $campaignCount = (int)$db->query("SELECT COUNT(*) FROM campaigns")->fetchColumn();
        
        $sentStats = $db->query("SELECT SUM(send_count) as sent, SUM(open_count) as opens, SUM(click_count) as clicks FROM campaigns WHERE status IN ('sent', 'sending')")->fetch();
        $totalSent = (int)($sentStats['sent'] ?? 0);
        $totalOpens = (int)($sentStats['opens'] ?? 0);
        $totalClicks = (int)($sentStats['clicks'] ?? 0);

        $openRate = $totalSent > 0 ? round(($totalOpens / $totalSent) * 100, 1) : 0.0;
        $clickRate = $totalSent > 0 ? round(($totalClicks / $totalSent) * 100, 1) : 0.0;

        // 1b. Fetch pending emails in the sending queue
        $pendingEmailsCount = (int)$db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'")->fetchColumn();

        // 2. Fetch last 10 campaigns for charting
        $stCampaigns = $db->query("
            SELECT name, send_count, open_count, click_count 
            FROM campaigns 
            WHERE status IN ('sent', 'sending')
            ORDER BY sent_at DESC 
            LIMIT 10
        ");
        $chartCampaigns = array_reverse($stCampaigns->fetchAll());

        // 3. Fetch recent activity timeline
        $stLogs = $db->query("
            SELECT al.*, s.email, s.first_name, s.last_name 
            FROM activity_log al
            JOIN subscribers s ON s.id = al.subscriber_id
            ORDER BY al.created_at DESC
            LIMIT 8
        ");
        $activities = $stLogs->fetchAll();

        // Render dashboard
        $title = 'Dashboard';
        $viewPath = dirname(__DIR__) . '/views/dashboard.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    public function notFound(): void {
        $title = 'Page Not Found';
        $viewPath = dirname(__DIR__) . '/views/404.php';
        include dirname(__DIR__) . '/views/layout.php';
    }
}
