<?php
declare(strict_types=1);
$nameVal = $isEdit ? $template['name'] : '';
$subjectVal = $isEdit ? $template['subject'] : '';
$htmlVal = $isEdit ? $template['body_html'] : '<div style="font-family: sans-serif; max-width: 600px; margin: auto; padding: 20px;">
    <h2 style="color: #635bff;">Hello {{first_name}},</h2>
    <p>Welcome to our update newsletter!</p>
    <hr style="border: 0; border-top: 1px solid #e3e8ee; margin: 20px 0;">
    <p style="font-size: 12px; color: #4f5b76;">To opt-out of these emails, <a href="{{unsubscribe_url}}" style="color: #635bff;">click here to unsubscribe</a>.</p>
</div>';
$textVal = $isEdit ? $template['body_text'] : '';
?>

<div class="header-actions">
    <div class="page-title">
        <a href="<?= e(getSetting('app_url')) ?>/templates" style="color: var(--theme-dark-slate); font-weight: 500; font-size: 13px; text-decoration: none; display: flex; align-items: center; gap: 4px; margin-bottom: 8px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Templates
        </a>
        <h1><?= $isEdit ? 'Edit Template' : 'Create Template' ?></h1>
        <p>Compose reusable newsletter templates with live side-by-side previewing.</p>
    </div>
</div>

<?php if (isset($error)): ?>
    <div style="background-color: var(--danger-light); color: var(--danger); border: 1px solid rgba(255,91,96,0.1); border-radius: 6px; padding: 12px; font-size: 13px; font-weight: 500; margin-bottom: 24px; max-width: 800px;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="grid grid-2">
    <!-- Template Composer Form -->
    <div class="card">
        <div class="card-header"><span class="card-title">Template Parameters</span></div>
        
        <form method="post" action="">
            <div class="form-group">
                <label class="form-label" for="name">Template Name</label>
                <input class="form-control" type="text" id="name" name="name" value="<?= e($nameVal) ?>" required placeholder="e.g. Standard Company Announcement">
            </div>

            <div class="form-group">
                <label class="form-label" for="subject">Default Subject Line (Optional)</label>
                <input class="form-control" type="text" id="subject" name="subject" value="<?= e($subjectVal) ?>" placeholder="e.g. Company Update: Month Year">
            </div>

            <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <label class="form-label" for="body_html" style="margin-bottom: 0;">HTML Base Template Content</label>
                <span style="font-size: 11px; color: var(--theme-dark-slate);">Use standard variables like <code>{{first_name}}</code></span>
            </div>
            <!-- WYSIWYG Editor Container -->
            <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
            <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
            <div id="quill-editor" style="height: 350px; background: white; font-family: monospace; font-size: 13px;"></div>
            <textarea class="form-control" id="body_html" name="body_html" required style="display: none;"><?= e($htmlVal) ?></textarea>
        </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label" for="body_text">Plain Text Alternative (Optional)</label>
                <textarea class="form-control" id="body_text" name="body_text" placeholder="Plain text version for text-only email fallback..."><?= e($textVal) ?></textarea>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="<?= e(getSetting('app_url')) ?>/templates" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Template Framework' ?></button>
            </div>
        </form>
    </div>

    <!-- Live Preview -->
    <div style="display: flex; flex-direction: column; height: 100%;">
        <div class="card" style="flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 480px;">
            <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 16px; margin-bottom: 0;">
                <span class="card-title">HTML Live Preview Renderer</span>
                <span style="font-size: 11px; background-color: var(--success-light); color: var(--success); font-weight: 600; padding: 2px 6px; border-radius: 4px;">Active</span>
            </div>
            <div style="flex-grow: 1; background: #ffffff; padding: 0; position: relative;">
                <iframe id="template_preview" style="width: 100%; height: 100%; border: none; background: #ffffff; min-height: 400px;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const textarea = document.getElementById("body_html");
        const iframe = document.getElementById("template_preview");

        // Initialize Quill
        window.quill = new Quill('#quill-editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link', 'image'],
                    ['clean'],
                    ['code-block']
                ]
            }
        });
        
        // Set initial content
        if (textarea.value) {
            window.quill.root.innerHTML = textarea.value;
        }

        function updatePreview() {
            let html = textarea.value;
            
            // Mock placeholder tags for visual convenience
            html = html.replace(/\{\{\s*first_name\s*\}\}|\{\{first_name\}\}/g, "John");
            html = html.replace(/\{\{\s*unsubscribe_url\s*\}\}|\{\{unsubscribe_url\}\}/g, "#");
            html = html.replace(/\{\{\s*app_name\s*\}\}|\{\{app_name\}\}/g, "Merlin Spellcaster");

            const doc = iframe.contentDocument || iframe.contentWindow.document;
            doc.open();
            doc.write(html);
            doc.close();
        }

        // Sync Quill to textarea and update live preview
        window.quill.on('text-change', function() {
            textarea.value = window.quill.root.innerHTML;
            updatePreview();
        });

        textarea.addEventListener("input", updatePreview);
        updatePreview();
    });
</script>
