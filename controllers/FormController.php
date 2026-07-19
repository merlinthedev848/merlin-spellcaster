<?php
declare(strict_types=1);

/**
 * Controller for creating, editing subscription forms, and managing public subscriber opt-in flows
 */
class FormController {
    /**
     * Lists created forms
     */
    public function index(): void {
        $db = Database::getConnection();
        $action = $_GET['action'] ?? '';
        $id = (int)($_GET['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf()) {
                $_SESSION['flash_error'] = 'CSRF validation failed.';
                header('Location: ' . getSetting('app_url') . '/forms');
                exit;
            }
            if ($action === 'delete' && $id > 0) {
                $db->prepare("DELETE FROM forms WHERE id = ?")->execute([$id]);
                $_SESSION['flash_success'] = 'Subscription form deleted successfully.';
                header('Location: ' . getSetting('app_url') . '/forms');
                exit;
            }
        }

        // Fetch forms
        $st = $db->query("
            SELECT f.*, l.name as list_name 
            FROM forms f 
            LEFT JOIN lists l ON f.list_id = l.id 
            ORDER BY f.created_at DESC
        ");
        $forms = $st->fetchAll();

        $title = 'Subscription Forms';
        $viewPath = dirname(__DIR__) . '/views/forms.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    /**
     * Creation pane for forms
     */
    public function create(): void {
        $db = Database::getConnection();
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf()) {
                $error = 'CSRF validation failed.';
            } else {
                $name = trim($_POST['name'] ?? '');
                $listId = ($_POST['list_id'] ?? '') !== '' ? (int)$_POST['list_id'] : null;
            $headline = trim($_POST['headline'] ?? 'Subscribe to our newsletter');
            $description = trim($_POST['description'] ?? '');
            $buttonText = trim($_POST['button_text'] ?? 'Subscribe');
            $successMessage = trim($_POST['success_message'] ?? 'Thank you for subscribing!');
            $redirectUrl = trim($_POST['redirect_url'] ?? '') ?: null;
            $downloadUrl = trim($_POST['download_url'] ?? '') ?: null;
            
            $showName = isset($_POST['show_name']) ? 1 : 0;
            $requireName = isset($_POST['require_name']) ? 1 : 0;
            $doubleOptin = isset($_POST['double_optin']) ? 1 : 0;

            if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__) . '/uploads';
                if (!file_exists($uploadDir)) @mkdir($uploadDir, 0755, true);
                $filename = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['upload_file']['name']));
                $target = $uploadDir . '/' . $filename;
                if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $target)) {
                    $downloadUrl = getSetting('app_url') . '/uploads/' . rawurlencode($filename);
                }
            }

            if (empty($name)) {
                $error = 'Form name is required.';
            } else {
                try {
                    $st = $db->prepare("
                        INSERT INTO forms (name, list_id, headline, description, button_text, success_message, redirect_url, download_url, show_name, require_name, double_optin, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $st->execute([$name, $listId, $headline, $description, $buttonText, $successMessage, $redirectUrl, $downloadUrl, $showName, $requireName, $doubleOptin]);
                    
                    $_SESSION['flash_success'] = 'Subscription form created successfully!';
                    header('Location: ' . getSetting('app_url') . '/forms');
                    exit;
                } catch (Throwable $e) {
                    $error = 'Failed to create form: ' . $e->getMessage();
                }
            }
        }
    }

        // Fetch lists for targeting dropdown
        $lists = $db->query("SELECT * FROM lists ORDER BY name ASC")->fetchAll();

        $mediaFiles = [];
        $uploadDir = dirname(__DIR__) . '/uploads';
        if (is_dir($uploadDir)) {
            $dir = opendir($uploadDir);
            while (($file = readdir($dir)) !== false) {
                if ($file !== '.' && $file !== '..' && $file !== '.htaccess') {
                    $mediaFiles[] = ['name' => $file, 'url' => getSetting('app_url') . '/uploads/' . rawurlencode($file)];
                }
            }
            closedir($dir);
        }

        $title = 'Create Form';
        $isEdit = false;
        $viewPath = dirname(__DIR__) . '/views/form_create.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    /**
     * Editing pane for forms
     */
    public function edit(): void {
        $db = Database::getConnection();
        $id = (int)($_GET['id'] ?? 0);
        $error = null;

        // Fetch form definition
        $st = $db->prepare("SELECT * FROM forms WHERE id = ?");
        $st->execute([$id]);
        $form = $st->fetch();

        if (!$form) {
            $_SESSION['flash_error'] = 'Subscription form not found.';
            header('Location: ' . getSetting('app_url') . '/forms');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Auth::checkCsrf()) {
                $error = 'CSRF validation failed.';
            } else {
                $name = trim($_POST['name'] ?? '');
                $listId = ($_POST['list_id'] ?? '') !== '' ? (int)$_POST['list_id'] : null;
            $headline = trim($_POST['headline'] ?? 'Subscribe to our newsletter');
            $description = trim($_POST['description'] ?? '');
            $buttonText = trim($_POST['button_text'] ?? 'Subscribe');
            $successMessage = trim($_POST['success_message'] ?? 'Thank you for subscribing!');
            $redirectUrl = trim($_POST['redirect_url'] ?? '') ?: null;
            $downloadUrl = trim($_POST['download_url'] ?? '') ?: null;
            
            $showName = isset($_POST['show_name']) ? 1 : 0;
            $requireName = isset($_POST['require_name']) ? 1 : 0;
            $doubleOptin = isset($_POST['double_optin']) ? 1 : 0;

            if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__) . '/uploads';
                if (!file_exists($uploadDir)) @mkdir($uploadDir, 0755, true);
                $filename = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['upload_file']['name']));
                $target = $uploadDir . '/' . $filename;
                if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $target)) {
                    $downloadUrl = getSetting('app_url') . '/uploads/' . rawurlencode($filename);
                }
            }

            if (empty($name)) {
                $error = 'Form name is required.';
            } else {
                try {
                    $stUpdate = $db->prepare("
                        UPDATE forms 
                        SET name = ?, list_id = ?, headline = ?, description = ?, button_text = ?, success_message = ?, redirect_url = ?, download_url = ?, show_name = ?, require_name = ?, double_optin = ? 
                        WHERE id = ?
                    ");
                    $stUpdate->execute([$name, $listId, $headline, $description, $buttonText, $successMessage, $redirectUrl, $downloadUrl, $showName, $requireName, $doubleOptin, $id]);
                    
                    $_SESSION['flash_success'] = 'Subscription form updated successfully!';
                    header('Location: ' . getSetting('app_url') . '/forms');
                    exit;
                } catch (Throwable $e) {
                    $error = 'Failed to update form: ' . $e->getMessage();
                }
            }
        }
    }

        // Fetch lists for targeting
        $lists = $db->query("SELECT * FROM lists ORDER BY name ASC")->fetchAll();

        $mediaFiles = [];
        $uploadDir = dirname(__DIR__) . '/uploads';
        if (is_dir($uploadDir)) {
            $dir = opendir($uploadDir);
            while (($file = readdir($dir)) !== false) {
                if ($file !== '.' && $file !== '..' && $file !== '.htaccess') {
                    $mediaFiles[] = ['name' => $file, 'url' => getSetting('app_url') . '/uploads/' . rawurlencode($file)];
                }
            }
            closedir($dir);
        }

        $title = 'Edit Form';
        $isEdit = true;
        $viewPath = dirname(__DIR__) . '/views/form_create.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    /**
     * Public endpoint for subscriptions (GET render, POST process)
     */
    public function subscribe(): void {
        $db = Database::getConnection();
        $formId = (int)($_GET['form'] ?? $_POST['form_id'] ?? 0);
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || ($_GET['format'] ?? '') === 'json';

        // Load form definition
        $form = null;
        if ($formId > 0) {
            $st = $db->prepare("SELECT * FROM forms WHERE id = ?");
            $st->execute([$formId]);
            $form = $st->fetch();
        }

        if (!$form) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Subscription configuration not found.']);
                exit;
            }
            http_response_code(404);
            die("Error: Subscription form configuration not found.");
        }

        $error = null;
        $success = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = strtolower(trim($_POST['email'] ?? ''));
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');

            // 1. Validation Checks
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif ($form['require_name'] && $firstName === '') {
                $error = 'First name is required.';
            } else {
                // 2. Fire dynamic verification hooks to intercept invalid emails / spam traps
                $hookData = ['email' => $email, 'valid' => true, 'error' => ''];
                Hook::fire('before_add_contact', $hookData);

                if (!$hookData['valid']) {
                    $error = 'Deliverability Check Failed: ' . ($hookData['error'] ?: 'Invalid email address. Address does not exist.');
                } else {
                    $db->beginTransaction();
                    try {
                        // 3. Upsert subscriber record
                        $stInsert = $db->prepare("
                            INSERT INTO subscribers (email, first_name, last_name, status, created_at) 
                            VALUES (?, ?, ?, 'active', NOW())
                            ON DUPLICATE KEY UPDATE 
                                first_name = IF(first_name = '', VALUES(first_name), first_name),
                                last_name = IF(last_name = '', VALUES(last_name), last_name),
                                status = IF(status = 'unsubscribed', 'active', status),
                                updated_at = NOW()
                        ");
                        $stInsert->execute([$email, $firstName, $lastName]);

                        // Retrieve ID
                        $stGet = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
                        $stGet->execute([$email]);
                        $subId = (int)$stGet->fetchColumn();

                        if ($subId > 0) {
                            $userIp = $_SERVER['REMOTE_ADDR'] ?? '';
                            if ($userIp !== '') {
                                sc_update_subscriber_geoip($subId, $userIp);
                            }
                        }

                        // 4. Associate list membership
                        if ($subId > 0 && $form['list_id'] !== null) {
                            $listId = (int)$form['list_id'];
                            $listStatus = $form['double_optin'] ? 'pending' : 'confirmed';
                            
                            $stListAssign = $db->prepare("
                                INSERT INTO subscriber_lists (subscriber_id, list_id, status) 
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE status = VALUES(status)
                            ");
                            $stListAssign->execute([$subId, $listId, $listStatus]);
                        }

                        logActivity($subId, 'subscribe', "Subscribed via Form: '{$form['name']}'");
                        $db->commit();
                        
                        // Fire  workflow automations for form submission
                        Automation::trigger("form_submit:{$formId}", $subId);
                        Automation::trigger("subscribe", $subId);
                        
                        $hookDataAfter = ['subscriber_id' => $subId];
                        Hook::fire('contact_added', $hookDataAfter);
                        $success = true;

                        // Support redirecting on success
                        if ($form['redirect_url'] !== null && !$isAjax) {
                            header('Location: ' . $form['redirect_url']);
                            exit;
                        }

                    } catch (Throwable $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        $error = 'Failed to process subscription: ' . $e->getMessage();
                    }
                }
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                if ($success) {
                    echo json_encode(['success' => true, 'message' => $form['success_message']]);
                } else {
                    echo json_encode(['success' => false, 'message' => $error]);
                }
                exit;
            }
        }

        $appName = getSetting('app_name', 'Merlin Spellcaster');
        $viewPath = dirname(__DIR__) . '/views/subscribe.php';
        include $viewPath;
    }
}
