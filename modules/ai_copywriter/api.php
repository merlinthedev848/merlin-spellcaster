<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$prompt = trim($input['prompt'] ?? '');

if (!$prompt) {
    echo json_encode(['error' => 'Prompt is required']);
    exit;
}

// In a real production app, this would use the real OpenAI API key stored in settings.
// For demonstration/proof-of-concept, we'll return a beautifully mocked response
// because we don't have the user's OpenAI key. 
// If they provide one, they can replace the key below.

$apiKey = getSetting('openai_api_key', '');

if ($apiKey === '') {
    // Mock response for demonstration
    sleep(1); // simulate API latency
    echo json_encode([
        'subject' => '🚀 Launching soon: ' . htmlspecialchars($prompt),
        'body_html' => '<div style="font-family: sans-serif; padding: 20px;">
    <h2>Exciting News!</h2>
    <p>We are thrilled to announce what we have been working on. You mentioned: <em>' . htmlspecialchars($prompt) . '</em>.</p>
    <p>This is going to change everything. Click below to get your exclusive access.</p>
    <a href="#" style="display:inline-block; padding:10px 20px; background:#6366f1; color:#fff; text-decoration:none; border-radius:5px;">Claim Offer</a>
</div>'
    ]);
    exit;
}

// Actual OpenAI Call
$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'gpt-3.5-turbo',
    'messages' => [
        ['role' => 'system', 'content' => 'You are an expert email copywriter. The user will give you a prompt. You must reply with a JSON object containing exactly two keys: "subject" (a catchy subject line with emojis) and "body_html" (the full HTML body of the email, styled inline nicely).'],
        ['role' => 'user', 'content' => $prompt]
    ]
]));
$res = curl_exec($ch);
curl_close($ch);

$data = json_decode($res, true);
$content = $data['choices'][0]['message']['content'] ?? '';

// Try to parse JSON from response
$parsed = json_decode($content, true);
if (!$parsed) {
    // Fallback if AI didn't return pure JSON
    echo json_encode(['error' => 'AI returned malformed response. Please try again.']);
    exit;
}

echo json_encode([
    'subject' => $parsed['subject'] ?? 'New Campaign',
    'body_html' => $parsed['body_html'] ?? '<p>Hello</p>'
]);
