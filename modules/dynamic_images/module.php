<?php
declare(strict_types=1);

// Add a UI hint in campaign_create.php
ModuleManager::addHook('campaign_form_after_subject', function() {
    $baseUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    ?>
    <div class="mt-3 bg-amber-500/10 border border-amber-500/20 rounded-xl p-4">
        <div class="flex items-center gap-3 mb-2">
            <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <h3 class="font-bold text-white text-sm">Dynamic Images</h3>
        </div>
        <p class="text-xs text-slate-400 mb-2">Embed a personalized graphic by pasting this image URL into your template. The system replaces {{first_name}} when sending!</p>
        <code class="text-xs bg-slate-900/50 p-2 rounded block font-mono text-amber-300 break-all">
            &lt;img src="<?= $baseUrl ?>/modules/dynamic_images/render.php?text={{first_name}}" width="400"&gt;
        </code>
    </div>
    <?php
});
