<?php
declare(strict_types=1);

/**
 * Controller for managing modular extensions, plugin expansions, and ZIP installations
 */
class ModuleController {
    public function index(): void {
        $modules = ModuleManager::getModules();

        $title = 'Modules Directory';
        $viewPath = dirname(__DIR__) . '/views/modules.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    public function toggle(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = trim($_GET['id'] ?? '');
            if ($id !== '') {
                $status = ModuleManager::toggleModule($id);
                $msg = $status ? "Module '{$id}' enabled successfully." : "Module '{$id}' disabled.";
                $_SESSION['flash_success'] = $msg;
            }
        }
        header('Location: ' . getSetting('app_url') . '/extensions');
        exit;
    }

    /**
     * Upload and extract a new module ZIP archive
     */
    public function upload(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_FILES['module_zip']) && $_FILES['module_zip']['error'] === UPLOAD_ERR_OK) {
                $tmpFile = $_FILES['module_zip']['tmp_name'];
                
                // Verify Extension
                $ext = strtolower(pathinfo($_FILES['module_zip']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'zip') {
                    $_SESSION['flash_error'] = 'Invalid file format. Please upload a standard ZIP archive.';
                    header('Location: ' . getSetting('app_url') . '/extensions');
                    exit;
                }

                if (!class_exists('ZipArchive')) {
                    $_SESSION['flash_error'] = 'ZipArchive class is not enabled on this server PHP installation. Please enable it in php.ini.';
                    header('Location: ' . getSetting('app_url') . '/extensions');
                    exit;
                }

                $zip = new ZipArchive();
                if ($zip->open($tmpFile) === true) {
                    try {
                        // 1. Locate module.json and determine if there is a prefix folder
                        $prefix = '';
                        $moduleJsonContent = '';
                        
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $name = $zip->getNameIndex($i);
                            if (basename($name) === 'module.json') {
                                $prefix = dirname($name);
                                if ($prefix === '.') {
                                    $prefix = '';
                                } else {
                                    $prefix = rtrim($prefix, '/') . '/';
                                }
                                $moduleJsonContent = $zip->getFromIndex($i);
                                break;
                            }
                        }

                        if (empty($moduleJsonContent)) {
                            throw new RuntimeException("Invalid module package. module.json file was not found in the ZIP archive.");
                        }

                        // 2. Parse descriptor to extract ID
                        $json = json_decode($moduleJsonContent, true);
                        if (json_last_error() !== JSON_ERROR_NONE || !isset($json['id'])) {
                            throw new RuntimeException("Failed to parse module.json. The 'id' parameter is missing or invalid.");
                        }

                        $moduleId = trim($json['id']);
                        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $moduleId)) {
                            throw new RuntimeException("Invalid module ID structure inside module.json. Must contain alphanumeric characters, dashes, or underscores only.");
                        }

                        $targetDir = dirname(__DIR__) . '/modules/' . $moduleId;
                        if (!file_exists($targetDir)) {
                            if (!@mkdir($targetDir, 0755, true)) {
                                throw new RuntimeException("Failed to create modules directory at: {$targetDir}");
                            }
                        }

                        // 3. Extract ZIP contents stripping folder prefix
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $name = $zip->getNameIndex($i);
                            
                            // Skip folder nodes themselves
                            if (str_ends_with($name, '/')) {
                                continue;
                            }

                            // Calculate clean relative path
                            $cleanName = $name;
                            if ($prefix !== '' && str_starts_with($name, $prefix)) {
                                $cleanName = substr($name, strlen($prefix));
                            }

                            $destPath = $targetDir . '/' . $cleanName;
                            $destFolder = dirname($destPath);
                            
                            if (!file_exists($destFolder)) {
                                @mkdir($destFolder, 0755, true);
                            }

                            file_put_contents($destPath, $zip->getFromIndex($i));
                        }

                        $_SESSION['flash_success'] = "Module '{$json['name']}' uploaded and installed successfully!";

                    } catch (Throwable $e) {
                        $_SESSION['flash_error'] = 'Failed to extract module: ' . $e->getMessage();
                    } finally {
                        $zip->close();
                    }
                } else {
                    $_SESSION['flash_error'] = 'Failed to open ZIP archive. File may be corrupted.';
                }
            } else {
                $_SESSION['flash_error'] = 'Module ZIP file upload failed.';
            }
        }
        header('Location: ' . getSetting('app_url') . '/extensions');
        exit;
    }
}
