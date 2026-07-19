<?php
declare(strict_types=1);

$nameVal = $isEdit ? $campaign['name'] : '';
$subjectVal = $isEdit ? $campaign['subject'] : '';
$listIdVal = $isEdit ? (int)$campaign['list_id'] : 0;
$schedVal = ($isEdit && $campaign['scheduled_at']) ? date('Y-m-d\TH:i', strtotime($campaign['scheduled_at'])) : '';
$campTagIds = $isEdit ? $campaignTagIds : [];
$unsubChecked = $isEdit ? ((int)$campaign['include_unsubscribe'] === 1) : true;
$maxPerHourVal = $isEdit ? (int)$campaign['max_per_hour'] : 0;
$htmlVal = $isEdit ? $campaign['body_html'] : '';
$textVal = $isEdit ? $campaign['body_text'] : '';
?>

<div class="header-actions">
    <div class="page-title">
        <a href="<?= e(getSetting('app_url')) ?>/campaigns" style="color: var(--theme-dark-slate); font-weight: 500; font-size: 13px; text-decoration: none; display: flex; align-items: center; gap: 4px; margin-bottom: 8px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Campaigns
        </a>
        <h1><?= $isEdit ? 'Edit Campaign' : 'Create Campaign' ?></h1>
        <p>Target custom lists and tags, edit HTML templates specifically for this send, and control unsubscribe links.</p>
    </div>
</div>

