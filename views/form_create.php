<?php
declare(strict_types=1);

$nameVal = $isEdit ? $form['name'] : '';
$listIdVal = $isEdit ? (int)$form['list_id'] : 0;
$headlineVal = $isEdit ? $form['headline'] : 'Subscribe to our newsletter';
$descriptionVal = $isEdit ? $form['description'] : 'Join our mailing list to receive standard updates.';
$buttonTextVal = $isEdit ? $form['button_text'] : 'Subscribe';
$successMessageVal = $isEdit ? $form['success_message'] : 'Thank you for subscribing!';
$redirectUrlVal = $isEdit ? $form['redirect_url'] : '';
$downloadUrlVal = $isEdit ? $form['download_url'] : '';

$showNameVal = $isEdit ? ((int)$form['show_name'] === 1) : true;
$requireNameVal = $isEdit ? ((int)$form['require_name'] === 1) : false;
$doubleOptinVal = $isEdit ? ((int)$form['double_optin'] === 1) : false;
?>

<div class="header-actions">
    <div class="page-title">
        <a href="<?= e(getSetting('app_url')) ?>/forms" style="color: var(--theme-dark-slate); font-weight: 500; font-size: 13px; text-decoration: none; display: flex; align-items: center; gap: 4px; margin-bottom: 8px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Forms
        </a>
        <h1><?= $isEdit ? 'Edit Form' : 'Create Form' ?></h1>
        <p>Define headline branding, set lead magnet delivery assets, and hook double opt-in validation metrics.</p>
    </div>
</div>

