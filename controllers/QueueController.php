<?php
declare(strict_types=1);

/**
 * QueueController — Manages outbound email queue inspection, cancellations, flushes, and forced sends.
 */
class QueueController {
    public function index(): void {
        $db = Database::getConnection();
        $action = $_GET['action'] ?? '';
        $id = (int)($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf()) {
                $_SESSION['flash_error'] = 'CSRF validation failed.';
                header('Location: ' . getSetting('app_url') . '/queue');
                exit;
            }

            if ($action === 'cancel_item' && $id > 0) {
                $db->prepare("DELETE FROM email_queue WHERE id = ?")->execute([$id]);
                $_SESSION['flash_success'] = 'Queue item canceled and removed.';
                header('Location: ' . getSetting('app_url') . '/queue');
                exit;
            }

            if ($action === 'flush_pending') {
                $count = (int)$db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'")->fetchColumn();
                $db->exec("DELETE FROM email_queue WHERE status = 'pending'");
                $_SESSION['flash_success'] = "Flushed and removed all {$count} pending queue items.";
                header('Location: ' . getSetting('app_url') . '/queue');
                exit;
            }

            if ($action === 'retry_failed') {
                $count = (int)$db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'failed'")->fetchColumn();
                $db->exec("UPDATE email_queue SET status = 'pending', attempts = 0, send_at = NOW() WHERE status = 'failed'");
                $_SESSION['flash_success'] = "Reset {$count} failed queue items back to pending status.";
                header('Location: ' . getSetting('app_url') . '/queue');
                exit;
            }

            if ($action === 'process_all') {
                require_once __DIR__ . '/../core/Queue.php';
                $result = Queue::process(500);
                $sent = (int)($result['sent'] ?? 0);
                $failed = (int)($result['failed'] ?? 0);
                if ($sent > 0 || $failed > 0) {
                    $_SESSION['flash_success'] = "Processed queue batch: {$sent} email(s) sent successfully, {$failed} failed.";
                } else {
                    $_SESSION['flash_info'] = "No pending emails were ready for immediate dispatch.";
                }
                header('Location: ' . getSetting('app_url') . '/queue');
                exit;
            }

            if ($action === 'send_now' && $id > 0) {
                require_once __DIR__ . '/../core/Queue.php';
                $stItem = $db->prepare("
                    SELECT eq.*, c.subject, c.body_html, c.body_text, c.include_unsubscribe, s.email, s.first_name, s.last_name
                    FROM email_queue eq
                    JOIN campaigns c ON c.id = eq.campaign_id
                    JOIN subscribers s ON s.id = eq.subscriber_id
                    WHERE eq.id = ?
                ");
                $stItem->execute([$id]);
                $item = $stItem->fetch();

                if ($item) {
                    require_once __DIR__ . '/../core/Mailer.php';
                    $mailer = new Mailer();
                    $altText = $item['body_text'] ?: strip_tags($item['body_html']);

                    if ($mailer->send($item['email'], $item['subject'], $item['body_html'], $altText)) {
                        $db->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?")->execute([$id]);
                        $db->prepare("UPDATE campaigns SET send_count = send_count + 1 WHERE id = ?")->execute([$item['campaign_id']]);
                        logActivity((int)$item['subscriber_id'], 'email_sent', "Force-sent campaign: {$item['subject']}");
                        $_SESSION['flash_success'] = "Email delivered instantly to {$item['email']}!";
                    } else {
                        $errMsg = $mailer->getLastError();
                        $db->prepare("UPDATE email_queue SET status = 'failed', error_message = ?, attempts = attempts + 1 WHERE id = ?")->execute([$errMsg, $id]);
                        $_SESSION['flash_error'] = "Delivery failed: " . $errMsg;
                    }
                }
                header('Location: ' . getSetting('app_url') . '/queue');
                exit;
            }

            if ($action === 'toggle_pause') {
                $current = getSetting('queue_paused', '0');
                $new = $current === '1' ? '0' : '1';
                setSetting('queue_paused', $new);
                $_SESSION['flash_success'] = $new === '1' ? 'Queue processing PAUSED globally.' : 'Queue processing RESUMED.';
                header('Location: ' . getSetting('app_url') . '/queue');
                exit;
            }
        }

        // Fetch Metrics
        $pendingCount = (int)$db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'")->fetchColumn();
        $sendingCount = (int)$db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'sending'")->fetchColumn();
        $sentCount = (int)$db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'sent'")->fetchColumn();
        $failedCount = (int)$db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'failed'")->fetchColumn();
        $queuePaused = getSetting('queue_paused', '0') === '1';

        // Filtering & Search
        $statusFilter = trim($_GET['status'] ?? '');
        $search = trim($_GET['q'] ?? '');
        $campaignId = (int)($_GET['campaign_id'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(10, min(250, (int)($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $conditions = [];
        $params = [];

        if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'sending', 'sent', 'failed'], true)) {
            $conditions[] = "eq.status = ?";
            $params[] = $statusFilter;
        }

        if ($campaignId > 0) {
            $conditions[] = "eq.campaign_id = ?";
            $params[] = $campaignId;
        }

        if ($search !== '') {
            $conditions[] = "(s.email LIKE ? OR c.name LIKE ? OR c.subject LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

        // Total Items Query
        $stCount = $db->prepare("
            SELECT COUNT(*) 
            FROM email_queue eq
            LEFT JOIN subscribers s ON s.id = eq.subscriber_id
            LEFT JOIN campaigns c ON c.id = eq.campaign_id
            {$whereClause}
        ");
        $stCount->execute($params);
        $totalItems = (int)$stCount->fetchColumn();
        $totalPages = max(1, (int)ceil($totalItems / $limit));
        if ($page > $totalPages) $page = $totalPages;
        $offset = max(0, ($page - 1) * $limit);

        // Items Query
        $stQuery = $db->prepare("
            SELECT eq.*, s.email as recipient_email, s.first_name, s.last_name, c.name as campaign_name, c.subject as campaign_subject
            FROM email_queue eq
            LEFT JOIN subscribers s ON s.id = eq.subscriber_id
            LEFT JOIN campaigns c ON c.id = eq.campaign_id
            {$whereClause}
            ORDER BY eq.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stQuery->execute($params);
        $queueItems = $stQuery->fetchAll();

        // Fetch Campaigns list for filter dropdown
        $campaigns = $db->query("SELECT id, name FROM campaigns ORDER BY created_at DESC")->fetchAll();

        $title = 'Outbound Email Queue';
        $viewPath = dirname(__DIR__) . '/views/queue.php';
        include dirname(__DIR__) . '/views/layout.php';
    }
}
