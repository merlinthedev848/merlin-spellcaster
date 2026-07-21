<?php
declare(strict_types=1);

/**
 * index.php — Front Controller and URL Router
 */

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/config.php';

// Parse URL path relative to index.php
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';

$basePath = rtrim(dirname($scriptName), '/');
$routePath = parse_url($requestUri, PHP_URL_PATH) ?? '/';

if (str_starts_with($routePath, $basePath)) {
    $routePath = substr($routePath, strlen($basePath));
}
$routePath = '/' . ltrim($routePath, '/');

define('CURRENT_ROUTE', $routePath);
define('BASE_PATH', $basePath);

// Check if system is installed. If not, override route to setup
$localConfig = __DIR__ . '/config.local.php';
if (!file_exists($localConfig)) {
    $routePath = '/setup';
}

// ─── Global Authentication Security Gate ──────────────────────────────────────
$publicRoutes = [
    '/setup',
    '/login',
    '/logout',
    '/o',
    '/r',
    '/unsubscribe',
    '/api/webhooks',
    '/cron',
    '/subscribe',
    '/go',
    '/webhooks/incoming',
    '/api/form-submit',
    '/widget.js'
];

if (!in_array($routePath, $publicRoutes, true)) {
    if (!Auth::check()) {
        header('Location: ' . getSetting('app_url', 'http://localhost/merlin-spellcaster') . '/login');
        exit;
    }
}

// Router dispatch table
try {
    // Load enabled modules routes dynamically
    $enabledModules = ModuleManager::getEnabledModules();
    foreach ($enabledModules as $modId => $modInfo) {
        // Prevent Windows file locking by not including the module we are about to uninstall
        if ($routePath === '/extensions/uninstall' && isset($_GET['id']) && $_GET['id'] === $modId) {
            continue;
        }

        $routesFile = $modInfo['dir'] . '/routes.php';
        if (file_exists($routesFile)) {
            include $routesFile;
        }
    }

    switch ($routePath) {
        case '/setup':
            if (file_exists($localConfig)) {
                header('Location: ' . (getSetting('app_url') ?: '/'));
                exit;
            }
            $controller = new SetupController();
            $controller->index();
            break;

        case '/login':
            $controller = new AuthController();
            $controller->login();
            break;

        case '/logout':
            $controller = new AuthController();
            $controller->logout();
            break;

        case '/':
        case '/dashboard':
            $controller = new DashboardController();
            $controller->index();
            break;

        case '/contacts':
            $controller = new ContactController();
            $controller->index();
            break;

        case '/contacts/check-email':
            $controller = new ContactController();
            $controller->checkEmail();
            break;

        case '/contacts/view':
            $controller = new ContactController();
            $controller->view();
            break;

        case '/campaigns':
            $controller = new CampaignController();
            $controller->index();
            break;

        case '/analytics':
            $controller = new AnalyticsController();
            $controller->index();
            break;

        case '/campaigns/create':
            $controller = new CampaignController();
            $controller->create();
            break;

        case '/campaigns/edit':
            $controller = new CampaignController();
            $controller->edit();
            break;

        case '/templates':
            $controller = new TemplateController();
            $controller->index();
            break;

        case '/templates/create':
            $controller = new TemplateController();
            $controller->create();
            break;

        case '/templates/edit':
            $controller = new TemplateController();
            $controller->edit();
            break;

        case '/automations':
            $controller = new AutomationController();
            $controller->index();
            break;

        case '/automations/create':
            $controller = new AutomationController();
            $controller->create();
            break;

        case '/settings':
            $controller = new SettingController();
            $controller->index();
            break;

        case '/media':
            $controller = new MediaController();
            $controller->index();
            break;

        case '/forms':
            $controller = new FormController();
            $controller->index();
            break;

        case '/forms/create':
            $controller = new FormController();
            $controller->create();
            break;

        case '/forms/edit':
            $controller = new FormController();
            $controller->edit();
            break;

        case '/api/form-submit':
            $controller = new FormController();
            $controller->submitApi();
            break;

        case '/widget.js':
            header('Content-Type: application/javascript');
            if (file_exists(__DIR__ . '/widget.js')) {
                readfile(__DIR__ . '/widget.js');
            } else {
                echo "console.error('widget.js missing');";
            }
            exit;

        case '/subscribe':
            $controller = new FormController();
            $controller->subscribe();
            break;

        case '/extensions':
            $controller = new ModuleController();
            $controller->index();
            break;

        case '/extensions/toggle':
            $controller = new ModuleController();
            $controller->toggle();
            break;

        case '/extensions/upload':
            $controller = new ModuleController();
            $controller->upload();
            break;

        case '/extensions/uninstall':
            $controller = new ModuleController();
            $controller->uninstall();
            break;

        case '/super/tenants':
            require_once __DIR__ . '/controllers/TenantController.php';
            $controller = new TenantController();
            $controller->index();
            break;

        case '/super/tenants/create':
            require_once __DIR__ . '/controllers/TenantController.php';
            $controller = new TenantController();
            $controller->create();
            break;

        case '/diagnostics':
            $controller = new SettingController();
            $controller->diagnostics();
            break;

        case '/diagnostics/clear-logs':
            $controller = new SettingController();
            $controller->clearLogs();
            break;

        case '/cron':
            $controller = new SettingController();
            $controller->runCron();
            break;

        case '/o':
            $controller = new CampaignController();
            $controller->trackOpen();
            break;

        case '/r':
            $controller = new CampaignController();
            $controller->trackClick();
            break;

        case '/unsubscribe':
            $controller = new ContactController();
            $controller->unsubscribe();
            break;

        case '/api/webhooks':
            $controller = new SettingController();
            $controller->webhook();
            break;

        case '/api/imap-folders':
            $controller = new SettingController();
            $controller->fetchImapFolders();
            break;

        case '/api/test-smtp':
            $controller = new SettingController();
            $controller->testSmtp();
            break;

        case '/tags':
            require_once __DIR__ . '/controllers/TagController.php';
            $controller = new TagController();
            $controller->index();
            break;

        case '/tags/create':
            require_once __DIR__ . '/controllers/TagController.php';
            $controller = new TagController();
            $controller->create();
            break;

        case '/tags/edit':
            require_once __DIR__ . '/controllers/TagController.php';
            $controller = new TagController();
            $controller->edit();
            break;

        case '/automations/edit':
            $controller = new AutomationController();
            $controller->edit();
            break;

        default:
            http_response_code(404);
            $controller = new DashboardController();
            $controller->notFound();
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    error_log("Router error: " . $e->getMessage());
    echo "<!DOCTYPE html><html><head><title>System Error</title><link rel='stylesheet' href='".e(getSetting('app_url'))."/assets/css/theme.css'></head><body style='display:flex;align-items:center;justify-content:center;height:100vh;background:#f8f9fc;'><div class='card' style='max-width:500px;text-align:center;'><h1 style='color:#ff5b60;margin-bottom:12px;'>System Error</h1><p style='color:#4f5b76;'>An unexpected error occurred during execution. Please check the system logs or contact your administrator.</p><a href='/' class='btn btn-primary'>Return to Dashboard</a></div></body></html>";
}
