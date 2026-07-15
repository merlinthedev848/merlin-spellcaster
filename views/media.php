<?php declare(strict_types=1); ?>
<div class="header-actions">
    <div class="page-title">
        <h1>Media Library</h1>
        <p>Upload and manage PDFs, ZIPs, and images for your forms and email campaigns.</p>
    </div>
    
    <button type="button" class="btn btn-primary" onclick="document.getElementById('upload-modal').style.display='block'">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
        Upload File
    </button>
</div>

<div class="card">
    <?php if (empty($files)): ?>
        <form id="main-media-upload-form" method="post" action="<?= e(getSetting('app_url')) ?>/media?action=upload" enctype="multipart/form-data" style="padding: 40px;">
            <?= Auth::csrfField() ?>
            <div id="main-drop-zone" style="border: 2px dashed var(--theme-border); border-radius: 8px; padding: 60px 20px; text-align: center; transition: all 0.2s ease;">
                <label style="display: block; font-size: 16px; font-weight: 600; color: var(--theme-dark-slate); margin-bottom: 12px; cursor: pointer;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--theme-border)" stroke-width="1.5" style="margin-bottom: 16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg><br>
                    No files uploaded yet.<br><br>Drag & Drop a File Here<br>or click to select
                    <input type="file" id="main-file-input" name="file" required style="display: none;">
                </label>
                <div id="main-file-name-display" style="font-size: 14px; color: var(--theme-blurple); font-weight: 500; margin-top: 16px;"></div>
                <button type="submit" id="main-upload-btn" class="btn btn-primary" style="margin-top: 24px; padding: 10px 24px; font-size: 14px; display: none; margin-left: auto; margin-right: auto; justify-content: center;">Upload File</button>
            </div>
        </form>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
                <tr style="border-bottom: 1px solid var(--theme-border);">
                    <th style="padding: 12px 8px; text-align: left; color: var(--theme-dark-slate);">File Name</th>
                    <th style="padding: 12px 8px; text-align: left; color: var(--theme-dark-slate);">Size</th>
                    <th style="padding: 12px 8px; text-align: left; color: var(--theme-dark-slate);">Uploaded</th>
                    <th style="padding: 12px 8px; text-align: right; color: var(--theme-dark-slate);">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                <tr style="border-bottom: 1px solid var(--theme-border);">
                    <td style="padding: 12px 8px; font-weight: 500;">
                        <a href="<?= e($file['url']) ?>" target="_blank" style="color: var(--theme-blurple); text-decoration: none;"><?= e($file['name']) ?></a>
                    </td>
                    <td style="padding: 12px 8px; color: var(--theme-dark-slate);"><?= round($file['size'] / 1024, 1) ?> KB</td>
                    <td style="padding: 12px 8px; color: var(--theme-dark-slate);"><?= date('M j, Y H:i', $file['time']) ?></td>
                    <td style="padding: 12px 8px; text-align: right;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="copyToClipboard('<?= e($file['url']) ?>')" style="margin-right: 4px;">Copy Link</button>
                        <form method="post" action="<?= e(getSetting('app_url')) ?>/media?action=delete" style="display:inline;" onsubmit="return confirm('Delete this file permanently?');">
                            <?= Auth::csrfField() ?>
                            <input type="hidden" name="filename" value="<?= e($file['name']) ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Upload Modal -->
<div id="upload-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 24px; border-radius: 8px; width: 400px; box-shadow: var(--theme-shadow);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 600;">Upload File</h3>
            <button type="button" onclick="document.getElementById('upload-modal').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--theme-dark-slate);">&times;</button>
        </div>
        
        <form id="media-upload-form" method="post" action="<?= e(getSetting('app_url')) ?>/media?action=upload" enctype="multipart/form-data">
            <?= Auth::csrfField() ?>
            <div id="drop-zone" style="margin-bottom: 16px; border: 2px dashed var(--theme-border); border-radius: 8px; padding: 30px 20px; text-align: center; transition: all 0.2s ease;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--theme-dark-slate); margin-bottom: 12px; cursor: pointer;">
                    Drag & Drop File Here<br>or click to select
                    <input type="file" id="file-input" name="file" required style="display: none;">
                </label>
                <div id="file-name-display" style="font-size: 12px; color: var(--theme-blurple); font-weight: 500;"></div>
            </div>
            <button type="submit" id="upload-btn" class="btn btn-primary" style="width: 100%; justify-content: center; display: none;">Upload File</button>
        </form>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert("Link copied to clipboard!");
    });
}

// Drag and Drop Logic generalized
function setupDropZone(dropZoneId, fileInputId, fileNameDisplayId, uploadBtnId) {
    const dropZone = document.getElementById(dropZoneId);
    const fileInput = document.getElementById(fileInputId);
    const fileNameDisplay = document.getElementById(fileNameDisplayId);
    const uploadBtn = document.getElementById(uploadBtnId);

    if (!dropZone) return;

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--theme-blurple)';
        dropZone.style.backgroundColor = 'var(--theme-bg)';
    });

    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--theme-border)';
        dropZone.style.backgroundColor = 'transparent';
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--theme-border)';
        dropZone.style.backgroundColor = 'transparent';
        
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleFileSelect();
        }
    });

    fileInput.addEventListener('change', handleFileSelect);

    function handleFileSelect() {
        if (fileInput.files.length > 0) {
            fileNameDisplay.innerHTML = "Selected: <strong>" + fileInput.files[0].name + "</strong>";
            uploadBtn.style.display = 'inline-flex';
        } else {
            fileNameDisplay.textContent = "";
            uploadBtn.style.display = 'none';
        }
    }
}

setupDropZone('drop-zone', 'file-input', 'file-name-display', 'upload-btn');
setupDropZone('main-drop-zone', 'main-file-input', 'main-file-name-display', 'main-upload-btn');
</script>
