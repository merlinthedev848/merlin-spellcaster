<?php
declare(strict_types=1);

if ($routePath === '/ai-copywriter') {
    $title = 'AI Copywriter';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

if ($routePath === '/ai-copywriter/generate') {
    header('Content-Type: application/json');
    
    $prompt = trim($_POST['prompt'] ?? '');
    $tone = trim($_POST['tone'] ?? 'professional');
    $audience = trim($_POST['audience'] ?? 'general');
    
    if (empty($prompt)) {
        echo json_encode(['success' => false, 'error' => 'Please provide a prompt.']);
        exit;
    }

    try {
        $systemPrompt = "You are an expert email marketing copywriter. Write a highly converting email with a '$tone' tone targeting '$audience'. Respond in strict JSON format: {\"subject\": \"...\", \"body\": \"...\"}. Do not use markdown blocks for the JSON. Use HTML for the body content.";
        $userPrompt = $prompt;

        $content = AI::generate($systemPrompt, $userPrompt, 'json_object');
        
        $parsed = json_decode($content, true);
        if (!$parsed || !isset($parsed['subject']) || !isset($parsed['body'])) {
            echo json_encode(['success' => false, 'error' => 'AI Provider returned invalid JSON structure: ' . $content]);
            exit;
        }

        echo json_encode([
            'success' => true, 
            'subject' => $parsed['subject'],
            'body' => $parsed['body']
        ]);

    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($routePath === '/ai-copywriter/save-draft') {
    header('Content-Type: application/json');
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if (empty($subject) || empty($body)) {
        echo json_encode(['success' => false, 'error' => 'Subject and body are required.']);
        exit;
    }

    try {
        $db = Database::getConnection();
        $db->prepare("INSERT INTO campaigns (name, subject, body_html, body_text, status) VALUES (?, ?, ?, ?, 'draft')")
           ->execute([$subject, $subject, $body, strip_tags($body)]);
        
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
