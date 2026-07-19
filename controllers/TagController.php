<?php
declare(strict_types=1);

/**
 * Controller for Managing CRM Tags
 */
class TagController {
    public function index(): void {
        $db = Database::getConnection();
        
        $action = $_GET['action'] ?? '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf()) {
                $_SESSION['flash_error'] = 'CSRF validation failed.';
                header('Location: ' . getSetting('app_url') . '/tags');
                exit;
            }
            if ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $db->prepare("DELETE FROM tags WHERE id = ?")->execute([$id]);
                    $_SESSION['flash_success'] = 'Tag deleted successfully.';
                }
                header('Location: ' . getSetting('app_url') . '/tags');
                exit;
            }
        }
        
        $st = $db->query("
            SELECT t.*, 
            (SELECT COUNT(*) FROM subscriber_tags st JOIN subscribers s ON s.id = st.subscriber_id WHERE st.tag_id = t.id AND s.status = 'active') as subscriber_count 
            FROM tags t 
            ORDER BY t.created_at DESC
        ");
        $tags = $st->fetchAll();
        
        $title = 'CRM Tags';
        $viewPath = dirname(__DIR__) . '/views/tags.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    public function create(): void {
        $db = Database::getConnection();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf()) {
                $_SESSION['flash_error'] = 'CSRF validation failed.';
                header('Location: ' . getSetting('app_url') . '/tags');
                exit;
            }
            $name = trim($_POST['name'] ?? '');
            
            if ($name === '') {
                $_SESSION['flash_error'] = 'Tag name is required.';
            } else {
                $colors = ['#ef4444','#f97316','#eab308','#22c55e','#14b8a6','#3b82f6','#8b5cf6','#ec4899','#06b6d4','#84cc16'];
                $color = $colors[array_rand($colors)];
                $st = $db->prepare("INSERT INTO tags (name, color, created_at) VALUES (?, ?, NOW())");
                try {
                    $st->execute([$name, $color]);
                    $_SESSION['flash_success'] = 'Tag created successfully!';
                } catch (Throwable $e) {
                    $_SESSION['flash_error'] = 'Failed to create tag: ' . $e->getMessage();
                }
            }
            header('Location: ' . getSetting('app_url') . '/tags');
            exit;
        }
    }

    public function edit(): void {
        $db = Database::getConnection();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf()) {
                $_SESSION['flash_error'] = 'CSRF validation failed.';
                header('Location: ' . getSetting('app_url') . '/tags');
                exit;
            }
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            
            if ($id > 0 && $name !== '') {
                $st = $db->prepare("UPDATE tags SET name = ? WHERE id = ?");
                try {
                    $st->execute([$name, $id]);
                    $_SESSION['flash_success'] = 'Tag updated successfully.';
                } catch (Throwable $e) {
                    $_SESSION['flash_error'] = 'Failed to update tag: ' . $e->getMessage();
                }
            }
            header('Location: ' . getSetting('app_url') . '/tags');
            exit;
        }
    }
}
