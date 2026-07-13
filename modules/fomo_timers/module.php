<?php
declare(strict_types=1);

// We add a tiny UI hint in campaign_create.php
ModuleManager::addHook('campaign_form_after_subject', function() {
    $baseUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    ?>
    <div class="mt-3 bg-rose-500/10 border border-rose-500/20 rounded-xl p-4">
        <div class="flex items-center gap-3 mb-2">
            <svg class="w-5 h-5 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h3 class="font-bold text-white text-sm">FOMO Timer</h3>
        </div>
        <p class="text-xs text-slate-400 mb-2">Embed a live countdown timer in your email by pasting this image URL into your template:</p>
        <code class="text-xs bg-slate-900/50 p-2 rounded block font-mono text-rose-300 break-all">
            &lt;img src="<?= $baseUrl ?>/modules/fomo_timers/render.php?end=<?= date('Y-m-d', strtotime('+3 days')) ?>T23:59:59" width="400"&gt;
        </code>
    </div>
    <?php
});
