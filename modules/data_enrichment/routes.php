<?php
declare(strict_types=1);

if ($routePath === '/enrichment') {
    $title = 'B2B Data Enrichment';
    
    // Fetch some corporate contacts that are candidates for enrichment
    $db = Database::getConnection();
    $contacts = $db->query("
        SELECT id, email, first_name, last_name, attributes 
        FROM subscribers 
        WHERE email NOT LIKE '%gmail.com' 
          AND email NOT LIKE '%yahoo.com' 
          AND email NOT LIKE '%hotmail.com' 
          AND email NOT LIKE '%outlook.com'
        LIMIT 10
    ")->fetchAll();
    
    foreach ($contacts as &$c) {
        $attrs = json_decode($c['attributes'] ?? '', true) ?: [];
        $c['company'] = $attrs['company'] ?? 'Pending Lookup...';
        $c['size'] = $attrs['company_size'] ?? '—';
        $c['industry'] = $attrs['industry'] ?? '—';
        $c['enriched'] = !empty($attrs['company']);
    }
    unset($c);

    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}

if ($routePath === '/enrichment/run') {
    header('Content-Type: application/json');
    $payload = json_decode(file_get_contents('php://input'), true);
    $subId = (int)($payload['subscriber_id'] ?? 0);
    
    if ($subId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid subscriber ID.']);
        exit;
    }

    try {
        $db = Database::getConnection();
        
        $st = $db->prepare("SELECT email, attributes FROM subscribers WHERE id = ?");
        $st->execute([$subId]);
        $sub = $st->fetch();
        
        if (!$sub) {
            echo json_encode(['success' => false, 'error' => 'Contact not found.']);
            exit;
        }
        
        // Mock scraping domain data
        sleep(2);
        
        $parts = explode('@', $sub['email']);
        $domain = $parts[1] ?? '';
        $companyName = ucfirst(explode('.', $domain)[0]);
        
        $industries = ['SaaS / Tech', 'Retail', 'Healthcare', 'Consulting & Business', 'Finance / Banking'];
        $sizes = ['1-10 employees', '11-50 employees', '51-200 employees', '201-500 employees', '500+ employees'];
        
        $attrs = json_decode($sub['attributes'] ?? '', true) ?: [];
        $attrs['company'] = $companyName;
        $attrs['industry'] = $industries[array_rand($industries)];
        $attrs['company_size'] = $sizes[array_rand($sizes)];
        $attrs['location'] = 'New York, US';
        
        $stUpdate = $db->prepare("UPDATE subscribers SET attributes = ? WHERE id = ?");
        $stUpdate->execute([json_encode($attrs), $subId]);
        
        echo json_encode([
            'success' => true,
            'company' => $attrs['company'],
            'size' => $attrs['company_size'],
            'industry' => $attrs['industry'],
            'message' => 'Successfully enriched B2B profile for ' . $sub['email']
        ]);
        
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
