<?php
/**
 * admin/forms.php — Subscription form builder
 * PHP 8.5+
 */
declare(strict_types=1);
$pageTitle = 'Subscription Forms';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if ($action === 'save') {
        $data = [
            'name'            => trim($_POST['name'] ?? ''),
            'list_id'         => (int)($_POST['list_id'] ?? 0) ?: null,
            'headline'        => trim($_POST['headline'] ?? ''),
            'description'     => trim($_POST['description'] ?? ''),
            'button_text'     => trim($_POST['button_text'] ?? 'Subscribe'),
            'success_message' => trim($_POST['success_message'] ?? ''),
            'redirect_url'    => trim($_POST['redirect_url'] ?? ''),
            'show_name'       => isset($_POST['show_name']) ? 1 : 0,
            'require_name'    => isset($_POST['require_name']) ? 1 : 0,
            'double_optin'    => isset($_POST['double_optin']) ? 1 : 0,
        ];
        if (!$data['name']) { flash('error','Form name is required.'); sc_redirect('/admin/forms.php'); }
        if ($id) {
            $db->prepare("UPDATE forms SET name=?,list_id=?,headline=?,description=?,button_text=?,success_message=?,redirect_url=?,show_name=?,require_name=?,double_optin=? WHERE id=?")
               ->execute([$data['name'],$data['list_id'],$data['headline'],$data['description'],$data['button_text'],$data['success_message'],$data['redirect_url'],$data['show_name'],$data['require_name'],$data['double_optin'],$id]);
        } else {
            $db->prepare("INSERT INTO forms (name,list_id,headline,description,button_text,success_message,redirect_url,show_name,require_name,double_optin) VALUES (?,?,?,?,?,?,?,?,?,?)")
               ->execute([$data['name'],$data['list_id'],$data['headline'],$data['description'],$data['button_text'],$data['success_message'],$data['redirect_url'],$data['show_name'],$data['require_name'],$data['double_optin']]);
        }
        flash('success','Form saved!');
    }
    if ($action === 'delete' && $id) {
        $db->prepare("DELETE FROM forms WHERE id=?")->execute([$id]);
        flash('success','Form deleted.');
    }
    sc_redirect('/admin/forms.php');
}

$forms = $db->query("SELECT f.*,l.name as list_name FROM forms f LEFT JOIN lists l ON l.id=f.list_id ORDER BY f.created_at DESC")->fetchAll();
$lists = $db->query("SELECT id,name FROM lists ORDER BY name")->fetchAll();
$editForm = null;
if ($editId = (int)($_GET['edit'] ?? 0)) {
    $st = $db->prepare("SELECT * FROM forms WHERE id=?"); $st->execute([$editId]); $editForm = $st->fetch();
}
$appUrl = getSetting('app_url');
?>

