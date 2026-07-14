<?php
declare(strict_types=1);

if (str_starts_with($routePath, '/multi-smtp')) {
    $db = Database::getConnection();
    
    $action = $_GET['action'] ?? '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? 'New Server');
            $host = trim($_POST['host'] ?? '');
            $port = (int)($_POST['port'] ?? 587);
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $encryption = trim($_POST['encryption'] ?? 'tls');
            $fromEmail = trim($_POST['from_email'] ?? '');
            $fromName = trim($_POST['from_name'] ?? '');
            $dailyLimit = (int)($_POST['daily_limit'] ?? 0);
            
            $st = $db->prepare("INSERT INTO smtp_servers (name, host, port, username, password, encryption, from_email, from_name, daily_limit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $st->execute([$name, $host, $port, $username, $password, $encryption, $fromEmail, $fromName, $dailyLimit]);
            
            $_SESSION['flash_success'] = 'SMTP Server added successfully.';
            header('Location: ' . getSetting('app_url') . '/multi-smtp');
            exit;
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare("DELETE FROM smtp_servers WHERE id = ?")->execute([$id]);
            $_SESSION['flash_success'] = 'SMTP Server removed.';
            header('Location: ' . getSetting('app_url') . '/multi-smtp');
            exit;
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare("UPDATE smtp_servers SET status = 1 - status WHERE id = ?")->execute([$id]);
            $_SESSION['flash_success'] = 'SMTP Server status toggled.';
            header('Location: ' . getSetting('app_url') . '/multi-smtp');
            exit;
        } elseif ($action === 'reset_errors') {
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare("UPDATE smtp_servers SET error_count = 0 WHERE id = ?")->execute([$id]);
            $_SESSION['flash_success'] = 'Error count reset.';
            header('Location: ' . getSetting('app_url') . '/multi-smtp');
            exit;
        }
    }
    
    $servers = $db->query("SELECT * FROM smtp_servers ORDER BY created_at DESC")->fetchAll();
    
    $title = 'Multi-SMTP Intelligent Routing Engine';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}
