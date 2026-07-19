<?php
declare(strict_types=1);

// Bootstrap Link Rotator database table
try {
    $db = Database::getConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS mod_link_rotators (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) UNIQUE NOT NULL,
            destinations TEXT NOT NULL,
            clicks INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (PDOException $e) {
    error_log("Link Rotator database bootstrap error: " . $e->getMessage());
}

// Route: /rotators (Admin dashboard page)
if ($routePath === '/rotators') {
    $db = Database::getConnection();
    $action = $_GET['action'] ?? '';
    $id = (int)($_GET['id'] ?? 0);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::checkCsrf()) {
            $_SESSION['flash_error'] = 'CSRF validation failed.';
            header('Location: ' . getSetting('app_url') . '/rotators');
            exit;
        }
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $destRaw = trim($_POST['destinations'] ?? '');

            // Clean destinations input into array
            $dests = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $destRaw))), function($url) {
                return filter_var($url, FILTER_VALIDATE_URL);
            });

            if ($name !== '' && $slug !== '' && !empty($dests)) {
                try {
                    $st = $db->prepare("INSERT INTO mod_link_rotators (name, slug, destinations) VALUES (?, ?, ?)");
                    $st->execute([$name, $slug, json_encode(array_values($dests))]);
                    $_SESSION['flash_success'] = 'Link rotator successfully registered.';
                } catch (PDOException $e) {
                    $_SESSION['flash_error'] = 'Error creating rotator: Slug must be unique.';
                }
            } else {
                $_SESSION['flash_error'] = 'Invalid parameters. Please specify a unique slug and valid URLs.';
            }
            header('Location: ' . getSetting('app_url') . '/rotators');
            exit;
        }

        if ($action === 'delete' && $id > 0) {
            $db->prepare("DELETE FROM mod_link_rotators WHERE id = ?")->execute([$id]);
            $_SESSION['flash_success'] = 'Link rotator removed.';
            header('Location: ' . getSetting('app_url') . '/rotators');
            exit;
        }
    }

    // Fetch rotators
    $rotators = $db->query("SELECT * FROM mod_link_rotators ORDER BY created_at DESC")->fetchAll();

    $title = 'Smart Link Rotator';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

// Route: /go (Redirection endpoint)
if ($routePath === '/go') {
    $slug = $_GET['s'] ?? '';
    if ($slug === '') {
        header('Location: ' . getSetting('app_url') . '/');
        exit;
    }

    try {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id, destinations, clicks FROM mod_link_rotators WHERE slug = ?");
        $st->execute([$slug]);
        $rotator = $st->fetch();

        if ($rotator) {
            $dests = json_decode($rotator['destinations'], true);
            if (!empty($dests)) {
                // Update click count
                $db->prepare("UPDATE mod_link_rotators SET clicks = clicks + 1 WHERE id = ?")
                   ->execute([(int)$rotator['id']]);

                // Round robin selection
                $idx = ((int)$rotator['clicks']) % count($dests);
                $target = $dests[$idx];

                header("Location: " . $target);
                exit;
            }
        }
    } catch (Throwable $e) {
        error_log("Link rotator redirection error: " . $e->getMessage());
    }

    header('Location: ' . getSetting('app_url') . '/');
    exit;
}
