<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/core/Auth.php';

$route = $_GET['route'] ?? $_POST['route'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($route === 'template') {
    Auth::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)($_GET['id'] ?? 0);
    $st = $db->prepare("SELECT id, name, subject, body_html, body_text FROM templates WHERE id=?");
    $st->execute([$id]);
    echo json_encode($st->fetch() ?: ['error' => 'Template not found']);
    exit;
}

if ($route === 'subscribers' && $action === 'export') {
    $secret = $_GET['secret'] ?? '';
    if (!Auth::isLoggedIn() && !hash_equals(getSetting('cron_secret'), $secret)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Unauthorized";
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['email', 'first_name', 'last_name', 'phone', 'status', 'source', 'created_at']);
    $rows = $db->query("SELECT email, first_name, last_name, phone, status, source, created_at FROM subscribers ORDER BY created_at DESC");
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
http_response_code(404);
echo json_encode(['error' => 'Unknown API route']);