<div class="p-6 space-y-5">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-white">Subscription Forms</h1>
    <button onclick="document.getElementById('formModal').showModal()" class="btn btn-primary">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      New Form
    </button>
  </div>

  <?php if (empty($forms)): ?>
  <div class="card text-center py-16">
    <div class="text-5xl mb-4">📋</div>
    <h3 class="text-white font-bold mb-2">No forms yet</h3>
    <p class="text-slate-400 text-sm mb-6">Create embeddable subscription forms for your website.</p>
    <button onclick="document.getElementById('formModal').showModal()" class="btn btn-primary">Create Form</button>
  </div>
  <?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($forms as $form): ?>
    <div class="card p-5 space-y-4 group" x-data="{showEmbed:false}">
      <div class="flex items-start justify-between">
        <div>
          <h3 class="font-bold text-white"><?= e($form['name']) ?></h3>
          <p class="text-xs text-slate-500 mt-0.5">List: <?= e($form['list_name'] ?? 'No list') ?></p>
        </div>
        <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
          <a href="?edit=<?= $form['id'] ?>" class="p-1.5 rounded-lg hover:bg-white/10 text-slate-400 hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
          </a>
          <form method="post" onsubmit="return confirm('Delete form?')" style="display:inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $form['id'] ?>">
            <button class="p-1.5 rounded-lg hover:bg-red-500/20 text-slate-400 hover:text-red-400 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </form>
        </div>
      </div>

      <div class="text-xs text-slate-400 space-y-1">
        <div class="flex justify-between"><span>Headline:</span><span class="text-slate-200"><?= e(mb_strimwidth($form['headline'],0,30,'…')) ?></span></div>
        <div class="flex justify-between"><span>Show Name:</span><span><?= $form['show_name']?'Yes':'No' ?></span></div>
        <div class="flex justify-between"><span>Double Opt-in:</span><span><?= $form['double_optin']?'Yes':'No' ?></span></div>
      </div>

      <button @click="showEmbed=!showEmbed" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold">
        <span x-text="showEmbed?'Hide':'Show'"></span> Embed Code
      </button>
      <div x-show="showEmbed" x-cloak class="space-y-2">
        <p class="text-xs text-slate-500 font-semibold">JavaScript Embed:</p>
        <textarea readonly class="text-xs bg-slate-900 border border-white/10 rounded-lg px-2 py-2 w-full text-slate-400 font-mono" rows="3" onclick="this.select()"><?= e('<script src="'.$appUrl.'/public/form.js" data-form="'.$form['id'].'"></script>') ?></textarea>
        <p class="text-xs text-slate-500 font-semibold">Direct URL:</p>
        <input type="text" readonly value="<?= e($appUrl.'/subscribe.php?form='.$form['id']) ?>" class="text-xs bg-slate-900 border border-white/10 rounded-lg px-2 py-1.5 w-full text-slate-400 focus:outline-none" onclick="this.select()">
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Create/Edit Modal -->
<dialog id="formModal" class="rounded-2xl border-0 p-0 shadow-2xl w-full max-w-lg" style="background:#111827">
  <form method="post" class="p-6 space-y-4">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editForm['id'] ?? 0 ?>">
    <h2 class="text-lg font-bold text-white"><?= $editForm ? 'Edit Form' : 'Create Form' ?></h2>
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Form Name *</label>
        <input type="text" name="name" value="<?= e($editForm['name'] ?? '') ?>" class="form-input w-full" required>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Add to List</label>
        <select name="list_id" class="form-input w-full">
          <option value="">— None —</option>
          <?php foreach ($lists as $l): ?><option value="<?= $l['id'] ?>" <?= ($editForm['list_id']??0)==$l['id']?'selected':'' ?>><?= e($l['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">Headline</label>
      <input type="text" name="headline" value="<?= e($editForm['headline'] ?? 'Subscribe to our newsletter') ?>" class="form-input w-full">
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">Description</label>
      <textarea name="description" class="form-input w-full" rows="2"><?= e($editForm['description'] ?? '') ?></textarea>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Button Text</label>
        <input type="text" name="button_text" value="<?= e($editForm['button_text'] ?? 'Subscribe') ?>" class="form-input w-full">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Success Message</label>
        <input type="text" name="success_message" value="<?= e($editForm['success_message'] ?? 'Thank you for subscribing!') ?>" class="form-input w-full">
      </div>
    </div>
    <div>
      <label class="block text-xs font-semibold text-slate-400 mb-1.5">Redirect URL after subscribe (optional)</label>
      <input type="url" name="redirect_url" value="<?= e($editForm['redirect_url'] ?? '') ?>" class="form-input w-full" placeholder="https://example.com/thank-you">
    </div>
    <div class="flex gap-4">
      <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="show_name" <?= ($editForm['show_name']??1)?'checked':'' ?> class="accent-indigo-500 w-4 h-4"><span class="text-sm text-slate-300">Show Name</span></label>
      <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="require_name" <?= ($editForm['require_name']??0)?'checked':'' ?> class="accent-indigo-500 w-4 h-4"><span class="text-sm text-slate-300">Require Name</span></label>
      <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="double_optin" <?= ($editForm['double_optin']??0)?'checked':'' ?> class="accent-indigo-500 w-4 h-4"><span class="text-sm text-slate-300">Double Opt-in</span></label>
    </div>
    <div class="flex gap-2 pt-2">
      <button type="submit" class="btn btn-primary flex-1 justify-center">Save Form</button>
      <button type="button" onclick="document.getElementById('formModal').close()" class="btn btn-secondary flex-1 justify-center">Cancel</button>
    </div>
  </form>
</dialog>



<?php
// Auto-open modal on edit
if ($editForm): ?>
<script>document.getElementById('formModal').showModal();</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
