<?php 
require_once __DIR__ . '/config.php';

// Simple Router / Controller Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_module') {
        $stmt = $db->prepare("UPDATE modules SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
    }
    header("Location: index.php");
    exit;
}

// Fetch Metrics
$metrics = $db->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();
$clicks = $db->query("SELECT COUNT(*) FROM campaign_clicks")->fetchColumn();
$campaigns = $db->query("SELECT * FROM campaigns ORDER BY id DESC")->fetchAll();
$modules = $db->query("SELECT * FROM modules")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merlin Spellcaster</title>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    </style>
</head>
<body class="bg-[#0B0F19] text-slate-300 min-h-screen selection:bg-indigo-500/30" x-data="{ tab: 'dashboard' }">

    <nav class="bg-[#0f172a]/80 backdrop-blur-md border-b border-indigo-500/10 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="bg-gradient-to-br from-indigo-500 to-violet-600 w-9 h-9 rounded-xl flex items-center justify-center text-white font-black text-lg shadow-[0_0_15px_rgba(99,102,241,0.4)]">
                    M
                </div>
                <span class="text-xl font-bold tracking-tight text-white">Merlin <span class="text-indigo-400 font-medium">Spellcaster</span></span>
            </div>
            
            <div class="hidden md:flex space-x-1 items-center bg-[#1e293b]/50 p-1 rounded-lg border border-slate-700/50">
                <button @click="tab = 'dashboard'" :class="tab === 'dashboard' ? 'bg-[#0f172a] text-white shadow-sm' : 'text-slate-400 hover:text-white'" class="px-5 py-1.5 rounded-md text-sm font-medium transition-all">Overview</button>
                <button @click="tab = 'campaigns'" :class="tab === 'campaigns' ? 'bg-[#0f172a] text-white shadow-sm' : 'text-slate-400 hover:text-white'" class="px-5 py-1.5 rounded-md text-sm font-medium transition-all">Incantations</button>
                <button @click="tab = 'modules'" :class="tab === 'modules' ? 'bg-[#0f172a] text-white shadow-sm' : 'text-slate-400 hover:text-white'" class="px-5 py-1.5 rounded-md text-sm font-medium transition-all">Spellbook</button>
            </div>

            <button class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2 rounded-lg text-sm font-semibold shadow-[0_0_20px_rgba(79,70,229,0.3)] transition-all">
                Cast Spell
            </button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-8">
        
        <div x-show="tab === 'dashboard'" x-transition.opacity.duration.300ms class="space-y-6" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-[#111827] border border-slate-800/60 p-6 rounded-2xl shadow-xl">
                    <div class="flex items-center justify-between">
                        <div class="text-xs text-slate-400 font-semibold uppercase tracking-widest">Audience</div>
                        <div class="text-emerald-400 bg-emerald-400/10 px-2 py-1 rounded text-xs font-bold">+12%</div>
                    </div>
                    <div class="text-4xl font-bold text-white mt-4"><?= number_format($metrics) ?></div>
                </div>
                
                <div class="bg-[#111827] border border-slate-800/60 p-6 rounded-2xl shadow-xl">
                    <div class="text-xs text-slate-400 font-semibold uppercase tracking-widest">Total Link Clicks</div>
                    <div class="text-4xl font-bold text-indigo-400 mt-4"><?= number_format($clicks) ?></div>
                </div>

                <div class="bg-[#111827] border border-slate-800/60 p-6 rounded-2xl shadow-xl">
                    <div class="text-xs text-slate-400 font-semibold uppercase tracking-widest">MariaDB Status</div>
                    <div class="mt-4 flex items-center text-sm font-medium text-slate-200">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse mr-3 shadow-[0_0_8px_rgba(16,185,129,0.8)]"></span> 
                        Engine Running Optimized
                    </div>
                </div>
            </div>
        </div>

        <div x-show="tab === 'campaigns'" x-transition.opacity.duration.300ms x-cloak>
            <div class="bg-[#111827] border border-slate-800/60 rounded-2xl overflow-hidden shadow-xl">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-[#0f172a] border-b border-slate-800/60 text-slate-400 text-xs font-semibold uppercase tracking-wider">
                            <th class="p-5">Spell Name</th>
                            <th class="p-5">Subject Line</th>
                            <th class="p-5 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50 text-sm">
                        <?php if(empty($campaigns)): ?>
                            <tr><td colspan="3" class="p-8 text-center text-slate-500">No incantations cast yet.</td></tr>
                        <?php else: ?>
                            <?php foreach($campaigns as $camp): ?>
                            <tr class="hover:bg-slate-800/20 transition-colors">
                                <td class="p-5 font-semibold text-white"><?= htmlspecialchars($camp['name']) ?></td>
                                <td class="p-5 text-slate-400"><?= htmlspecialchars($camp['subject']) ?></td>
                                <td class="p-5 text-right">
                                    <span class="px-3 py-1 bg-indigo-500/10 border border-indigo-500/20 rounded-full text-xs text-indigo-400 font-medium">
                                        <?= ucfirst($camp['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="tab === 'modules'" x-transition.opacity.duration.300ms class="grid grid-cols-1 md:grid-cols-2 gap-6" x-cloak>
            <?php foreach($modules as $mod): ?>
            <div class="bg-[#111827] border border-slate-800/60 p-6 rounded-2xl shadow-xl flex flex-col justify-between group hover:border-indigo-500/30 transition-all">
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-white"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $mod['folder_name']))) ?></h3>
                        <p class="text-xs text-slate-500 mt-2 font-mono">/modules/<?= $mod['folder_name'] ?></p>
                    </div>
                    <div class="<?= $mod['is_active'] ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-800 text-slate-500' ?> px-2.5 py-1 rounded text-xs font-bold uppercase tracking-wider">
                        <?= $mod['is_active'] ? 'Active' : 'Dormant' ?>
                    </div>
                </div>
                
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="toggle_module">
                    <input type="hidden" name="id" value="<?= $mod['id'] ?>">
                    <button type="submit" class="w-full py-2.5 rounded-lg font-semibold text-sm transition-all <?= $mod['is_active'] ? 'bg-slate-800/50 hover:bg-slate-800 text-slate-300' : 'bg-indigo-600/10 hover:bg-indigo-600/20 text-indigo-400 border border-indigo-500/20' ?>">
                        <?= $mod['is_active'] ? 'Deactivate Module' : 'Awaken Module' ?>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>