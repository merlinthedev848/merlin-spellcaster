<?php
declare(strict_types=1);

if ($routePath === '/ai-settings') {
    $title = 'AI Settings & Integrations';
    $viewPath = __DIR__ . '/pages/ui.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

// Inbound API Gateway for AI Agents
if (str_starts_with($routePath, '/api/ai-agent/')) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
    
    // Serve the OpenAPI Schema
    if ($routePath === '/api/ai-agent/schema.json') {
        echo file_get_contents(__DIR__ . '/api/schema.json');
        exit;
    }

    // Authenticate via Bearer Token
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Missing or invalid Authorization header']);
        exit;
    }
    $token = $matches[1];

    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT id, agent_name FROM mod_ai_agent_keys WHERE api_key = ?");
    $stmt->execute([$token]);
    $key = $stmt->fetch();

    if (!$key) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid API Key']);
        exit;
    }

    // Update last used
    $db->prepare("UPDATE mod_ai_agent_keys SET last_used_at = NOW() WHERE id = ?")->execute([$key['id']]);

    $action = $_GET['action'] ?? '';

    if ($action === 'stats') {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit; }
        
        $totalSubs = $db->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();
        $avgOpen = $db->query("SELECT ROUND(SUM(open_count)/NULLIF(SUM(send_count),0)*100,1) FROM campaigns WHERE status='sent'")->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_subscribers' => (int)$totalSubs,
                'average_open_rate_percent' => (float)$avgOpen
            ]
        ]);
        exit;
    }

    if ($action === 'draft_campaign') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $subject = $data['subject'] ?? '';
        $bodyHtml = $data['body_html'] ?? '';
        
        if (empty($subject) || empty($bodyHtml)) {
            http_response_code(400);
            echo json_encode(['error' => 'subject and body_html are required']);
            exit;
        }
        
        try {
            $db->prepare("INSERT INTO campaigns (subject, body_html, body_text, status) VALUES (?, ?, ?, 'draft')")
               ->execute([$subject, $bodyHtml, strip_tags($bodyHtml)]);
               
            echo json_encode(['success' => true, 'message' => "Campaign drafted by agent {$key['agent_name']}"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Action not found']);
    exit;
}
