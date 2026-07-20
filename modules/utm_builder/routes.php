<?php
declare(strict_types=1);

if ($routePath === '/utm-builder') {
    $db = Database::getConnection();
    
    // Bootstrap database table
    try {
        $db->exec("
        CREATE TABLE IF NOT EXISTS mod_utm_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_url VARCHAR(2048) NOT NULL,
            final_url VARCHAR(4096) NOT NULL,
            utm_source VARCHAR(255) NOT NULL,
            utm_medium VARCHAR(255) NOT NULL,
            utm_campaign VARCHAR(255) NOT NULL,
            utm_term VARCHAR(255) DEFAULT NULL,
            utm_content VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (PDOException $e) {
        error_log("UTM Builder migration error: " . $e->getMessage());
    }

    // Process POST save
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['original_url'])) {
        if (!Auth::checkCsrf()) {
            flash('error', 'CSRF validation failed.');
            sc_redirect('/utm-builder');
        }

        $orig = trim($_POST['original_url']);
        $source = trim($_POST['utm_source']);
        $medium = trim($_POST['utm_medium']);
        $campaign = trim($_POST['utm_campaign']);
        $term = trim($_POST['utm_term'] ?? '');
        $content = trim($_POST['utm_content'] ?? '');

        if ($orig === '' || $source === '' || $medium === '' || $campaign === '') {
            flash('error', 'Please fill in all required fields.');
        } else {
            // Build the final tracking URL
            $urlParts = parse_url($orig);
            $query = [];
            if (isset($urlParts['query'])) {
                parse_str($urlParts['query'], $query);
            }
            $query['utm_source'] = $source;
            $query['utm_medium'] = $medium;
            $query['utm_campaign'] = $campaign;
            if ($term !== '') $query['utm_term'] = $term;
            if ($content !== '') $query['utm_content'] = $content;

            $finalQuery = http_build_query($query);
            
            $finalUrl = (isset($urlParts['scheme']) ? $urlParts['scheme'] . '://' : '') .
                       (isset($urlParts['host']) ? $urlParts['host'] : '') .
                       (isset($urlParts['path']) ? $urlParts['path'] : '') .
                       ($finalQuery !== '' ? '?' . $finalQuery : '') .
                       (isset($urlParts['fragment']) ? '#' . $urlParts['fragment'] : '');

            try {
                $stmt = $db->prepare("
                    INSERT INTO mod_utm_links (original_url, final_url, utm_source, utm_medium, utm_campaign, utm_term, utm_content)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$orig, $finalUrl, $source, $medium, $campaign, $term !== '' ? $term : null, $content !== '' ? $content : null]);
                flash('success', 'UTM tracking link generated and saved successfully.');
            } catch (Exception $e) {
                flash('error', 'Failed to save tracking URL: ' . $e->getMessage());
            }
        }
        sc_redirect('/utm-builder');
    }

    // Process delete link
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
        if (!Auth::checkCsrf()) {
            flash('error', 'CSRF validation failed.');
            sc_redirect('/utm-builder');
        }
        $delId = (int)$_POST['delete_id'];
        $db->prepare("DELETE FROM mod_utm_links WHERE id = ?")->execute([$delId]);
        flash('success', 'Tracking URL removed.');
        sc_redirect('/utm-builder');
    }

    // Fetch saved URLs
    $savedLinks = $db->query("SELECT * FROM mod_utm_links ORDER BY id DESC")->fetchAll();

    $title = 'UTM Campaign Builder';
    $viewPath = __DIR__ . '/view.php';
    include dirname(dirname(__DIR__)) . '/views/layout.php';
    exit;
}
