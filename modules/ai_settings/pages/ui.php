<?php
declare(strict_types=1);
// UI Page for AI Settings Configuration

$appUrl = rtrim(getSetting('app_url', (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]"), '/');
$schemaUrl = $appUrl . '/api/ai-agent/schema.json';

$db = Database::getConnection();

// Ensure table exists
try {
    $db->exec("
    CREATE TABLE IF NOT EXISTS mod_ai_agent_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_name VARCHAR(100) NOT NULL,
        api_key VARCHAR(64) UNIQUE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {}

// Handle AI Provider Settings Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_provider'])) {
    if (!Auth::checkCsrf()) { flash('error', 'Invalid security token'); sc_redirect($_SERVER['REQUEST_URI']); }
    setSetting('ai_provider', trim($_POST['ai_provider']));
    setSetting('ai_model', trim($_POST['ai_model']));
    setSetting('ai_endpoint', trim($_POST['ai_endpoint']));
    
    $aiKey = trim($_POST['ai_key']);
    if ($aiKey !== '') {
        setSetting('ai_key', $aiKey);
    }
    
    flash('success', "AI Provider settings saved.");
    sc_redirect('/ai-settings');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agent_name'])) {
    if (!Auth::checkCsrf()) { flash('error', 'Invalid security token'); sc_redirect($_SERVER['REQUEST_URI']); }
    
    $name = trim($_POST['agent_name']);
    $key = 'oc_' . bin2hex(random_bytes(24));
    
    if ($name) {
        $db->prepare("INSERT INTO mod_ai_agent_keys (agent_name, api_key) VALUES (?, ?)")->execute([$name, $key]);
        flash('success', "API Key generated for agent: $name");
    }
    sc_redirect('/ai-settings');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!Auth::checkCsrf()) { flash('error', 'Invalid security token'); sc_redirect($_SERVER['REQUEST_URI']); }
    $db->prepare("DELETE FROM mod_ai_agent_keys WHERE id = ?")->execute([(int)$_POST['delete_id']]);
    flash('success', "API Key revoked.");
    sc_redirect('/ai-settings');
}

$keys = $db->query("SELECT * FROM mod_ai_agent_keys ORDER BY id DESC")->fetchAll();
?>

<div class="flex items-center gap-4 mb-8">
  <h1 class="text-2xl font-bold text-white">AI Settings & Agent Gateway</h1>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
  <div class="card p-6 border-t-4 border-fuchsia-500 mt-6">
    <h2 class="text-lg font-bold text-white mb-4">Outbound AI Provider (System-wide)</h2>
    <p class="text-slate-400 text-sm mb-6">Select which AI provider powers AI features across the platform. If you have a local OpenClaw server running, you can connect it here!</p>
    
    <form method="post" >
        <?= Auth::csrfField() ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1.5">AI Provider</label>
                <select name="ai_provider" class="form-input w-full" required>
                    <option value="openclaw" <?= getSetting('ai_provider') === 'openclaw' ? 'selected' : '' ?>>OpenClaw (Self-Hosted)</option>
                    <option value="openai" <?= getSetting('ai_provider') === 'openai' ? 'selected' : '' ?>>OpenAI</option>
                    <option value="deepseek" <?= getSetting('ai_provider') === 'deepseek' ? 'selected' : '' ?>>DeepSeek</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1.5">Model Name</label>
                <input type="text" name="ai_model" value="<?= e(getSetting('ai_model', 'gpt-3.5-turbo')) ?>" class="form-input w-full" placeholder="e.g. gpt-4, local-model" required>
            </div>
        </div>
        <div class="mb-4">
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">API Endpoint URL (v1/chat/completions format)</label>
            <input type="url" name="ai_endpoint" value="<?= e(getSetting('ai_endpoint', 'http://127.0.0.1:8080/v1/chat/completions')) ?>" class="form-input w-full" placeholder="http://192.168.../v1/chat/completions" required>
        </div>
        <div class="mb-6">
            <label class="block text-xs font-semibold text-slate-400 mb-1.5">API Key / Bearer Token</label>
            <input type="password" name="ai_key" value="<?= e(getSetting('ai_key', '')) ?>" class="form-input w-full" placeholder="sk-...">
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%;">Save AI Provider Settings</button>
    </form>
</div>
  <div class="card p-6 border-t-4 border-blue-500">
    <h2 class="text-lg font-bold text-white mb-4">Generate API Key</h2>
    <p class="text-slate-400 text-sm mb-6">Create a secure Bearer token to allow external AI Agents to manage your platform autonomously.</p>
    
    <form method="post">
      <?= Auth::csrfField() ?>
      <div style="display: flex; gap: 8px;">
        <input type="text" name="agent_name" class="form-control flex-1" placeholder="e.g. Sales Agent Alpha" required>
        <button type="submit" class="btn btn-primary">Generate Key</button>
      </div>
    </form>
  </div>
  
  <div class="card p-6 bg-blue-500/5 border border-blue-500/20">
    <h2 class="text-lg font-bold text-blue-400 mb-2">Configure External AI Agents</h2>
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
