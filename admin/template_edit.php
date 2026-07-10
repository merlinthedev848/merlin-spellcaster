<?php
/**
 * admin/template_edit.php — Create/Edit email template
 * PHP 8.5+
 */
declare(strict_types=1);
$id       = (int)($_GET['id'] ?? 0);
$template = null;
$pageTitle = $id ? 'Edit Template' : 'New Template';
require_once __DIR__ . '/../includes/header.php';

if ($id) {
    $st = $db->prepare("SELECT * FROM templates WHERE id=?");
    $st->execute([$id]);
    $template = $st->fetch();
    if (!$template) { flash('error','Template not found.'); sc_redirect('/admin/templates.php'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $subject  = trim($_POST['subject'] ?? '');
    $bodyHtml = $_POST['body_html'] ?? '';
    $bodyText = trim($_POST['body_text'] ?? '');

    if (!$name || !$bodyHtml) { flash('error','Name and HTML body are required.'); }
    else {
        if ($id) {
            $db->prepare("UPDATE templates SET name=?,subject=?,body_html=?,body_text=?,updated_at=NOW() WHERE id=?")->execute([$name,$subject,$bodyHtml,$bodyText,$id]);
        } else {
            $db->prepare("INSERT INTO templates (name,subject,body_html,body_text) VALUES (?,?,?,?)")->execute([$name,$subject,$bodyHtml,$bodyText]);
            $id = (int)$db->lastInsertId();
        }
        flash('success','Template saved!');
        sc_redirect('/admin/template_edit.php?id='.$id);
    }
}

// Starter templates
$starters = [
    'blank' => ['Blank', '<html><body style="font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px"><div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden"><div style="background:#6366f1;padding:32px;text-align:center"><h1 style="color:#fff;margin:0;font-size:24px">Your Newsletter Title</h1></div><div style="padding:32px"><p style="color:#333;line-height:1.7">Hello {{first_name}},</p><p style="color:#333;line-height:1.7">Your email content here…</p></div><div style="background:#f8f9fa;padding:20px;text-align:center;border-top:1px solid #eee"><p style="color:#999;font-size:12px;margin:0">You are receiving this because you subscribed to our newsletter.</p><p style="color:#999;font-size:12px;margin:8px 0 0"><a href="{{unsubscribe_url}}" style="color:#6366f1">Unsubscribe</a></p></div></div></body></html>'],
    'announcement' => ['Announcement', '<html><body style="font-family:\'Segoe UI\',Arial,sans-serif;background:#0f0a2e;margin:0;padding:30px 0"><div style="max-width:580px;margin:0 auto"><div style="background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:16px 16px 0 0;padding:48px 40px;text-align:center"><div style="font-size:48px;margin-bottom:12px">🚀</div><h1 style="color:#fff;font-size:28px;font-weight:800;margin:0 0 8px">Big Announcement!</h1><p style="color:rgba(255,255,255,0.8);margin:0">Something exciting is here</p></div><div style="background:#1a1340;padding:40px;border-radius:0 0 16px 16px"><p style="color:#c4b5fd;line-height:1.8;margin:0 0 20px">Hello {{first_name}},</p><p style="color:#a78bfa;line-height:1.8;margin:0 0 24px">We have some incredible news to share with you…</p><div style="text-align:center;margin:32px 0"><a href="#" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:14px 32px;border-radius:50px;text-decoration:none;font-weight:700;font-size:16px">Learn More →</a></div><hr style="border:none;border-top:1px solid rgba(255,255,255,0.1);margin:32px 0"><p style="color:#6d5acd;font-size:12px;text-align:center;margin:0"><a href="{{unsubscribe_url}}" style="color:#8b5cf6">Unsubscribe</a> · Merlin Spellcaster</p></div></div></body></html>'],
];
?>

<div class="p-6" x-data="{tab:'edit',preview:false}">
  <div class="flex items-center gap-4 mb-6">
    <a href="/admin/templates.php" class="text-slate-400 hover:text-white transition-colors">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    </a>
    <h1 class="text-2xl font-bold text-white"><?= $pageTitle ?></h1>
  </div>

  <!-- Starter buttons (only on new) -->
  <?php if (!$id): ?>
  <div class="mb-4 flex items-center gap-3">
    <span class="text-xs text-slate-500 font-semibold">Load Starter:</span>
    <?php foreach ($starters as $key => [$label, $html]): ?>
    <button type="button" onclick="document.getElementById('bodyHtml').value=<?= json_encode($html) ?>; refreshPreview()" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-400 bg-white/5 hover:text-white hover:bg-indigo-600/30 transition-all"><?= $label ?></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="post" class="grid grid-cols-1 xl:grid-cols-4 gap-5">
    <div class="xl:col-span-3 space-y-4">
      <div class="card p-5 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Template Name *</label>
            <input type="text" name="name" value="<?= e($template['name'] ?? '') ?>" class="form-input-dark w-full" placeholder="Welcome Email" required autofocus>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">Default Subject</label>
            <input type="text" name="subject" value="<?= e($template['subject'] ?? '') ?>" class="form-input-dark w-full" placeholder="Welcome to {{app_name}}!">
          </div>
        </div>
      </div>

      <div class="card p-5 space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider">HTML Body *</h2>
          <div class="flex gap-1">
            <button type="button" @click="preview=false" :class="!preview?'bg-indigo-600 text-white':'text-slate-400 bg-white/5'" class="px-3 py-1 rounded-lg text-xs font-semibold transition-all">✏️ Edit</button>
            <button type="button" @click="preview=true; refreshPreview()" :class="preview?'bg-indigo-600 text-white':'text-slate-400 bg-white/5'" class="px-3 py-1 rounded-lg text-xs font-semibold transition-all">👁 Preview</button>
          </div>
        </div>
        <div x-show="!preview">
          <textarea name="body_html" id="bodyHtml" class="form-input-dark w-full font-mono text-xs" rows="22" placeholder="<!-- Paste full HTML email here -->"><?= e($template['body_html'] ?? '') ?></textarea>
        </div>
        <div x-show="preview" x-cloak class="rounded-xl overflow-hidden border border-white/10" style="height:500px">
          <iframe id="previewFrame" class="w-full h-full bg-white" sandbox="allow-same-origin"></iframe>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">Plain Text</label>
          <textarea name="body_text" class="form-input-dark w-full font-mono text-xs" rows="4" placeholder="Plain text fallback (optional)"><?= e($template['body_text'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <div class="space-y-4">
      <div class="card p-5 space-y-3">
        <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Variables</h2>
        <div class="space-y-1.5">
          <?php foreach (['{{first_name}}'=>'First name','{{last_name}}'=>'Last name','{{email}}'=>'Email','{{unsubscribe_url}}'=>'Unsubscribe URL','{{app_name}}'=>'App name','{{app_url}}'=>'App URL'] as $var=>$desc): ?>
          <div class="flex items-center justify-between group cursor-pointer" onclick="insertVar('<?= $var ?>')">
            <code class="text-xs text-indigo-400 font-mono group-hover:text-indigo-300"><?= e($var) ?></code>
            <span class="text-xs text-slate-600"><?= $desc ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <p class="text-xs text-slate-600">Click a variable to insert it at the cursor position.</p>
      </div>
      <button type="submit" class="btn btn-primary w-full justify-center">💾 Save Template</button>
      <a href="/admin/templates.php" class="btn btn-secondary w-full justify-center">Cancel</a>
    </div>
  </form>
</div>

<style>
.form-input-dark { background:rgba(255,255,255,0.03);border:1px solid rgba(148,163,184,0.1);color:#e2e8f0;border-radius:10px;padding:8px 12px;font-size:14px;transition:border-color 0.2s,box-shadow 0.2s;outline:none;width:100%; }
.form-input-dark:focus { border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,0.12); }
.form-input-dark::placeholder { color:#475569; }
textarea.form-input-dark { resize:vertical; }
</style>
<script>
function refreshPreview() {
  const html = document.getElementById('bodyHtml').value;
  document.getElementById('previewFrame').srcdoc = makePreviewHtml(html);
}
function makePreviewHtml(html) {
  const safe = html
    .replace(/href=(["'])\s*\{\{unsubscribe_url\}\}\s*\1/gi, 'href="#preview-unsubscribe"')
    .replace(/href=(["'])\s*%7B%7Bunsubscribe_url%7D%7D\s*\1/gi, 'href="#preview-unsubscribe"');
  return safe + `
<script>
document.addEventListener('click', function (event) {
  const link = event.target.closest && event.target.closest('a');
  if (link) event.preventDefault();
}, true);
<\/script>`;
}
function insertVar(v) {
  const ta = document.getElementById('bodyHtml');
  const s = ta.selectionStart, e = ta.selectionEnd;
  ta.value = ta.value.slice(0,s) + v + ta.value.slice(e);
  ta.selectionStart = ta.selectionEnd = s + v.length;
  ta.focus();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
