<?php
declare(strict_types=1);

/**
 * Controller for managing Newsletter Templates and visual live rendering previews
 */
class TemplateController {
    public function index(): void {
        $db = Database::getConnection();
        
        $action = $_GET['action'] ?? '';
        $id = (int)($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf()) {
                $_SESSION['flash_error'] = 'CSRF validation failed.';
                header('Location: ' . getSetting('app_url') . '/templates');
                exit;
            }
            if ($action === 'delete' && $id > 0) {
                $db->prepare("DELETE FROM templates WHERE id = ?")->execute([$id]);
                $_SESSION['flash_success'] = 'Template deleted successfully.';
                header('Location: ' . getSetting('app_url') . '/templates');
                exit;
            }
        }
        
        // Fetch all templates
        $st = $db->query("SELECT * FROM templates ORDER BY created_at DESC");
        $templates = $st->fetchAll();

        $title = 'Newsletter Templates';
        $viewPath = dirname(__DIR__) . '/views/templates.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    public function create(): void {
        $db = Database::getConnection();
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf()) {
                $error = 'CSRF validation failed.';
            } else {
                $name = trim($_POST['name'] ?? '');
                $subject = trim($_POST['subject'] ?? '');
                $bodyHtml = $_POST['body_html'] ?? '';
                $bodyText = $_POST['body_text'] ?? '';

                if (empty($name) || empty($bodyHtml)) {
                    $error = 'Template name and HTML content are required.';
                } else {
                    try {
                        $st = $db->prepare("
                            INSERT INTO templates (name, subject, body_html, body_text, created_at) 
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        $st->execute([$name, $subject, $bodyHtml, $bodyText]);
                        
                        $_SESSION['flash_success'] = 'Newsletter template saved successfully!';
                        header('Location: ' . getSetting('app_url') . '/templates');
                        exit;
                    } catch (Throwable $e) {
                        $error = 'Failed to save template: ' . $e->getMessage();
                    }
                }
            }
        }

        $title = 'Create Template';
        $isEdit = false;
        $viewPath = dirname(__DIR__) . '/views/template_create.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    public function edit(): void {
        $db = Database::getConnection();
        $id = (int)($_GET['id'] ?? 0);
        $error = null;

        // Fetch existing template
        $st = $db->prepare("SELECT * FROM templates WHERE id = ?");
        $st->execute([$id]);
        $template = $st->fetch();

        if (!$template) {
            $_SESSION['flash_error'] = 'Template not found.';
            header('Location: ' . getSetting('app_url') . '/templates');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf()) {
                $error = 'CSRF validation failed.';
            } else {
                $name = trim($_POST['name'] ?? '');
                $subject = trim($_POST['subject'] ?? '');
                $bodyHtml = $_POST['body_html'] ?? '';
                $bodyText = $_POST['body_text'] ?? '';

                if (empty($name) || empty($bodyHtml)) {
                    $error = 'Template name and HTML content are required.';
                } else {
                    try {
                        $stUpdate = $db->prepare("
                            UPDATE templates 
                            SET name = ?, subject = ?, body_html = ?, body_text = ? 
                            WHERE id = ?
                        ");
                        $stUpdate->execute([$name, $subject, $bodyHtml, $bodyText, $id]);
                        
                        $_SESSION['flash_success'] = 'Newsletter template updated successfully!';
                        header('Location: ' . getSetting('app_url') . '/templates');
                        exit;
                    } catch (Throwable $e) {
                        $error = 'Failed to update template: ' . $e->getMessage();
                    }
                }
            }
        }

        $title = 'Edit Template';
        $isEdit = true;
        $viewPath = dirname(__DIR__) . '/views/template_create.php';
        include dirname(__DIR__) . '/views/layout.php';
    }
}
