<?php
declare(strict_types=1);

ModuleManager::addHook('campaign_form_after_subject', function() {
    ?>
    <div class="mt-3 bg-indigo-500/10 border border-indigo-500/20 rounded-xl p-4" x-data="aiCopywriter()">
        <div class="flex items-center gap-3 mb-2">
            <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
            <h3 class="font-bold text-white text-sm">AI Magic Wand</h3>
        </div>
        <p class="text-xs text-slate-400 mb-3">Describe your email and let AI write the subject and body for you.</p>
        <div class="flex gap-2">
            <input type="text" x-model="prompt" class="form-input w-full text-sm bg-slate-900/50" placeholder="e.g. A launch email for our new SaaS product offering 20% off">
            <button type="button" @click="generate" :disabled="loading" class="btn btn-secondary whitespace-nowrap">
                <span x-show="!loading">✨ Generate</span>
                <span x-show="loading">Thinking...</span>
            </button>
        </div>
        <div x-show="error" x-text="error" class="text-xs text-red-400 mt-2"></div>
    </div>
    
    <script>
    function aiCopywriter() {
        return {
            prompt: '',
            loading: false,
            error: '',
            async generate() {
                if (!this.prompt) {
                    this.error = 'Please enter a prompt.';
                    return;
                }
                this.loading = true;
                this.error = '';
                try {
                    const res = await fetch('/modules/ai_copywriter/api.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({prompt: this.prompt})
                    });
                    const data = await res.json();
                    if (data.error) throw new Error(data.error);
                    
                    // Inject into form
                    document.querySelector('input[name="subject"]').value = data.subject;
                    // Try to inject into TinyMCE if it exists, otherwise standard textarea
                    if (typeof tinymce !== 'undefined' && tinymce.get('bodyHtml')) {
                        tinymce.get('bodyHtml').setContent(data.body_html);
                    } else {
                        const ta = document.querySelector('textarea[name="body_html"]') || document.querySelector('#bodyHtml');
                        if (ta) ta.value = data.body_html;
                    }
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.loading = false;
                }
            }
        }
    }
    </script>
    <?php
});
