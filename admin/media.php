<?php
declare(strict_types=1);
$pageTitle = 'Media Library';
require_once dirname(__DIR__) . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    if (!isset($_FILES['media']) || !is_uploaded_file($_FILES['media']['tmp_name'])) {
        flash('error', 'Choose a file to upload.');
        sc_redirect('/admin/media.php');
    }

    $file = $_FILES['media'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'application/pdf'];
    $mime = mime_content_type($file['tmp_name']) ?: (string)$file['type'];
    if (!in_array($mime, $allowed, true)) {
        flash('error', 'Unsupported file type.');
        sc_redirect('/admin/media.php');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $safeName = bin2hex(random_bytes(12)) . ($ext ? '.' . preg_replace('/[^a-z0-9]/', '', $ext) : '');
    $target = UPLOAD_DIR . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        flash('error', 'Upload failed. Check uploads/ permissions.');
        sc_redirect('/admin/media.php');
    }

    [$width, $height] = @getimagesize($target) ?: [0, 0];
    $stmt = $db->prepare("INSERT INTO media (filename, original_name, file_path, file_size, mime_type, width, height) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$safeName, (string)$file['name'], UPLOAD_URL . $safeName, (int)$file['size'], $mime, (int)$width, (int)$height]);
    flash('success', 'File uploaded.');
    sc_redirect('/admin/media.php');
}

$files = $db->query("SELECT * FROM media ORDER BY uploaded_at DESC, id DESC LIMIT 100")->fetchAll();
?>

<div class="flex items-center justify-between mb-8">
  <div>
    <h1 class="text-2xl font-bold text-white">Media Library</h1>
    <p class="text-slate-500 text-sm mt-1">Upload images or PDFs for campaigns.</p>
  </div>
</div>

<div class="card p-6 mb-6">
  <form method="post" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-3 items-end">
    <input type="hidden" name="action" value="upload">
    <div class="flex-1 w-full">
      <label class="form-label">File</label>
      <input type="file" name="media" class="form-input" required>
    </div>
    <button class="btn btn-primary" type="submit">Upload</button>
  </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 gap-4">
  <?php foreach ($files as $file): ?>
    <div class="card-sm p-4">
      <?php if (str_starts_with((string)$file['mime_type'], 'image/')): ?>
        <img src="<?= e((string)$file['file_path']) ?>" alt="" class="w-full aspect-video object-cover rounded-lg bg-slate-950 mb-3">
      <?php else: ?>
        <div class="w-full aspect-video rounded-lg bg-slate-950 mb-3 flex items-center justify-center text-slate-500 text-sm">PDF</div>
      <?php endif; ?>
      <div class="text-sm text-slate-200 font-semibold truncate"><?= e((string)$file['original_name']) ?></div>
      <a href="<?= e((string)$file['file_path']) ?>" target="_blank" class="text-xs text-indigo-400 hover:underline break-all"><?= e((string)$file['file_path']) ?></a>
    </div>
  <?php endforeach; ?>
  <?php if (!$files): ?>
    <div class="card p-8 text-center text-slate-500 md:col-span-3 xl:col-span-4">No media uploaded yet.</div>
  <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
