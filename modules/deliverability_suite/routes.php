<?php
declare(strict_types=1);

require_once __DIR__ . '/Verifier.php';

// Register hook for validating new contacts before adding them
Hook::register('before_add_contact', function(&$data) {
    $res = EmailVerifier::verify($data['email']);
    if (!$res['valid']) {
        $data['valid'] = false;
        $data['error'] = $res['reason'];
    }
});

// Route: /deliverability
if ($routePath === '/deliverability') {
    $db = Database::getConnection();
    
    // Action: Update domain warmup settings
    $action = $_GET['action'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'warmup_update') {
        if (!Auth::checkCsrf()) {
            $_SESSION['flash_error'] = 'CSRF validation failed.';
            header('Location: ' . getSetting('app_url') . '/deliverability?tab=warmup');
            exit;
        }
        $active = isset($_POST['warmup_active']) ? '1' : '0';
        $seedList = (int)($_POST['warmup_seed_list'] ?? 0);
        $startDate = trim($_POST['warmup_start_date'] ?? date('Y-m-d'));

        setSetting('warmup_active', $active);
        setSetting('warmup_seed_list', (string)$seedList);
        setSetting('warmup_start_date', $startDate);

        $_SESSION['flash_success'] = 'Warm-Up Engine settings updated.';
        header('Location: ' . getSetting('app_url') . '/deliverability?tab=warmup');
        exit;
    }

    // 1. Fetch campaigns for Content Scanner dropdown
    $campaigns = $db->query("SELECT id, name, subject, body_html FROM campaigns ORDER BY created_at DESC LIMIT 20")->fetchAll();

    // 2. Fetch lists for Domain Warmup
    $lists = $db->query("SELECT id, name FROM lists ORDER BY name ASC")->fetchAll();

    // 3. Fetch stats for Verifier Tab
    $totalVerifier = (int)$db->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();
    $activeVerifier = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE status='active'")->fetchColumn();
    $bouncedVerifier = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE status='bounced'")->fetchColumn();
    $unsubsVerifier = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE status='unsubscribed'")->fetchColumn();

    // 4. Fetch stats for Warm-up Tab
    $warmupActive = getSetting('warmup_active', '0') === '1';
    $seedListId = (int)getSetting('warmup_seed_list', '0');
    $startDate = getSetting('warmup_start_date', date('Y-m-d'));

    $warmupDay = 0;
    $warmupQuota = 0;
    if ($warmupActive) {
        $start = new DateTime($startDate);
        $today = new DateTime();
        $diff = $today->diff($start);
        $warmupDay = $diff->days + 1;
        $warmupQuota = (int)round(5 * pow(1.3, $warmupDay - 1));
    }

    $title = 'Email Deliverability Suite';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

// Redirect old routes for backward compatibility
if ($routePath === '/warmup') {
    header('Location: ' . getSetting('app_url') . '/deliverability?tab=warmup');
    exit;
}
if ($routePath === '/verifier') {
    header('Location: ' . getSetting('app_url') . '/deliverability?tab=verifier');
    exit;
}

// Route: /deliverability/scan (Content Scanner API)
if ($routePath === '/deliverability/scan') {
    header('Content-Type: application/json');
    $payload = json_decode(file_get_contents('php://input'), true);
    $text = strtolower(($payload['subject'] ?? '') . ' ' . ($payload['html_body'] ?? ''));
    
    if (empty(trim($text))) {
        echo json_encode(['success' => false, 'error' => 'No content to scan.']);
        exit;
    }

    try {
        sleep(1); // Brief processing delay
        $spamWords = [
            'free', 'guarantee', 'act now', 'urgent', 'winner', 'cash', 'money',
            'no cost', 'no obligation', 'risk-free', 'buy now', 'click here',
            'order now', 'limited time', 'exclusive deal', 'viagra', 'crypto',
            'investment', 'credit card'
        ];
        
        $foundWords = [];
        $score = 100;
        foreach ($spamWords as $word) {
            if (strpos($text, $word) !== false) {
                $foundWords[] = $word;
                $score -= 5;
            }
        }
        $score = max(0, $score);
        
        $verdict = 'Excellent';
        $color = '#34d399';
        if ($score < 90) { $verdict = 'Good'; $color = '#fbbf24'; }
        if ($score < 70) { $verdict = 'Risky'; $color = '#fb923c'; }
        if ($score < 50) { $verdict = 'High Spam Probability'; $color = '#ef4444'; }

        echo json_encode([
            'success' => true,
            'score' => $score,
            'verdict' => $verdict,
            'color' => $color,
            'triggers' => $foundWords
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Route: /verifier/scan (Verifier fetch IDs API)
if ($routePath === '/verifier/scan') {
    header('Content-Type: application/json');
    try {
        $db = Database::getConnection();
        $ids = $db->query("SELECT id FROM subscribers WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'ids' => array_map('intval', $ids)]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Route: /verifier/scan-batch (Verifier batch scanning API)
if ($routePath === '/verifier/scan-batch') {
    header('Content-Type: application/json');
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $ids = $data['ids'] ?? [];
    
    if (empty($ids)) {
        echo json_encode(['success' => true, 'processed' => 0, 'results' => []]);
        exit;
    }
    
    $results = [];
    $db = Database::getConnection();
    
    // Ensure the bounced tag exists in database
    $stTagCheck = $db->prepare("SELECT id FROM tags WHERE name = ?");
    $stTagCheck->execute(['bounced']);
    $tagId = $stTagCheck->fetchColumn();
    if (!$tagId) {
        $stInsertTag = $db->prepare("INSERT INTO tags (name, color) VALUES (?, ?)");
        $stInsertTag->execute(['bounced', '#e53e3e']);
        $tagId = (int)$db->lastInsertId();
    } else {
        $tagId = (int)$tagId;
    }
    
    $stSub = $db->prepare("SELECT email FROM subscribers WHERE id = ? AND status = 'active'");
    $stBounce = $db->prepare("UPDATE subscribers SET status = 'bounced' WHERE id = ?");
    $stFlushQueue = $db->prepare("DELETE FROM email_queue WHERE subscriber_id = ? AND status = 'pending'");
    
    foreach ($ids as $id) {
        $id = (int)$id;
        $stSub->execute([$id]);
        $email = $stSub->fetchColumn();
        
        if (!$email) {
            $results[] = ['id' => $id, 'valid' => true, 'skipped' => true];
            continue;
        }
        
        $res = EmailVerifier::verify((string)$email);
        if (!$res['valid']) {
            $db->beginTransaction();
            try {
                $stBounce->execute([$id]);
                $stFlushQueue->execute([$id]);
                $db->prepare("DELETE FROM subscriber_tags WHERE subscriber_id = ?")->execute([$id]);
                $db->prepare("INSERT INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)")->execute([$id, $tagId]);
                logActivity($id, 'bounce', "Email Verifier flagged deliverability: " . $res['reason']);
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
            }
        }
        
        $results[] = [
            'id' => $id,
            'email' => $email,
            'valid' => $res['valid'],
            'reason' => $res['reason']
        ];
    }
    
    echo json_encode(['success' => true, 'results' => $results]);
    exit;
}

// Route: /warmup/run (Domain Warmup schedule CRON endpoint - compatible with existing schedulers)
if ($routePath === '/warmup/run') {
    header('Content-Type: application/json');
    $provided = $_GET['secret'] ?? '';
    $secret = getSetting('cron_secret');
    if ($secret !== '' && !hash_equals($secret, $provided)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid cron secret.']);
        exit;
    }

    $db = Database::getConnection();
    $active = getSetting('warmup_active', '0') === '1';
    if (!$active) {
        echo json_encode(['success' => false, 'error' => 'Warmup engine is inactive.']);
        exit;
    }

    $startDate = getSetting('warmup_start_date', date('Y-m-d'));
    $seedListId = (int)getSetting('warmup_seed_list', '0');
    if ($seedListId <= 0) {
        echo json_encode(['success' => false, 'error' => 'No seed list selected.']);
        exit;
    }

    $start = new DateTime($startDate);
    $today = new DateTime();
    $diff = $today->diff($start);
    $day = $diff->days + 1;

    if ($day > 30) {
        setSetting('warmup_active', '0');
        echo json_encode(['success' => true, 'message' => 'Warmup schedule complete (exceeded 30 days). Engine deactivated.']);
        exit;
    }

    $quota = (int)round(5 * pow(1.3, $day - 1));
    $st = $db->prepare("
        SELECT s.email, s.first_name 
        FROM subscribers s 
        JOIN subscriber_lists sl ON s.id = sl.subscriber_id 
        WHERE sl.list_id = ? AND s.status = 'active'
        LIMIT ?
    ");
    $st->bindValue(1, $seedListId, PDO::PARAM_INT);
    $st->bindValue(2, $quota, PDO::PARAM_INT);
    $st->execute();
    $seedSubs = $st->fetchAll();

    if (empty($seedSubs)) {
        echo json_encode(['success' => false, 'error' => 'No active subscribers found in seed list.']);
        exit;
    }

    $mailer = new Mailer();
    $sent = 0;
    foreach ($seedSubs as $sub) {
        $subject = "Daily Update Checklist - " . date('M j, Y');
        $body = "<p>Hi " . e($sub['first_name'] ?: 'Friend') . ",</p><p>This is your daily domain warmup sequence checklist update from CK Medis Services. Have an excellent day!</p>";
        $altText = "Hi " . ($sub['first_name'] ?: 'Friend') . ",\nThis is your daily domain warmup sequence checklist update. Have an excellent day!";

        if ($mailer->send($sub['email'], $subject, $body, $altText)) {
            $sent++;
        }
        usleep(500000);
    }

    echo json_encode([
        'success' => true,
        'day' => $day,
        'quota' => $quota,
        'sent' => $sent,
        'message' => "Warmup sequence ran. Sent {$sent} of {$quota} target emails."
    ]);
    exit;
}
