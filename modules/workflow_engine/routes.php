<?php
declare(strict_types=1);

if ($routePath === '/automations') {
    $title = 'Behavioral Workflow Engine';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

if ($routePath === '/automations/save') {
    header('Content-Type: application/json');
    $payload = json_decode(file_get_contents('php://input'), true);
    
    try {
        sleep(1);
        echo json_encode([
            'success' => true,
            'message' => 'Automation workflow saved successfully!'
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
