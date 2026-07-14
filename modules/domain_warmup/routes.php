<?php
declare(strict_types=1);

// Route: /warmup (Admin Settings Page)
if ($routePath === '/warmup') {
    $db = Database::getConnection();
    $action = $_GET['action'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
        $active = isset($_POST['warmup_active']) ? '1' : '0';
        $seedList = (int)($_POST['warmup_seed_list'] ?? 0);
        $startDate = trim($_POST['warmup_start_date'] ?? date('Y-m-d'));

        setSetting('warmup_active', $active);
        setSetting('warmup_seed_list', (string)$seedList);
        setSetting('warmup_start_date', $startDate);

        $_SESSION['flash_success'] = 'Warm-Up Engine settings updated.';
        header('Location: ' . getSetting('app_url') . '/warmup');
        exit;
    }

    // Fetch lists
    $lists = $db->query("SELECT id, name FROM lists ORDER BY name ASC")->fetchAll();

    // Fetch stats
    $warmupActive = getSetting('warmup_active', '0') === '1';
    $seedListId = (int)getSetting('warmup_seed_list', '0');
    $startDate = getSetting('warmup_start_date', date('Y-m-d'));

    $day = 0;
    $quota = 0;
    if ($warmupActive) {
        $start = new DateTime($startDate);
        $today = new DateTime();
        $diff = $today->diff($start);
        $day = $diff->days + 1;
        $quota = (int)round(5 * pow(1.3, $day - 1));
    }

    $title = 'Domain Warm-Up Engine';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

// Route: /warmup/run (Trigger daily warmup email sends)
if ($routePath === '/warmup/run') {
    header('Content-Type: application/json');
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

    // Exponential daily growth sequence
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
        usleep(500000); // 0.5s delay to be polite
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
