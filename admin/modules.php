<?php
declare(strict_types=1);
$pageTitle = 'Modules';
require_once dirname(__DIR__) . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $active = (int)($_POST['is_active'] ?? 0);
    $stmt = $db->prepare("UPDATE modules SET is_active=? WHERE id=?");
    $stmt->execute([$active, $id]);
    flash('success', 'Module updated.');
    sc_redirect('/admin/modules.php');
}

$modules = $db->query("SELECT * FROM modules ORDER BY folder_name")->fetchAll();
?>

<div class="flex items-center justify-between mb-8">
  <div>
    <h1 class="text-2xl font-bold text-white">Modules</h1>
    <p class="text-slate-500 text-sm mt-1">Enable optional integrations.</p>
  </div>
</div>

<div class="card overflow-hidden">
  <table class="data-table">
    <thead>
      <tr>
        <th>Module</th>
        <th>Description</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($modules as $module): ?>
        <tr>
          <td class="font-semibold text-slate-100"><?= e((string)$module['folder_name']) ?></td>
          <td><?= e((string)($module['description'] ?? '')) ?></td>
          <td>
            <span class="badge <?= (int)$module['is_active'] ? 'badge-active' : 'badge-draft' ?>">
              <?= (int)$module['is_active'] ? 'Active' : 'Off' ?>
            </span>
          </td>
          <td class="text-right">
            <form method="post">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$module['id'] ?>">
              <input type="hidden" name="is_active" value="<?= (int)$module['is_active'] ? 0 : 1 ?>">
              <button class="btn btn-secondary btn-sm" type="submit"><?= (int)$module['is_active'] ? 'Disable' : 'Enable' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$modules): ?>
        <tr><td colspan="4" class="text-center text-slate-500 py-8">No modules are registered.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
