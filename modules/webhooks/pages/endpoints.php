<?php
declare(strict_types=1);
$pageTitle = 'Webhook Endpoints';
require_once dirname(__DIR__, 3) . '/includes/header.php';

$baseUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$apiUrl = $baseUrl . '/modules/webhooks/api/incoming.php';

$lists = $db->query("SELECT id, name FROM lists ORDER BY name")->fetchAll();
?>

<div class="flex items-center gap-4 mb-8">
  <h1 class="text-2xl font-bold text-white">Webhook Integrations</h1>
</div>

<div class="card p-6 mb-8 border-t-4 border-indigo-500">
  <h2 class="text-xl font-bold text-white mb-2">Zapier / Custom API Endpoint</h2>
  <p class="text-slate-400 mb-6 text-sm">
    You can automatically push subscribers into your lists from external applications by sending a POST request to the URL below.
  </p>
  
  <div class="bg-slate-900/50 p-4 rounded-xl border border-slate-700/50 mb-6 flex items-center justify-between">
    <code class="text-indigo-400 font-mono text-sm"><?= $apiUrl ?></code>
  </div>

  <h3 class="font-bold text-slate-200 mb-3 text-sm uppercase tracking-wider">Required Payload (JSON or Form-Data)</h3>
  <table class="data-table w-full text-sm mb-6">
    <thead>
      <tr>
        <th>Field</th>
        <th>Type</th>
        <th>Description</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="font-mono text-indigo-300">email</td>
        <td>String (Required)</td>
        <td>The subscriber's email address.</td>
      </tr>
      <tr>
        <td class="font-mono text-indigo-300">list_id</td>
        <td>Integer (Required)</td>
        <td>The ID of the list to add them to.</td>
      </tr>
      <tr>
        <td class="font-mono text-indigo-300">first_name</td>
        <td>String (Optional)</td>
        <td>The subscriber's first name.</td>
      </tr>
      <tr>
        <td class="font-mono text-indigo-300">last_name</td>
        <td>String (Optional)</td>
        <td>The subscriber's last name.</td>
      </tr>
    </tbody>
  </table>

  <h3 class="font-bold text-slate-200 mb-3 text-sm uppercase tracking-wider">Your List IDs</h3>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <?php foreach ($lists as $l): ?>
      <div class="bg-slate-800/50 p-3 rounded-lg border border-slate-700/50 flex justify-between items-center">
        <span class="text-sm font-semibold text-slate-300 truncate" title="<?= e($l['name']) ?>"><?= e($l['name']) ?></span>
        <span class="text-indigo-400 font-mono bg-indigo-500/10 px-2 py-1 rounded text-xs">ID: <?= $l['id'] ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
