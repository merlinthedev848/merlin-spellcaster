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
        // Mock AI generation delay
        sleep(3);
        
        // Mock responses based on tone
        $subject = "Unlock Your Potential with Our New Solution";
        $body = "Hi {first_name},\n\nWe know how hard you work to achieve your goals. That's why we built a solution designed specifically for {$audience}.\n\n" . 
                "With our latest offering, you can streamline your workflow and get back to what matters most.\n\n" .
                "Are you ready to transform your process?\n\nBest,\nThe Team";
        
        if ($tone === 'witty') {
            $subject = "Don't let your workflow be a joke. (Unless it's a good one)";
            $body = "Hey {first_name},\n\nTired of doing things the hard way? We thought so. We made something for {$audience} that actually works.\n\n" . 
                    "No magic tricks, just solid results. Give it a spin.\n\nCheers,\nYour Friends";
        } elseif ($tone === 'urgent') {
            $subject = "Action Required: Elevate Your Strategy Today";
            $body = "Hi {first_name},\n\nTime is running out to capitalize on the latest trends for {$audience}. Our new solution is built to give you the edge immediately.\n\n" . 
                    "Act now to secure your advantage.\n\nRegards,\nThe Team";
        }

        echo json_encode([
            'success' => true, 
            'subject' => $subject,
            'body' => $body
        ]);

    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
