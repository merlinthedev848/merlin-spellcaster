<?php
/**
 * admin/campaign_create.php — Create/Edit campaign
 * PHP 8.5+
 */
declare(strict_types=1);
$editId   = (int)($_GET['id'] ?? 0);
$campaign = null;
$pageTitle = $editId ? 'Edit Campaign' : 'New Campaign';
require_once __DIR__ . '/../includes/header.php';

if ($editId) {
    $st = $db->prepare("SELECT * FROM campaigns WHERE id = ?");
    $st->execute([$editId]);
    $campaign = $st->fetch();
    if (!$campaign) { flash('error','Campaign not found.'); sc_redirect('/admin/campaigns.php'); }
    $selectedLists = array_column($db->prepare("SELECT list_id FROM campaign_lists WHERE campaign_id=?")->execute([$editId]) ? (function() use ($db, $editId) {
        $s = $db->prepare("SELECT list_id FROM campaign_lists WHERE campaign_id=?");
        $s->execute([$editId]);
        return $s->fetchAll();
    })() : [], 'list_id');
}
$selectedLists ??= [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::checkCsrf()) { flash('error', 'Invalid security token (CSRF). Please try again.'); sc_redirect(<?php
/**
 * admin/campaign_create.php — Create/Edit campaign
 * PHP 8.5+
 */
declare(strict_types=1);
$editId   = (int)($_GET['id'] ?? 0);
$campaign = null;
$pageTitle = $editId ? 'Edit Campaign' : 'New Campaign';
require_once __DIR__ . '/../includes/header.php';

if ($editId) {
    $st = $db->prepare("SELECT * FROM campaigns WHERE id = ?");
    $st->execute([$editId]);
    $campaign = $st->fetch();
    if (!$campaign) { flash('error','Campaign not found.'); sc_redirect('/admin/campaigns.php'); }
    $selectedLists = array_column($db->prepare("SELECT list_id FROM campaign_lists WHERE campaign_id=?")->execute([$editId]) ? (function() use ($db, $editId) {
        $s = $db->prepare("SELECT list_id FROM campaign_lists WHERE campaign_id=?");
        $s->execute([$editId]);
        return $s->fetchAll();
    })() : [], 'list_id');
}
$selectedLists ??= [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $subject    = trim($_POST['subject'] ?? '');
    $fromName   = trim($_POST['from_name'] ?? getSetting('smtp_from_name'));
    $fromEmail  = trim($_POST['from_email'] ?? getSetting('smtp_from_email'));
    $replyTo    = trim($_POST['reply_to'] ?? '');
    $bodyHtml   = $_POST['body_html'] ?? '';
    $bodyText   = trim($_POST['body_text'] ?? '');
    $listIds    = array_map('intval', (array)($_POST['list_ids'] ?? []));
    $schedAt    = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null;
    $tplId      = (int)($_POST['template_id'] ?? 0) ?: null;
    $status     = $schedAt ? 'scheduled' : 'draft';

    $errors = [];
    if (!$name)    $errors[] = 'Campaign name is required.';
    if (!$subject) $errors[] = 'Subject line is required.';
    if (!$bodyHtml && !$bodyText) $errors[] = 'Email body is required.';

    if (empty($errors)) {
        if ($editId) {
            $db->prepare("UPDATE campaigns SET name=?,subject=?,from_name=?,from_email=?,reply_to=?,body_html=?,body_text=?,template_id=?,scheduled_at=?,status=CASE WHEN status IN ('draft','scheduled') THEN ? ELSE status END,updated_at=NOW() WHERE id=?")
               ->execute([$name,$subject,$fromName,$fromEmail,$replyTo,$bodyHtml,$bodyText,$tplId,$schedAt,$status,$editId]);
            $db->prepare("DELETE FROM campaign_lists WHERE campaign_id=?")->execute([$editId]);
            $cid = $editId;
        } else {
            $db->prepare("INSERT INTO campaigns (name,subject,from_name,from_email,reply_to,body_html,body_text,template_id,scheduled_at,status) VALUES (?,?,?,?,?,?,?,?,?,?)")
               ->execute([$name,$subject,$fromName,$fromEmail,$replyTo,$bodyHtml,$bodyText,$tplId,$schedAt,$status]);
            $cid = (int)$db->lastInsertId();
        }
        if ($listIds) {
            $st = $db->prepare("INSERT IGNORE INTO campaign_lists (campaign_id,list_id) VALUES (?,?)");
            foreach ($listIds as $lid) $st->execute([$cid, $lid]);
        }
        logActivity($db, currentUserId(), $editId ? 'updated' : 'created', 'campaign', $cid, $name);
        flash('success', ($editId ? 'Campaign updated.' : 'Campaign created!'));
        sc_redirect('/admin/campaign_view.php?id=' . $cid);
    }
}

$templates = $db->query("SELECT id,name FROM templates ORDER BY name")->fetchAll();
$lists     = $db->query("SELECT * FROM lists ORDER BY name")->fetchAll();
?>

<div class="p-6">
  <div class="flex items-center gap-4 mb-6">
    <a href="/admin/campaigns.php" class="text-slate-400 hover:text-white transition-colors">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    </a>
    <h1 class="text-2xl font-bold text-white"><?= $pageTitle ?></h1>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="mb-4 p-4 rounded-xl text-sm" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);color:#f87171;">
    <?php foreach ($errors as $err): ?><div>⚠️ <?= e($err) ?></div><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="post" class="grid grid-cols-1 xl:grid-cols-3 gap-5" x-data="{preview:false,selectedTemplate:0}" x-init="">

    <!-- Left: Main -->
    <div class="xl:col-span-2 space-y-4">

      <!-- Details Card -->
      <div class="card p-5 space-y-4">
        <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Campaign Details</h2>
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">Campaign Name *</label>
          <input type="text" name="name" value="<?= e($campaign['name'] ?? '') ?>" class="form-input w-full" placeholder="May Newsletter 2025" required>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">Subject Line *</label>
          <input type="text" name="subject" value="<?= e($campaign['subject'] ?? '') ?>" class="form-input w-full" placeholder="🚀 Something amazing this way comes…" required>
          <p class="text-xs text-slate-600 mt-1">Tip: emoji in subject lines boost open rates</p>
          <?php ModuleManager::triggerAction('campaign_form_after_subject'); ?>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">From Name</label>
            <input type="text" name="from_name" value="<?= e($campaign['from_name'] ?? getSetting('smtp_from_name')) ?>" class="form-input w-full" placeholder="Merlin Spellcaster">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">From Email</label>
            <input type="email" name="from_email" value="<?= e($campaign['from_email'] ?? getSetting('smtp_from_email')) ?>" class="form-input w-full" placeholder="hi@example.com">
          </div>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">Reply-To (optional)</label>
          <input type="email" name="reply_to" value="<?= e($campaign['reply_to'] ?? '') ?>" class="form-input w-full" placeholder="Same as From Email">
        </div>
      </div>

      <!-- Email Body -->
      <div class="card p-5 space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Email Body (HTML)</h2>
          <div class="flex gap-1">
            <button type="button" @click="preview=false" :class="!preview?'bg-indigo-600 text-white':'text-slate-400 bg-white/5'" class="px-3 py-1 rounded-lg text-xs font-semibold transition-all">Edit</button>
            <button type="button" @click="preview=true" :class="preview?'bg-indigo-600 text-white':'text-slate-400 bg-white/5'" class="px-3 py-1 rounded-lg text-xs font-semibold transition-all">Preview</button>
          </div>
        </div>
        <div x-show="!preview">
          <textarea name="body_html" id="bodyHtml" class="form-input w-full font-mono text-xs" rows="18" placeholder="<!-- Paste or type your HTML email here -->"><?= e($campaign['body_html'] ?? '') ?></textarea>
          <p class="text-xs text-slate-600 mt-1">Use <code class="text-indigo-400">{{first_name}}</code>, <code class="text-indigo-400">{{email}}</code>, <code class="text-indigo-400">{{unsubscribe_url}}</code> for personalization.</p>
        </div>
        <div x-show="preview" x-cloak class="rounded-lg overflow-hidden border border-white/10" style="height:400px">
          <iframe id="previewFrame" class="w-full h-full bg-white" sandbox="allow-same-origin"></iframe>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">Plain Text Version (auto-generated if empty)</label>
          <textarea name="body_text" class="form-input w-full font-mono text-xs" rows="4" placeholder="Optional plain text fallback…"><?= e($campaign['body_text'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <!-- Right: Sidebar -->
    <div class="space-y-4">

      <!-- Template -->
      <div class="card p-5">
        <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">Load Template</h2>
        <select name="template_id" class="form-input w-full text-sm" @change="loadTemplate($event.target.value)">
          <option value="">— No Template —</option>
          <?php foreach ($templates as $t): ?>
          <option value="<?= $t['id'] ?>" <?= ($campaign['template_id'] ?? 0) == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="text-xs text-slate-600 mt-2">Loading a template will replace the body HTML above.</p>
      </div>

      <!-- Lists -->
      <div class="card p-5">
        <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">Send To Lists</h2>
        <?php if (empty($lists)): ?>
        <p class="text-xs text-slate-500">No lists yet. <a href="/admin/lists.php" class="text-indigo-400">Create one →</a></p>
        <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($lists as $list): ?>
          <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-white/5 transition-colors">
            <input type="checkbox" name="list_ids[]" value="<?= $list['id'] ?>" <?= in_array($list['id'], $selectedLists) ? 'checked' : '' ?> class="accent-indigo-500 w-4 h-4">
            <div>
              <span class="text-sm text-slate-200 font-medium"><?= e($list['name']) ?></span>
              <span class="text-xs text-slate-500 ml-1">(<?= number_format((int)$list['subscriber_count']) ?>)</span>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Schedule -->
      <div class="card p-5">
        <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">Schedule (optional)</h2>
        <input type="datetime-local" name="scheduled_at" value="<?= e(str_replace(' ','T',$campaign['scheduled_at']??'')) ?>" class="form-input w-full text-sm">
        <p class="text-xs text-slate-600 mt-2">Leave empty to save as draft and send manually.</p>
      </div>

      <!-- Actions -->
      <div class="flex flex-col gap-2">
        <button type="submit" class="btn btn-primary w-full justify-center">
          <?= $editId ? '💾 Save Changes' : '✨ Create Campaign' ?>
        </button>
        <a href="/admin/campaigns.php" class="btn btn-secondary w-full justify-center">Cancel</a>
      </div>
    </div>
  </form>
</div>



<script>
document.querySelector('[x-data]').__x?.$watch('preview', v => {
  if (v) {
    const html = document.getElementById('bodyHtml').value;
    const iframe = document.getElementById('previewFrame');
    iframe.srcdoc = makePreviewHtml(html);
  }
});

// Alpine watch workaround
document.addEventListener('alpine:initialized', () => {
  const comp = Alpine.$data(document.querySelector('[x-data]'));
  Alpine.effect(() => {
    if (comp.preview) {
      const html = document.getElementById('bodyHtml').value;
      document.getElementById('previewFrame').srcdoc = makePreviewHtml(html);
    }
  });
});

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

async function loadTemplate(id) {
  if (!id) return;
  const r = await fetch(`/api/index.php?route=template&id=${id}`);
  const j = await r.json();
  if (j.body_html) {
    document.getElementById('bodyHtml').value = j.body_html;
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
SERVER['REQUEST_URI']); }
    $name       = trim($_POST['name'] ?? '');
    $subject    = trim($_POST['subject'] ?? '');
    $fromName   = trim($_POST['from_name'] ?? getSetting('smtp_from_name'));
    $fromEmail  = trim($_POST['from_email'] ?? getSetting('smtp_from_email'));
    $replyTo    = trim($_POST['reply_to'] ?? '');
    $bodyHtml   = $_POST['body_html'] ?? '';
    $bodyText   = trim($_POST['body_text'] ?? '');
    $listIds    = array_map('intval', (array)($_POST['list_ids'] ?? []));
    $schedAt    = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null;
    $tplId      = (int)($_POST['template_id'] ?? 0) ?: null;
    $status     = $schedAt ? 'scheduled' : 'draft';

    $errors = [];
    if (!$name)    $errors[] = 'Campaign name is required.';
    if (!$subject) $errors[] = 'Subject line is required.';
    if (!$bodyHtml && !$bodyText) $errors[] = 'Email body is required.';

    if (empty($errors)) {
        if ($editId) {
            $db->prepare("UPDATE campaigns SET name=?,subject=?,from_name=?,from_email=?,reply_to=?,body_html=?,body_text=?,template_id=?,scheduled_at=?,status=CASE WHEN status IN ('draft','scheduled') THEN ? ELSE status END,updated_at=NOW() WHERE id=?")
               ->execute([$name,$subject,$fromName,$fromEmail,$replyTo,$bodyHtml,$bodyText,$tplId,$schedAt,$status,$editId]);
            $db->prepare("DELETE FROM campaign_lists WHERE campaign_id=?")->execute([$editId]);
            $cid = $editId;
        } else {
            $db->prepare("INSERT INTO campaigns (name,subject,from_name,from_email,reply_to,body_html,body_text,template_id,scheduled_at,status) VALUES (?,?,?,?,?,?,?,?,?,?)")
               ->execute([$name,$subject,$fromName,$fromEmail,$replyTo,$bodyHtml,$bodyText,$tplId,$schedAt,$status]);
            $cid = (int)$db->lastInsertId();
        }
        if ($listIds) {
            $st = $db->prepare("INSERT IGNORE INTO campaign_lists (campaign_id,list_id) VALUES (?,?)");
            foreach ($listIds as $lid) $st->execute([$cid, $lid]);
        }
        logActivity($db, currentUserId(), $editId ? 'updated' : 'created', 'campaign', $cid, $name);
        flash('success', ($editId ? 'Campaign updated.' : 'Campaign created!'));
        sc_redirect('/admin/campaign_view.php?id=' . $cid);
    }
}

$templates = $db->query("SELECT id,name FROM templates ORDER BY name")->fetchAll();
$lists     = $db->query("SELECT * FROM lists ORDER BY name")->fetchAll();
?>

<div class="p-6">
  <div class="flex items-center gap-4 mb-6">
    <a href="/admin/campaigns.php" class="text-slate-400 hover:text-white transition-colors">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    </a>
    <h1 class="text-2xl font-bold text-white"><?= $pageTitle ?></h1>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="mb-4 p-4 rounded-xl text-sm" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);color:#f87171;">
    <?php foreach ($errors as $err): ?><div>⚠️ <?= e($err) ?></div><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="post" class="grid grid-cols-1 xl:grid-cols-3 gap-5" x-data="{preview:false,selectedTemplate:0}" x-init="">

    <!-- Left: Main -->
    <div class="xl:col-span-2 space-y-4">

      <!-- Details Card -->
      <div class="card p-5 space-y-4">
        <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Campaign Details</h2>
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">Campaign Name *</label>
          <input type="text" name="name" value="<?= e($campaign['name'] ?? '') ?>" class="form-input w-full" placeholder="May Newsletter 2025" required>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">Subject Line *</label>
          <input type="text" name="subject" value="<?= e($campaign['subject'] ?? '') ?>" class="form-input w-full" placeholder="🚀 Something amazing this way comes…" required>
          <p class="text-xs text-slate-600 mt-1">Tip: emoji in subject lines boost open rates</p>
          <?php ModuleManager::triggerAction('campaign_form_after_subject'); ?>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">From Name</label>
            <input type="text" name="from_name" value="<?= e($campaign['from_name'] ?? getSetting('smtp_from_name')) ?>" class="form-input w-full" placeholder="Merlin Spellcaster">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">From Email</label>
            <input type="email" name="from_email" value="<?= e($campaign['from_email'] ?? getSetting('smtp_from_email')) ?>" class="form-input w-full" placeholder="hi@example.com">
          </div>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">Reply-To (optional)</label>
          <input type="email" name="reply_to" value="<?= e($campaign['reply_to'] ?? '') ?>" class="form-input w-full" placeholder="Same as From Email">
        </div>
      </div>

      <!-- Email Body -->
      <div class="card p-5 space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Email Body (HTML)</h2>
          <div class="flex gap-1">
            <button type="button" @click="preview=false" :class="!preview?'bg-indigo-600 text-white':'text-slate-400 bg-white/5'" class="px-3 py-1 rounded-lg text-xs font-semibold transition-all">Edit</button>
            <button type="button" @click="preview=true" :class="preview?'bg-indigo-600 text-white':'text-slate-400 bg-white/5'" class="px-3 py-1 rounded-lg text-xs font-semibold transition-all">Preview</button>
          </div>
        </div>
        <div x-show="!preview">
          <textarea name="body_html" id="bodyHtml" class="form-input w-full font-mono text-xs" rows="18" placeholder="<!-- Paste or type your HTML email here -->"><?= e($campaign['body_html'] ?? '') ?></textarea>
          <p class="text-xs text-slate-600 mt-1">Use <code class="text-indigo-400">{{first_name}}</code>, <code class="text-indigo-400">{{email}}</code>, <code class="text-indigo-400">{{unsubscribe_url}}</code> for personalization.</p>
        </div>
        <div x-show="preview" x-cloak class="rounded-lg overflow-hidden border border-white/10" style="height:400px">
          <iframe id="previewFrame" class="w-full h-full bg-white" sandbox="allow-same-origin"></iframe>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-400 mb-1.5">Plain Text Version (auto-generated if empty)</label>
          <textarea name="body_text" class="form-input w-full font-mono text-xs" rows="4" placeholder="Optional plain text fallback…"><?= e($campaign['body_text'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <!-- Right: Sidebar -->
    <div class="space-y-4">

      <!-- Template -->
      <div class="card p-5">
        <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">Load Template</h2>
        <select name="template_id" class="form-input w-full text-sm" @change="loadTemplate($event.target.value)">
          <option value="">— No Template —</option>
          <?php foreach ($templates as $t): ?>
          <option value="<?= $t['id'] ?>" <?= ($campaign['template_id'] ?? 0) == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="text-xs text-slate-600 mt-2">Loading a template will replace the body HTML above.</p>
      </div>

      <!-- Lists -->
      <div class="card p-5">
        <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">Send To Lists</h2>
        <?php if (empty($lists)): ?>
        <p class="text-xs text-slate-500">No lists yet. <a href="/admin/lists.php" class="text-indigo-400">Create one →</a></p>
        <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($lists as $list): ?>
          <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-white/5 transition-colors">
            <input type="checkbox" name="list_ids[]" value="<?= $list['id'] ?>" <?= in_array($list['id'], $selectedLists) ? 'checked' : '' ?> class="accent-indigo-500 w-4 h-4">
            <div>
              <span class="text-sm text-slate-200 font-medium"><?= e($list['name']) ?></span>
              <span class="text-xs text-slate-500 ml-1">(<?= number_format((int)$list['subscriber_count']) ?>)</span>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Schedule -->
      <div class="card p-5">
        <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-3">Schedule (optional)</h2>
        <input type="datetime-local" name="scheduled_at" value="<?= e(str_replace(' ','T',$campaign['scheduled_at']??'')) ?>" class="form-input w-full text-sm">
        <p class="text-xs text-slate-600 mt-2">Leave empty to save as draft and send manually.</p>
      </div>

      <!-- Actions -->
      <div class="flex flex-col gap-2">
        <button type="submit" class="btn btn-primary w-full justify-center">
          <?= $editId ? '💾 Save Changes' : '✨ Create Campaign' ?>
        </button>
        <a href="/admin/campaigns.php" class="btn btn-secondary w-full justify-center">Cancel</a>
      </div>
    </div>
  </form>
</div>



<script>
document.querySelector('[x-data]').__x?.$watch('preview', v => {
  if (v) {
    const html = document.getElementById('bodyHtml').value;
    const iframe = document.getElementById('previewFrame');
    iframe.srcdoc = makePreviewHtml(html);
  }
});

// Alpine watch workaround
document.addEventListener('alpine:initialized', () => {
  const comp = Alpine.$data(document.querySelector('[x-data]'));
  Alpine.effect(() => {
    if (comp.preview) {
      const html = document.getElementById('bodyHtml').value;
      document.getElementById('previewFrame').srcdoc = makePreviewHtml(html);
    }
  });
});

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

async function loadTemplate(id) {
  if (!id) return;
  const r = await fetch(`/api/index.php?route=template&id=${id}`);
  const j = await r.json();
  if (j.body_html) {
    document.getElementById('bodyHtml').value = j.body_html;
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
