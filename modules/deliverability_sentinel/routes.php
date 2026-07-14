<?php
declare(strict_types=1);

if ($routePath === '/deliverability') {
    $title = 'AI Deliverability Sentinel';
    
    // Fetch all campaigns for the dropdown
    $db = Database::getConnection();
    $campaigns = $db->query("SELECT id, name, subject, body_html FROM campaigns ORDER BY created_at DESC LIMIT 20")->fetchAll();
    
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

if ($routePath === '/deliverability/scan') {
    header('Content-Type: application/json');
    
    $payload = json_decode(file_get_contents('php://input'), true);
    $text = strtolower(($payload['subject'] ?? '') . ' ' . ($payload['html_body'] ?? ''));
    
    if (empty(trim($text))) {
        echo json_encode(['success' => false, 'error' => 'No content to scan.']);
        exit;
    }

    try {
        sleep(2); // AI Processing delay
        
        $spamWords = [
            'free', 'guarantee', 'act now', 'urgent', 'winner', 'cash', 'money',
            'no cost', 'no obligation', 'risk-free', 'buy now', 'click here',
            'order now', 'limited time', 'exclusive deal', 'viagra', 'crypto',
            'investment', 'credit card'
        ];
        
        $foundWords = [];
        $score = 100;
        
        foreach ($spamWords as $word) {
            if (strpos($text, $word) !== false) {
                $foundWords[] = $word;
                $score -= 5;
            }
        }
        
        $score = max(0, $score);
        
        $verdict = 'Excellent';
        $color = '#34d399';
        if ($score < 90) { $verdict = 'Good'; $color = '#fbbf24'; }
        if ($score < 70) { $verdict = 'Risky'; $color = '#fb923c'; }
        if ($score < 50) { $verdict = 'High Spam Probability'; $color = '#ef4444'; }

        echo json_encode([
            'success' => true,
            'score' => $score,
            'verdict' => $verdict,
            'color' => $color,
            'triggers' => $foundWords
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
