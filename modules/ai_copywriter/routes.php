<?php
declare(strict_types=1);

// Route: /ai-copywriter
if ($routePath === '/ai-copywriter') {
    $title = 'AI Assistant & Copywriter Suite';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

// Route: /ai-copywriter/generate (AJAX API Generator)
if ($routePath === '/ai-copywriter/generate') {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'POST required']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $audience = trim($data['audience'] ?? 'Video Producers & Creative Directors');
    $offer = trim($data['offer'] ?? 'British Voice Over & Audio Production');
    $tone = trim($data['tone'] ?? 'persuasive');
    $benefits = trim($data['benefits'] ?? 'Broadcast quality home studio, 24-hour turnaround, versatile tones');

    $apiKey = getSetting('openai_api_key', '');

    if (!empty($apiKey)) {
        // Live OpenAI GPT API Request
        $prompt = "You are a world-class B2B email copywriter. Write high-converting email copy for:\nTarget Audience: {$audience}\nService/Offer: {$offer}\nTone: {$tone}\nKey Benefits: {$benefits}\nReturn JSON with keys: 'subjects' (array of 5 subject lines), 'email_a' (Direct pitch email body), 'email_b' (Problem-Agitate-Solve email body), 'email_c' (Social proof / story pitch).";
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'response_format' => ['type' => 'json_object']
        ]));
        
        $res = curl_exec($ch);
        if (PHP_VERSION_ID < 80000) { @curl_close($ch); }

        $jsonRes = json_decode($res ?: '', true);
        $aiText = $jsonRes['choices'][0]['message']['content'] ?? '';
        $parsedAi = json_decode($aiText, true);

        if ($parsedAi && isset($parsedAi['subjects'])) {
            echo json_encode([
                'status' => 'success',
                'engine' => 'Live OpenAI GPT-4o Engine',
                'subjects' => $parsedAi['subjects'],
                'email_a' => $parsedAi['email_a'],
                'email_b' => $parsedAi['email_b'],
                'email_c' => $parsedAi['email_c']
            ]);
            exit;
        }
    }

    // Built-in Intelligent NLP Copy Engine (Fallback when no API key configured)
    $subjects = [
        "Quick question regarding your upcoming video projects, {{first_name}}",
        "Looking for a versatile British voiceover for {{company}}?",
        "Broadcast-quality British VO for your next campaign (24h turnaround)",
        "Idea for {{company}}'s video & e-learning audio",
        "Pro British voiceover demo for {{company}}"
    ];

    $emailA = "Hi {{first_name}},\n\nI came across {{company}}'s recent work and wanted to reach out directly.\n\nI'm a professional British voiceover artist specializing in {$offer} for {$audience}.\n\nHere is what I bring to your production pipeline:\n- {$benefits}\n- Broadcast-grade acoustically treated studio\n- Fast 24-hour delivery with free revisions\n\nWould you be open to hearing a quick 30-second custom sample for one of your current projects?\n\nBest regards,\n[Your Name]\n[Your Studio Link]";

    $emailB = "Hi {{first_name}},\n\nFinding reliable voice talent that delivers broadcast-ready audio on tight production deadlines can be a constant bottleneck.\n\nI help {$audience} eliminate that headache. Whether you need corporate narrations, commercial spots, or e-learning tracks, I deliver clean, polished audio within 24 hours.\n\nCan I send over my latest voiceover reel for your roster?\n\nBest,\n[Your Name]";

    $emailC = "Hi {{first_name}},\n\nWe recently wrapped a voiceover project for a leading production team looking for authentic British narration, and it got me thinking about {{company}}.\n\nIf you have any commercial, corporate, or video projects coming up that require {$offer}, I'd love to audition for you.\n\nKey highlights:\n- {$benefits}\n- Direct remote direction via Source-Connect / Zoom\n\nLet me know if I can drop a quick custom sample into your inbox!\n\nCheers,\n[Your Name]";

    echo json_encode([
        'status' => 'success',
        'engine' => 'Built-in Copywriting Engine',
        'subjects' => $subjects,
        'email_a' => $emailA,
        'email_b' => $emailB,
        'email_c' => $emailC
    ]);
    exit;
}

// Redirect aliases
if ($routePath === '/ai-settings') {
    header('Location: ' . getSetting('app_url') . '/ai-copywriter?tab=settings');
    exit;
}
