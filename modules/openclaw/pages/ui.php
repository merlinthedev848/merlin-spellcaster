<?php
declare(strict_types=1);
$pageTitle = 'OpenClaw Integrations';
require_once dirname(__DIR__, 3) . '/includes/header.php';

$appUrl = rtrim(getSetting('app_url', (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]"), '/');
$schemaUrl = $appUrl . '/modules/openclaw/api/schema.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agent_name'])) {
    if (!Auth::checkCsrf()) { flash('error', 'Invalid security token'); sc_redirect($_SERVER['REQUEST_URI']); }
    
    $name = trim($_POST['agent_name']);
    $key = 'oc_' . bin2hex(random_bytes(24));
    
    if ($name) {
        $db->prepare("INSERT INTO mod_openclaw_keys (agent_name, api_key) VALUES (?, ?)")->execute([$name, $key]);
        flash('success', "API Key generated for agent: $name");
    }
    sc_redirect('/modules/openclaw/pages/ui.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!Auth::checkCsrf()) { flash('error', 'Invalid security token'); sc_redirect($_SERVER['REQUEST_URI']); }
    $db->prepare("DELETE FROM mod_openclaw_keys WHERE id = ?")->execute([(int)$_POST['delete_id']]);
    flash('success', "API Key revoked.");
    sc_redirect('/modules/openclaw/pages/ui.php');
}

$keys = $db->query("SELECT * FROM mod_openclaw_keys ORDER BY id DESC")->fetchAll();
?>

<div class="flex items-center gap-4 mb-8">
  <h1 class="text-2xl font-bold text-white">OpenClaw AI Agent Gateway</h1>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
  <div class="card p-6 border-t-4 border-blue-500">
    <h2 class="text-lg font-bold text-white mb-4">Generate API Key</h2>
    <p class="text-slate-400 text-sm mb-6">Create a secure Bearer token to allow your OpenClaw agent to manage your campaigns autonomously.</p>
    
    <form method="post">
      <?= Auth::csrfField() ?>
      <div class="flex gap-2">
        <input type="text" name="agent_name" class="form-input flex-1" placeholder="e.g. Sales Agent Alpha" required>
        <button type="submit" class="btn btn-primary bg-blue-600 hover:bg-blue-500">Generate Key</button>
      </div>
    </form>
  </div>
  
  <div class="card p-6 bg-blue-500/5 border border-blue-500/20">
    <h2 class="text-lg font-bold text-blue-400 mb-2">Configure OpenClaw</h2>
    <p class="text-slate-300 text-sm mb-4">
        To connect your agent, feed it the OpenAPI schema URL so it knows what API endpoints are available. It will automatically understand how to read stats and draft campaigns!
    </p>
    
    <label class="block text-xs font-semibold text-slate-400 mb-1.5">OpenAPI Schema URL</label>
    <div class="bg-slate-900 rounded p-3 mb-2 font-mono text-blue-300 text-sm select-all">
        <?= $schemaUrl ?>
    </div>
  </div>
</div>

<div class="card p-0 overflow-hidden">
  <table class="data-table w-full">
    <thead>
      <tr>
        <th>Agent Name</th>
        <th>API Key (Bearer Token)</th>
        <th>Created</th>
        <th>Last Used</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if(!$keys): ?>
      <tr><td colspan="5" class="text-center text-slate-500 py-8">No keys generated.</td></tr>
      <?php endif; ?>
      <?php foreach ($keys as $k): ?>
      <tr>
        <td class="font-semibold text-slate-200"><?= e($k['agent_name']) ?></td>
        <td>
            <code class="text-blue-400 bg-blue-500/10 px-2 py-1 rounded text-xs select-all"><?= e($k['api_key']) ?></code>
        </td>
        <td class="text-xs text-slate-400"><?= $k['created_at'] ?></td>
        <td class="text-xs text-slate-400"><?= $k['last_used_at'] ?: 'Never' ?></td>
        <td>
            <form method="post" onsubmit="return confirm('Revoke this key?');">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="delete_id" value="<?= $k['id'] ?>">
                <button type="submit" class="text-rose-400 hover:text-rose-300 text-sm">Revoke</button>
            </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
