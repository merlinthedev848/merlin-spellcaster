<?php
// Triggers automatically whenever a subscriber clicks a tracking link routed via r.php
ModuleManager::addHook('link_clicked', function($subscriberId, $campaignId, $destination) {
    // In a real scenario, this is where you execute a cURL request to a webhook, CRM, or Slack API
    error_log("[Merlin Spellcaster] Magic detected: Audience ID #{$subscriberId} interacted with Campaign #{$campaignId} -> {$destination}");
}, 10);