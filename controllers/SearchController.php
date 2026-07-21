<?php
declare(strict_types=1);

/**
 * SearchController — Handles global omnibox search across Contacts, Campaigns, Automations, Templates, Forms, and Media Assets.
 */
class SearchController {
    public function index(): void {
        $db = Database::getConnection();
        $query = trim($_GET['q'] ?? '');

        $contacts = [];
        $campaigns = [];
        $automations = [];
        $templates = [];
        $forms = [];

        if ($query !== '') {
            $searchTerm = "%{$query}%";

            // 1. Search Contacts
            $stCon = $db->prepare("
                SELECT id, email, first_name, last_name, status, lead_score, created_at 
                FROM subscribers 
                WHERE email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ?
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            $stCon->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $contacts = $stCon->fetchAll();

            // 2. Search Campaigns
            $stCamp = $db->prepare("
                SELECT id, name, subject, status, created_at 
                FROM campaigns 
                WHERE name LIKE ? OR subject LIKE ? 
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            $stCamp->execute([$searchTerm, $searchTerm]);
            $campaigns = $stCamp->fetchAll();

            // 3. Search Automations
            $stAuto = $db->prepare("
                SELECT id, name, trigger_event, status, created_at 
                FROM automations 
                WHERE name LIKE ? OR trigger_event LIKE ? 
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            $stAuto->execute([$searchTerm, $searchTerm]);
            $automations = $stAuto->fetchAll();

            // 4. Search Templates
            $stTpl = $db->prepare("
                SELECT id, name, subject, created_at 
                FROM templates 
                WHERE name LIKE ? OR subject LIKE ? 
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            $stTpl->execute([$searchTerm, $searchTerm]);
            $templates = $stTpl->fetchAll();

            // 5. Search Forms
            $stForm = $db->prepare("
                SELECT id, name, success_message, created_at 
                FROM forms 
                WHERE name LIKE ? OR success_message LIKE ? 
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            $stForm->execute([$searchTerm, $searchTerm]);
            $forms = $stForm->fetchAll();
        }

        $totalResults = count($contacts) + count($campaigns) + count($automations) + count($templates) + count($forms);
        $title = $query !== '' ? "Search Results for '{$query}'" : "Global Search";
        $viewPath = dirname(__DIR__) . '/views/search.php';
        include dirname(__DIR__) . '/views/layout.php';
    }
}