<?php if (isset($error)): ?>
    <div style="background-color: var(--danger-light); color: var(--danger); border: 1px solid rgba(255,91,96,0.1); border-radius: 6px; padding: 12px; font-size: 13px; font-weight: 500; margin-bottom: 24px; max-width: 800px;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="grid grid-2">
    <!-- Form Editor Pane -->
    <div class="card" style="display: flex; flex-direction: column; gap: 20px;">
        <div class="card-header" style="border-bottom: 1px solid var(--theme-border); padding-bottom: 16px; margin-bottom: 0;">
            <span class="card-title">Form Parameters</span>
        </div>
        
        <form method="post" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label" for="name">Internal Form Name</label>
                <input class="form-control" type="text" id="name" name="name" value="<?= e($nameVal) ?>" required placeholder="e.g. Lead Ebook Download Form">
            </div>

            <div class="form-group">
                <label class="form-label" for="list_id">Assign Subscribers to Target List</label>
                <select class="form-control" id="list_id" name="list_id">
                    <option value="">— Do Not Assign to List —</option>
                    <?php foreach ($lists as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= ($listIdVal === (int)$l['id']) ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="headline">Display Title / Headline</label>
                <input class="form-control" type="text" id="headline" name="headline" value="<?= e($headlineVal) ?>" oninput="updateMock()" placeholder="e.g. Subscribe to our newsletter">
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Display Description</label>
                <textarea class="form-control" id="description" name="description" oninput="updateMock()" placeholder="Add a short subtitle context..." style="min-height: 80px;"><?= e($descriptionVal) ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="button_text">CTA Button Label</label>
                    <input class="form-control" type="text" id="button_text" name="button_text" value="<?= e($buttonTextVal) ?>" oninput="updateMock()" placeholder="e.g. Subscribe">
                </div>
                <div class="form-group">
                    <label class="form-label" for="success_message">Success Message</label>
                    <input class="form-control" type="text" id="success_message" name="success_message" value="<?= e($successMessageVal) ?>" placeholder="e.g. Thank you for subscribing!">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="redirect_url">Redirect URL on Success (Optional)</label>
                <input class="form-control" type="url" id="redirect_url" name="redirect_url" value="<?= e($redirectUrlVal) ?>" placeholder="e.g. https://yourwebsite.com/thank-you">
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label" for="upload_file">Upload Lead Magnet File (Optional)</label>
                <input class="form-control" type="file" id="upload_file" name="upload_file" accept=".pdf,.doc,.docx,.zip,.png,.jpg,.jpeg">
                <p style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 4px;">Upload a file to serve as a lead magnet. This will automatically set the Download URL below.</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="download_url">Download URL / Lead Magnet File (Optional)</label>
                <input class="form-control" type="url" id="download_url" name="download_url" list="mediaFilesList" value="<?= e($downloadUrlVal) ?>" placeholder="Select a file or enter an external URL">
                <datalist id="mediaFilesList">
                    <?php foreach ($mediaFiles as $file): ?>
                        <option value="<?= e($file['url']) ?>"><?= e($file['name']) ?></option>
                    <?php endforeach; ?>
                </datalist>
                <p style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 4px;">Select a previously uploaded file from the Media Library dropdown, or enter any external URL.</p>
            </div>

            <div class="form-group" style="display: flex; flex-direction: column; gap: 10px; margin: 24px 0 32px;">
                <label style="display: inline-flex; align-items: center; gap: 8px; font-weight: 500; font-size: 13px; cursor: pointer;">
                    <input type="checkbox" id="show_name" name="show_name" value="1" style="accent-color: var(--theme-blurple);" <?= $showNameVal ? 'checked' : '' ?> onchange="updateMock()">
                    Show Name Field (First and Last Name)
                </label>
                
                <label style="display: inline-flex; align-items: center; gap: 8px; font-weight: 500; font-size: 13px; cursor: pointer;">
                    <input type="checkbox" id="require_name" name="require_name" value="1" style="accent-color: var(--theme-blurple);" <?= $requireNameVal ? 'checked' : '' ?> onchange="updateMock()">
                    Require First Name field to submit
                </label>

                <label style="display: inline-flex; align-items: center; gap: 8px; font-weight: 500; font-size: 13px; cursor: pointer;">
                    <input type="checkbox" name="double_optin" value="1" style="accent-color: var(--theme-blurple);" <?= $doubleOptinVal ? 'checked' : '' ?>>
                    Require Double Opt-in confirmation (status starts as pending)
                </label>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="<?= e(getSetting('app_url')) ?>/forms" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" style="font-weight: 600;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>Save Form Configuration →</button>
            </div>
        </form>
    </div>

    <!-- Live Preview Pane -->
    <div style="display: flex; flex-direction: column; height: 100%;">
        <div class="card" style="flex-grow: 1; display: flex; flex-direction: column; min-height: 480px; justify-content: center; align-items: center; background-color: #fafbfc; border: 1px dashed var(--theme-border);">
            <div style="width: 100%; max-width: 400px; background-color: white; border-radius: 12px; padding: 32px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); border: 1px solid var(--theme-border); display: flex; flex-direction: column; text-align: center;">
                <div style="color: var(--theme-blurple); margin-bottom: 16px;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                </div>
                
                <h2 id="mock_headline" style="font-size: 20px; font-weight: 800; color: var(--theme-dark); margin: 0 0 8px; line-height: 1.3;">Subscribe to our newsletter</h2>
                <p id="mock_description" style="color: var(--theme-dark-slate); font-size: 13px; line-height: 1.6; margin: 0 0 24px;">Join our mailing list to receive standard updates.</p>
                
                <div style="display: flex; flex-direction: column; gap: 12px; text-align: left;">
                    <div id="mock_name_fields" style="display: flex; gap: 10px;">
                        <div style="flex: 1;">
                            <label style="font-size: 11px; font-weight: 600; color: var(--theme-dark-slate); text-transform: uppercase; display: block; margin-bottom: 6px;">First Name <span id="mock_first_req" style="color: var(--danger); display: none;">*</span></label>
                            <input class="form-control" type="text" placeholder="John" disabled style="margin-bottom: 0;">
                        </div>
                        <div style="flex: 1;">
                            <label style="font-size: 11px; font-weight: 600; color: var(--theme-dark-slate); text-transform: uppercase; display: block; margin-bottom: 6px;">Last Name</label>
                            <input class="form-control" type="text" placeholder="Doe" disabled style="margin-bottom: 0;">
                        </div>
                    </div>
                    
                    <div>
                        <label style="font-size: 11px; font-weight: 600; color: var(--theme-dark-slate); text-transform: uppercase; display: block; margin-bottom: 6px;">Email Address *</label>
                        <input class="form-control" type="email" placeholder="john@domain.com" disabled style="margin-bottom: 0;">
                    </div>
                    
                    <button type="button" id="mock_button" class="btn btn-primary" style="width: 100%; font-weight: 600; padding: 12px; margin-top: 8px; justify-content: center;" disabled>
                        Subscribe
                    </button>
                </div>
            </div>
            <div style="margin-top: 16px; font-size: 11px; color: var(--theme-dark-slate); font-weight: 500;">Live Workspace Editor Preview</div>
        </div>
    </div>
</div>

<script>
    function updateMock() {
        const headlineInput = document.getElementById("headline");
        const descInput = document.getElementById("description");
        const buttonInput = document.getElementById("button_text");
        
        const showNameCheckbox = document.getElementById("show_name");
        const requireNameCheckbox = document.getElementById("require_name");

        const mockHeadline = document.getElementById("mock_headline");
        const mockDescription = document.getElementById("mock_description");
        const mockButton = document.getElementById("mock_button");
        
        const mockNameFields = document.getElementById("mock_name_fields");
        const mockFirstReq = document.getElementById("mock_first_req");

        mockHeadline.textContent = headlineInput.value.trim() !== "" ? headlineInput.value : "Subscribe to our newsletter";
        mockDescription.textContent = descInput.value.trim() !== "" ? descInput.value : "Join our mailing list to receive standard updates.";
        mockButton.textContent = buttonInput.value.trim() !== "" ? buttonInput.value : "Subscribe";

        if (showNameCheckbox.checked) {
            mockNameFields.style.display = "flex";
        } else {
            mockNameFields.style.display = "none";
        }

        if (requireNameCheckbox.checked) {
            mockFirstReq.style.display = "inline";
        } else {
            mockFirstReq.style.display = "none";
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        updateMock();
    });
</script>