<?php if (isset($error)): ?>
    <div style="background-color: var(--danger-light); color: var(--danger); border: 1px solid rgba(255,91,96,0.1); border-radius: 6px; padding: 12px; font-size: 13px; font-weight: 500; margin-bottom: 24px; max-width: 800px;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="grid grid-2">
    <!-- Campaign Settings Pane -->
    <div class="card" style="display: flex; flex-direction: column; gap: 20px;">
        <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 16px; margin-bottom: 0;">
            <span class="card-title">Campaign Settings</span>
        </div>
        
        <form method="post" action="">
            <?= Auth::csrfField() ?>
            <div class="form-group">
                <label class="form-label" for="name">Internal Campaign Name</label>
                <input class="form-control" type="text" id="name" name="name" value="<?= e($nameVal) ?>" required placeholder="e.g. Cold Lead Outreach #1">
            </div>

            <div class="form-group">
                <label class="form-label" for="template_id">Choose Base Template Framework</label>
                <select class="form-control" id="template_id" name="template_id" onchange="applyTemplatePreset(this.value)">
                    <option value="">-- Start with Blank Canvas or Choose Preset --</option>
                    <?php foreach ($templates as $temp): ?>
                        <option value="<?= $temp['id'] ?>" <?= ($isEdit && $matchedTemplateId === (int)$temp['id']) ? 'selected' : '' ?>><?= e($temp['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="subject">Email Subject Line</label>
                <input class="form-control" type="text" id="subject" name="subject" value="<?= e($subjectVal) ?>" placeholder="e.g. Introduction from CK Media Services">
            </div>
            <?php $dummyData = null; Hook::fire('campaign_form_after_subject', $dummyData); ?>

            <!-- Email Body Workspaces -->
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <label class="form-label" for="body_html" style="margin-bottom: 0;">HTML Email Body Content</label>
                    <span style="font-size: 11px; color: var(--theme-dark-slate);">Supports <code>{{first_name}}</code>, <code>{{unsubscribe_url}}</code></span>
                </div>
                <!-- HTML Code Editor -->
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css">
                <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/xml/xml.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/css/css.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/javascript/javascript.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/htmlmixed/htmlmixed.min.js"></script>
                <style>
                    .CodeMirror { height: 250px; border: 1px solid var(--theme-border); border-radius: 6px; font-family: monospace; font-size: 13px; }
                </style>
                <textarea class="form-control" id="body_html" name="body_html" required style="display: none;"><?= e($htmlVal) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="body_text">Plain Text Alternative (Optional)</label>
                <textarea class="form-control" id="body_text" name="body_text" placeholder="Plain text version for text-only fallback..."><?= e($textVal) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="list_id">Target Recipient Segment List</label>
                <select class="form-control" id="list_id" name="list_id" required>
                    <option value="0">All Active Contacts</option>
                    <?php foreach ($lists as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= ($listIdVal === (int)$l['id']) ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Target Tag Filtering -->
            <div class="form-group">
                <label class="form-label">Filter Targeting by CRM Tags (Optional)</label>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; padding: 12px; border: 1px solid var(--theme-border); border-radius: 6px; background-color: #fafbfc; max-height: 120px; overflow-y: auto;">
                    <?php foreach ($tags as $t): ?>
                        <label style="display: inline-flex; align-items: center; gap: 6px; background-color: white; border: 1px solid var(--theme-border); padding: 4px 10px; border-radius: 20px; font-size: 12px; cursor: pointer; font-weight: 500;">
                            <input type="checkbox" name="tags[]" value="<?= $t['id'] ?>" style="accent-color: var(--theme-blurple);" <?= ($isEdit && in_array((int)$t['id'], $campTagIds, true)) ? 'checked' : '' ?>>
                            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: <?= e($t['color']) ?>;"></span>
                            <?= e($t['name']) ?>
                        </label>
                    <?php endforeach; ?>
                    <?php if (empty($tags)): ?>
                        <span style="color: var(--theme-dark-slate); font-size: 12px;">No tags created.</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Scheduled Start Date Picker -->
            <div class="form-group">
                <label class="form-label" for="scheduled_at">Scheduled Release Start Date/Time (Optional)</label>
                <input class="form-control" type="datetime-local" id="scheduled_at" name="scheduled_at" value="<?= e($schedVal) ?>">
            </div>

            <!-- Hourly Throttle limit -->
            <div class="form-group">
                <label class="form-label" for="max_per_hour">Hourly Send Throttle Limit</label>
                <input class="form-control" type="number" id="max_per_hour" name="max_per_hour" min="0" value="<?= $maxPerHourVal ?>" placeholder="e.g. 50 (0 for unlimited)">
                <p style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 4px;">Restricts this campaign to send at most X emails per hour, protecting your SMTP server reputation.</p>
            </div>

            <!-- Unsubscribe Footer Settings -->
            <div class="form-group" style="margin-bottom: 32px;">
                <label style="display: inline-flex; align-items: center; gap: 8px; font-weight: 500; font-size: 13px; cursor: pointer;">
                    <input type="checkbox" name="include_unsubscribe" value="1" style="accent-color: var(--theme-blurple);" <?= $unsubChecked ? 'checked' : '' ?>>
                    Include automatic unsubscribe link in email footer
                </label>
                <p style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 4px; margin-left: 20px;">
                    Uncheck to disable the automatic unsubscribe block (highly recommended for cold outreach to make emails feel personal). Note that manually placed <code>{{unsubscribe_url}}</code> will always be processed.
                </p>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="<?= e(getSetting('app_url')) ?>/campaigns" class="btn btn-secondary">Cancel</a>
                <?php if ($isEdit): ?>
                    <button type="submit" name="save_campaign" class="btn btn-secondary" style="font-weight: 600;">Save Campaign</button>
                <?php endif; ?>
                <button type="submit" name="save_draft" class="btn btn-secondary" style="font-weight: 600;">Save Draft</button>
                <button type="submit" name="send_now" class="btn btn-primary" onclick="return confirm('Queue and activate sending?');">Schedule & Active Queue →</button>
            </div>
        </form>
    </div>

    <!-- Live Preview Pane -->
    <div style="display: flex; flex-direction: column; height: 100%;">
        <div class="card" style="flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 480px;">
            <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 16px; margin-bottom: 0;">
                <span class="card-title">HTML Live Preview Renderer</span>
                <span id="preview_indicator" style="font-size: 11px; background-color: var(--theme-border); color: var(--theme-dark-slate); font-weight: 600; padding: 2px 6px; border-radius: 4px;">Pre-rendering</span>
            </div>
            <div style="flex-grow: 1; background: #ffffff; padding: 0; position: relative;">
                <iframe id="campaign_preview_frame" style="width: 100%; height: 100%; border: none; background: #ffffff; min-height: 400px;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
    const templatesMap = {
        <?php foreach ($templates as $temp): ?>
            "<?= e($temp['id']) ?>": {
                subject: <?= json_encode($temp['subject'], JSON_HEX_TAG | JSON_HEX_AMP) ?>,
                html: <?= json_encode($temp['body_html'], JSON_HEX_TAG | JSON_HEX_AMP) ?>,
                text: <?= json_encode($temp['body_text'], JSON_HEX_TAG | JSON_HEX_AMP) ?>
            },
        <?php endforeach; ?>
    };

    function applyTemplatePreset(id) {
        const textarea = document.getElementById("body_html");
        const textTextarea = document.getElementById("body_text");
        const subjectInput = document.getElementById("subject");
        
        if (id && templatesMap[id]) {
            const temp = templatesMap[id];
            textarea.value = temp.html;
            if (window.htmlEditor) {
                window.htmlEditor.setValue(temp.html);
            }
            textTextarea.value = temp.text || "";
            if (temp.subject && subjectInput.value === "") {
                subjectInput.value = temp.subject;
            }
            updatePreview();
        }
    }

    function updatePreview() {
        const iframe = document.getElementById("campaign_preview_frame");
        const textarea = document.getElementById("body_html");
        const indicator = document.getElementById("preview_indicator");
        
        const doc = iframe.contentDocument || iframe.contentWindow.document;
        let html = textarea.value;

        if (html.trim() === "") {
            doc.open();
            doc.write("<div style='font-family:sans-serif; text-align:center; padding:60px; color:#4f5b76;'>Write HTML or select a template preset on the left to preview.</div>");
            doc.close();
            indicator.textContent = "Empty Body";
            indicator.style.backgroundColor = "var(--theme-border)";
            indicator.style.color = "var(--theme-dark-slate)";
            return;
        }

        // Parse mock values
        html = html.replace(/\{\{\s*first_name\s*\}\}|\{\{first_name\}\}/g, "John");
        html = html.replace(/\{\{\s*unsubscribe_url\s*\}\}|\{\{unsubscribe_url\}\}/g, "#");
        html = html.replace(/\{\{\s*app_name\s*\}\}|\{\{app_name\}\}/g, "Merlin Spellcaster");

        doc.open();
        doc.write(html);
        doc.close();
        
        indicator.textContent = "Live Pre-rendering";
        indicator.style.backgroundColor = "var(--success-light)";
        indicator.style.color = "var(--success)";
    }

    document.addEventListener("DOMContentLoaded", function() {
        const textarea = document.getElementById("body_html");
        
        // Initialize CodeMirror
        window.htmlEditor = CodeMirror.fromTextArea(textarea, {
            mode: "htmlmixed",
            lineNumbers: true,
            theme: "default",
            lineWrapping: true
        });

        // Sync CodeMirror to textarea and update live preview
        window.htmlEditor.on('change', function() {
            window.htmlEditor.save(); // saves to textarea
            updatePreview();
        });

        updatePreview();
    });
</script>
