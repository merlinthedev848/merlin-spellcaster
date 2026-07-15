<?php
declare(strict_types=1);

/**
 * Controller for handling Media Library uploads and deletions
 */
class MediaController {
    
    private string $uploadDir;
    
    public function __construct() {
        $this->uploadDir = dirname(__DIR__) . '/uploads';
        if (!file_exists($this->uploadDir)) {
            @mkdir($this->uploadDir, 0755, true);
        }
    }

    public function index(): void {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->upload();
        } elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->delete();
        } else {
            $this->view();
        }
    }

    private function view(): void {
        $files = [];
        if (is_dir($this->uploadDir)) {
            $dir = opendir($this->uploadDir);
            while (($file = readdir($dir)) !== false) {
                if ($file !== '.' && $file !== '..' && $file !== '.htaccess') {
                    $path = $this->uploadDir . '/' . $file;
                    $files[] = [
                        'name' => $file,
                        'size' => filesize($path),
                        'time' => filemtime($path),
                        'url'  => getSetting('app_url') . '/uploads/' . rawurlencode($file)
                    ];
                }
            }
            closedir($dir);
        }
        
        // Sort by time descending
        usort($files, fn($a, $b) => $b['time'] <=> $a['time']);

        $title = 'Media Library';
        $viewPath = dirname(__DIR__) . '/views/media.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    private function upload(): void {
        if (!Auth::checkCsrf()) {
            $_SESSION['flash_error'] = 'Invalid security token';
            header('Location: ' . getSetting('app_url') . '/media');
            exit;
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Upload failed or no file selected.';
            header('Location: ' . getSetting('app_url') . '/media');
            exit;
        }

        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip'];
        $fileName = basename($_FILES['file']['name']);
        
        // Sanitize file name
        $fileName = preg_replace("/[^a-zA-Z0-9_\-\.]/", "_", $fileName);
        
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts, true)) {
            $_SESSION['flash_error'] = 'Invalid file type. Only JPG, PNG, GIF, WEBP, PDF, and ZIP are allowed.';
            header('Location: ' . getSetting('app_url') . '/media');
            exit;
        }
        
        // Ensure uniqueness
        $target = $this->uploadDir . '/' . $fileName;
        $counter = 1;
        while (file_exists($target)) {
            $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
            $target = $this->uploadDir . '/' . $nameWithoutExt . '_' . $counter . '.' . $ext;
            $counter++;
        }

        if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            $_SESSION['flash_success'] = 'File uploaded successfully.';
        } else {
            $_SESSION['flash_error'] = 'Failed to move uploaded file.';
        }

        header('Location: ' . getSetting('app_url') . '/media');
        exit;
    }

    private function delete(): void {
        if (!Auth::checkCsrf()) {
            $_SESSION['flash_error'] = 'Invalid security token';
            header('Location: ' . getSetting('app_url') . '/media');
            exit;
        }

        $file = basename($_POST['filename'] ?? '');
        if ($file && $file !== '.htaccess') {
            $path = $this->uploadDir . '/' . $file;
            if (file_exists($path) && is_file($path)) {
                unlink($path);
                $_SESSION['flash_success'] = 'File deleted.';
            }
        }

        header('Location: ' . getSetting('app_url') . '/media');
        exit;
    }
}
