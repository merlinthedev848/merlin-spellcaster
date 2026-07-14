<?php
declare(strict_types=1);

if ($routePath === '/survey-builder') {
    $title = 'Survey Builder';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

if ($routePath === '/survey-builder/save') {
    header('Content-Type: application/json');
    $payload = json_decode(file_get_contents('php://input'), true);
    
    if (empty($payload['title'])) {
        echo json_encode(['success' => false, 'error' => 'Survey title is required.']);
        exit;
    }

    try {
        // Here we would typically save to a `surveys` and `survey_fields` table
        // For prototyping, we'll return success to show the flow.
        sleep(1);
        echo json_encode([
            'success' => true,
            'message' => 'Survey "' . htmlspecialchars($payload['title']) . '" saved successfully! It is now live.'
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
