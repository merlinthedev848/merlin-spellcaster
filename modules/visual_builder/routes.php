<?php
declare(strict_types=1);

if ($routePath === '/visual-builder') {
    $title = 'Visual Email Builder';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

if ($routePath === '/visual-builder/export') {
    header('Content-Type: application/json');
    $payload = json_decode(file_get_contents('php://input'), true);
    
    try {
        $html = $payload['html'] ?? '';
        
        // Mock save logic
        sleep(1);
        
        echo json_encode([
            'success' => true,
            'message' => 'Template exported successfully! Ready for use in campaigns.'
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
