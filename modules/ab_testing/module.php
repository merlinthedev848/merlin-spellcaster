<?php
declare(strict_types=1);

// Hook into campaign_create to add A/B subject line options
ModuleManager::addHook('campaign_form_after_subject', function() {
    ?>
    <div class="mt-4 bg-fuchsia-500/10 border border-fuchsia-500/20 rounded-xl p-4" x-data="{ enabled: false }">
        <label class="flex items-center gap-3 cursor-pointer mb-3">
            <input type="checkbox" name="ab_test_enabled" value="1" x-model="enabled" class="rounded border-fuchsia-500/50 text-fuchsia-500 focus:ring-fuchsia-500 bg-slate-900/50">
            <span class="font-bold text-fuchsia-400 text-sm">Enable A/B Subject Testing</span>
        </label>
        <div x-show="enabled" x-collapse>
            <p class="text-xs text-slate-400 mb-3">
                The main subject above will be Test A. Enter Test B below. We'll send A to 10%, B to 10%, wait 2 hours, and send the winner to the remaining 80%.
            </p>
            <input type="text" name="ab_subject_b" class="form-input w-full" placeholder="Test B Subject Line...">
        </div>
    </div>
    <?php
});

// Since we cannot easily hijack the core form processing without a specific hook, 
// a robust system would have a 'campaign_created' hook.
// Let's add that hook to the module file assuming it will be added to core.
ModuleManager::addHook('campaign_created', function($campaignId, $postData) {
    global $db;
    if (!empty($postData['ab_test_enabled']) && !empty($postData['ab_subject_b'])) {
        $db->prepare("INSERT INTO mod_ab_tests (campaign_id, variant_a_subject, variant_b_subject) VALUES (?, ?, ?)")
           ->execute([$campaignId, $postData['subject'], $postData['ab_subject_b']]);
    }
});
