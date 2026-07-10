<?php
$pageTitle = 'Subscribers';
require_once __DIR__ . '/../includes/header.php';

// ── POST Processing ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::checkCsrf()) {
        flash('error', 'Invalid CSRF token');
        sc_redirect('/admin/subscribers.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $email     = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $listId    = (int)($_POST['list_id'] ?? 0);
        $status    = in_array($_POST['status'] ?? '', ['active','unsubscribed','bounced','complained']) ? $_POST['status'] : 'active';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Invalid email address.');
        } else {
            $exists = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
            $exists->execute([$email]);
            if ($exists->fetch()) {
                flash('error', 'A subscriber with that email already exists.');
            } else {
                $ins = $db->prepare("INSERT INTO subscribers (email, first_name, last_name, phone, status, source, created_at) VALUES (?, ?, ?, ?, ?, 'manual', NOW())");
                $ins->execute([$email, $firstName, $lastName, $phone, $status]);
                $subId = (int)$db->lastInsertId();
                if ($listId && $subId) {
                    $db->prepare("INSERT IGNORE INTO subscriber_lists (subscriber_id, list_id, status, subscribed_at) VALUES (?, ?, 'confirmed', NOW())")->execute([$subId, $listId]);
                    updateListCounts($db);
                }
                logActivity($db, currentUserId(), 'create', 'subscriber', $subId, "Added subscriber {$email}");
                flash('success', 'Subscriber added successfully.');
            }
        }
        sc_redirect('/admin/subscribers.php');

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $sub = $db->prepare("SELECT email FROM subscribers WHERE id = ?");
            $sub->execute([$id]);
            $row = $sub->fetch();
            $db->prepare("DELETE FROM subscriber_lists WHERE subscriber_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM subscribers WHERE id = ?")->execute([$id]);
            updateListCounts($db);
            logActivity($db, currentUserId(), 'delete', 'subscriber', $id, "Deleted subscriber " . ($row['email'] ?? ''));
            flash('success', 'Subscriber deleted.');
        }
        sc_redirect('/admin/subscribers.php');

    } elseif ($action === 'bulk_delete') {
        $raw = $_POST['ids'] ?? '';
        $ids = array_filter(array_map('intval', explode(',', $raw)));
        if (!empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $db->prepare("DELETE FROM subscriber_lists WHERE subscriber_id IN ({$ph})")->execute($ids);
            $db->prepare("DELETE FROM subscribers WHERE id IN ({$ph})")->execute($ids);
            updateListCounts($db);
            logActivity($db, currentUserId(), 'bulk_delete', 'subscriber', null, "Bulk deleted " . count($ids) . " subscribers");
            flash('success', count($ids) . ' subscriber(s) deleted.');
        }
        sc_redirect('/admin/subscribers.php');

    } elseif ($action === 'bulk_status') {
        $raw    = $_POST['ids'] ?? '';
        $ids    = array_filter(array_map('intval', explode(',', $raw)));
        $status = in_array($_POST['status'] ?? '', ['active','unsubscribed','bounced','complained']) ? $_POST['status'] : 'active';
        if (!empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $params = $ids;
            $params[] = $status;
            // Re-order: status at front for SET clause
            $updateParams = array_merge([$status], $ids);
            $db->prepare("UPDATE subscribers SET status = ? WHERE id IN ({$ph})")->execute($updateParams);
            flash('success', count($ids) . ' subscriber(s) updated to ' . $status . '.');
        }
        sc_redirect('/admin/subscribers.php');
    }
}

// ── CSV Export ──────────────────────────────────────────────────────────────────
if (($_GET['action'] ?? '') === 'export') {
    $search       = trim($_GET['search'] ?? '');
    $statusFilter = $_GET['status'] ?? '';
    $listFilter   = (int)($_GET['list'] ?? 0);

    $where  = ['1=1'];
    $params = [];
    if ($search) {
        $where[]  = '(s.email LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)';
        $params   = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
    }
    if ($statusFilter) {
        $where[]  = 's.status = ?';
        $params[] = $statusFilter;
    }
    $whereStr = implode(' AND ', $where);

    if ($listFilter) {
        $stmt = $db->prepare("SELECT s.* FROM subscribers s JOIN subscriber_lists sl ON s.id = sl.subscriber_id WHERE sl.list_id = ? AND {$whereStr} ORDER BY s.created_at DESC");
        $stmt->execute(array_merge([$listFilter], $params));
    } else {
        $stmt = $db->prepare("SELECT s.* FROM subscribers s WHERE {$whereStr} ORDER BY s.created_at DESC");
        $stmt->execute($params);
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="subscribers-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Email', 'First Name', 'Last Name', 'Phone', 'Status', 'Source', 'Tags', 'Created At']);
    while ($row = $stmt->fetch()) {
        $tags = '';
        if (!empty($row['tags'])) {
            $arr  = json_decode($row['tags'], true);
            $tags = is_array($arr) ? implode(', ', $arr) : $row['tags'];
        }
        fputcsv($out, [
            $row['id'],
            $row['email'],
            $row['first_name'],
            $row['last_name'],
            $row['phone'],
            $row['status'],
            $row['source'],
            $tags,
            $row['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// ── Filters & Query ─────────────────────────────────────────────────────────────
$search       = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$listFilter   = (int)($_GET['list'] ?? 0);
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;
$offset       = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = '(s.email LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)';
    $params   = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
}
if ($statusFilter) {
    $where[]  = 's.status = ?';
    $params[] = $statusFilter;
}
$whereStr = implode(' AND ', $where);

if ($listFilter) {
    $total = $db->prepare("SELECT COUNT(DISTINCT s.id) FROM subscribers s JOIN subscriber_lists sl ON s.id = sl.subscriber_id WHERE sl.list_id = ? AND {$whereStr}");
    $total->execute(array_merge([$listFilter], $params));
    $totalCount = (int)$total->fetchColumn();

    $stmt = $db->prepare("SELECT s.* FROM subscribers s JOIN subscriber_lists sl ON s.id = sl.subscriber_id WHERE sl.list_id = ? AND {$whereStr} ORDER BY s.created_at DESC LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute(array_merge([$listFilter], $params));
} else {
    $total = $db->prepare("SELECT COUNT(*) FROM subscribers s WHERE {$whereStr}");
    $total->execute($params);
    $totalCount = (int)$total->fetchColumn();

    $stmt = $db->prepare("SELECT s.* FROM subscribers s WHERE {$whereStr} ORDER BY s.created_at DESC LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);
}
$subscribers = $stmt->fetchAll();
$totalPages  = max(1, (int)ceil($totalCount / $perPage));
$allLists    = $db->query("SELECT * FROM lists ORDER BY name")->fetchAll();

// ── Build export URL ────────────────────────────────────────────────────────────
$exportParams = http_build_query(array_filter([
    'action' => 'export',
    'search' => $search,
    'status' => $statusFilter,
    'list'   => $listFilter ?: null,
]));

function statusBadge(string $status): string {
    $map = [
        'active'        => 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30',
        'unsubscribed'  => 'bg-slate-700/50 text-slate-400 ring-1 ring-slate-600/30',
        'bounced'       => 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30',
        'complained'    => 'bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30',
    ];
    $cls = $map[$status] ?? 'bg-slate-700/50 text-slate-400';
    return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ' . $cls . '">' . e(ucfirst($status)) . '</span>';
}
?>

<!-- ── Page wrapper ─────────────────────────────────────────────────────────── -->
<div x-data="{
    selected: [],
    selectAll: false,
    addModal: false,
    deleteModal: false,
    deleteId: null,
    bulkStatus: 'active',
    toggleAll(rows) {
        if (this.selectAll) {
            this.selected = rows;
        } else {
            this.selected = [];
        }
    },
    toggleItem(id) {
        if (this.selected.includes(id)) {
            this.selected = this.selected.filter(i => i !== id);
        } else {
            this.selected.push(id);
        }
    },
    confirmDelete(id) {
        this.deleteId = id;
        this.deleteModal = true;
    }
}" x-cloak>

<!-- Flash Message -->
<?php if ($flash = getFlash()): ?>
<div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-3
    <?= $flash['type'] === 'success' ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400'
      : ($flash['type'] === 'error'   ? 'bg-red-500/10 border border-red-500/30 text-red-400'
      : 'bg-amber-500/10 border border-amber-500/30 text-amber-400') ?>">
    <?php if ($flash['type'] === 'success'): ?>
    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    <?php else: ?>
    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <?php endif; ?>
    <?= e($flash['message']) ?>
</div>
<?php endif; ?>

<!-- ── Header bar ──────────────────────────────────────────────────────────── -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-indigo-600/20 flex items-center justify-center">
            <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <div>
            <h1 class="text-xl font-bold text-slate-100">Subscribers</h1>
            <p class="text-xs text-slate-500">Manage your contact database</p>
        </div>
        <span class="ml-2 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-600/20 text-indigo-300 ring-1 ring-indigo-500/30">
            <?= number_format($totalCount) ?> total
        </span>
    </div>
    <div class="flex items-center gap-2">
        <a href="/admin/imports.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium border border-slate-700/60 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Import
        </a>
        <a href="?<?= $exportParams ?>" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium border border-slate-700/60 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Export CSV
        </a>
        <button @click="addModal = true" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold shadow-lg shadow-indigo-900/40 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Subscriber
        </button>
    </div>
</div>

<!-- ── Filter bar ──────────────────────────────────────────────────────────── -->
<form method="GET" class="flex flex-wrap gap-3 mb-5">
    <div class="relative flex-1 min-w-[220px]">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by email or name…"
            class="w-full pl-9 pr-4 py-2 rounded-lg bg-[#111827] border border-slate-700/60 text-slate-200 placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500/50">
    </div>
    <select name="status" class="px-3 py-2 rounded-lg bg-[#111827] border border-slate-700/60 text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
        <option value="">All Statuses</option>
        <option value="active"       <?= $statusFilter === 'active'       ? 'selected' : '' ?>>Active</option>
        <option value="unsubscribed" <?= $statusFilter === 'unsubscribed' ? 'selected' : '' ?>>Unsubscribed</option>
        <option value="bounced"      <?= $statusFilter === 'bounced'      ? 'selected' : '' ?>>Bounced</option>
        <option value="complained"   <?= $statusFilter === 'complained'   ? 'selected' : '' ?>>Complained</option>
    </select>
    <select name="list" class="px-3 py-2 rounded-lg bg-[#111827] border border-slate-700/60 text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
        <option value="">All Lists</option>
        <?php foreach ($allLists as $list): ?>
        <option value="<?= $list['id'] ?>" <?= $listFilter === (int)$list['id'] ? 'selected' : '' ?>><?= e($list['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm font-medium border border-slate-600/60 transition-colors">Filter</button>
    <?php if ($search || $statusFilter || $listFilter): ?>
    <a href="/admin/subscribers.php" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 text-sm border border-slate-700/60 transition-colors">Clear</a>
    <?php endif; ?>
    <div class="ml-auto self-center text-sm text-slate-500">
        <?php
        $from = $totalCount ? $offset + 1 : 0;
        $to   = min($offset + $perPage, $totalCount);
        echo "Showing {$from}–{$to} of " . number_format($totalCount);
        ?>
    </div>
</form>

<!-- ── Bulk Actions Bar ────────────────────────────────────────────────────── -->
<div x-show="selected.length > 0" x-transition class="flex items-center gap-3 mb-4 px-4 py-3 rounded-xl bg-indigo-600/10 border border-indigo-500/30">
    <span class="text-sm font-medium text-indigo-300"><span x-text="selected.length"></span> selected</span>
    <div class="flex-1"></div>
    <select x-model="bulkStatus" class="px-3 py-1.5 rounded-lg bg-[#111827] border border-slate-700/60 text-slate-300 text-sm">
        <option value="active">Set Active</option>
        <option value="unsubscribed">Set Unsubscribed</option>
        <option value="bounced">Set Bounced</option>
        <option value="complained">Set Complained</option>
    </select>
    <form method="POST" @submit.prevent="if(selected.length){$el.querySelector('[name=ids]').value=selected.join(','); $el.submit()}">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="bulk_status">
        <input type="hidden" name="ids" value="">
        <input type="hidden" name="status" :value="bulkStatus">
        <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm font-medium border border-slate-600/60 transition-colors">
            Update Status
        </button>
    </form>
    <form method="POST" @submit.prevent="if(selected.length && confirm('Delete ' + selected.length + ' subscriber(s)? This cannot be undone.')){$el.querySelector('[name=ids]').value=selected.join(','); $el.submit()}">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="bulk_delete">
        <input type="hidden" name="ids" value="">
        <button type="submit" class="px-3 py-1.5 rounded-lg bg-red-600/20 hover:bg-red-600/30 text-red-400 text-sm font-medium border border-red-500/30 transition-colors">
            Delete Selected
        </button>
    </form>
</div>

<!-- ── Subscribers Table ───────────────────────────────────────────────────── -->
<div class="bg-[#111827] border border-slate-800/60 rounded-2xl overflow-hidden">
    <?php if (empty($subscribers)): ?>
    <div class="flex flex-col items-center justify-center py-20 text-center">
        <div class="w-16 h-16 rounded-2xl bg-slate-800 flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <p class="text-slate-400 font-medium mb-1">No subscribers found</p>
        <p class="text-slate-600 text-sm mb-5">
            <?= ($search || $statusFilter || $listFilter) ? 'Try adjusting your filters.' : 'Get started by adding your first subscriber.' ?>
        </p>
        <?php if (!$search && !$statusFilter && !$listFilter): ?>
        <button @click="addModal = true" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add First Subscriber
        </button>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-800/60">
                <th class="w-10 px-4 py-3">
                    <input type="checkbox" x-model="selectAll"
                        @change="toggleAll([<?= implode(',', array_column($subscribers, 'id')) ?>])"
                        class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Subscriber</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider hidden md:table-cell">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider hidden lg:table-cell">Tags</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider hidden xl:table-cell">Source</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider hidden lg:table-cell">Added</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/40">
            <?php foreach ($subscribers as $sub): ?>
            <?php
                $initials = strtoupper(substr($sub['first_name'] ?? $sub['email'], 0, 1) . substr($sub['last_name'] ?? '', 0, 1));
                if (strlen($initials) < 1) $initials = strtoupper(substr($sub['email'], 0, 2));
                $tagsArr = [];
                if (!empty($sub['tags'])) {
                    $decoded = json_decode($sub['tags'], true);
                    $tagsArr = is_array($decoded) ? $decoded : [];
                }
                $shownTags = array_slice($tagsArr, 0, 3);
                $extraTags = count($tagsArr) - count($shownTags);
                $gradients = ['from-indigo-500 to-violet-600','from-cyan-500 to-blue-600','from-emerald-500 to-teal-600','from-pink-500 to-rose-600','from-amber-500 to-orange-600'];
                $grad = $gradients[crc32($sub['email']) % count($gradients)];
            ?>
            <tr class="hover:bg-slate-800/20 transition-colors group">
                <td class="px-4 py-3">
                    <input type="checkbox" :checked="selected.includes(<?= $sub['id'] ?>)"
                        @change="toggleItem(<?= $sub['id'] ?>)"
                        class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br <?= $grad ?> flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                            <?= e($initials) ?>
                        </div>
                        <div>
                            <a href="/admin/subscriber_view.php?id=<?= $sub['id'] ?>" class="font-medium text-slate-200 hover:text-indigo-400 transition-colors">
                                <?= e(trim(($sub['first_name'] ?? '') . ' ' . ($sub['last_name'] ?? ''))) ?: '<span class="text-slate-500 italic">No name</span>' ?>
                            </a>
                            <p class="text-xs text-slate-500"><?= e($sub['email']) ?></p>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 hidden md:table-cell">
                    <?= statusBadge($sub['status'] ?? 'active') ?>
                </td>
                <td class="px-4 py-3 hidden lg:table-cell">
                    <div class="flex flex-wrap gap-1">
                        <?php foreach ($shownTags as $tag): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-slate-700/60 text-slate-400 ring-1 ring-slate-600/30"><?= e($tag) ?></span>
                        <?php endforeach; ?>
                        <?php if ($extraTags > 0): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-slate-800 text-slate-500">+<?= $extraTags ?></span>
                        <?php endif; ?>
                        <?php if (empty($tagsArr)): ?><span class="text-slate-600 text-xs">—</span><?php endif; ?>
                    </div>
                </td>
                <td class="px-4 py-3 hidden xl:table-cell text-slate-500 text-xs capitalize">
                    <?= e($sub['source'] ?? '—') ?>
                </td>
                <td class="px-4 py-3 hidden lg:table-cell">
                    <span class="text-slate-500 text-xs" title="<?= e($sub['created_at'] ?? '') ?>">
                        <?= !empty($sub['created_at']) ? timeAgo($sub['created_at']) : '—' ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-right">
                    <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <a href="/admin/subscriber_view.php?id=<?= $sub['id'] ?>"
                            class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-400 hover:bg-indigo-600/10 transition-colors"
                            title="View">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </a>
                        <a href="/admin/subscriber_view.php?id=<?= $sub['id'] ?>&edit=1"
                            class="p-1.5 rounded-lg text-slate-500 hover:text-cyan-400 hover:bg-cyan-600/10 transition-colors"
                            title="Edit">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                        <button @click="confirmDelete(<?= $sub['id'] ?>)"
                            class="p-1.5 rounded-lg text-slate-500 hover:text-red-400 hover:bg-red-600/10 transition-colors"
                            title="Delete">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- ── Pagination ──────────────────────────────────────────────────────────── -->
<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-between mt-5">
    <p class="text-sm text-slate-500">
        Page <?= $page ?> of <?= $totalPages ?>
    </p>
    <div class="flex items-center gap-1">
        <?php
        $baseUrl = '/admin/subscribers.php?' . http_build_query(array_filter([
            'search' => $search,
            'status' => $statusFilter,
            'list'   => $listFilter ?: null,
        ]));
        ?>
        <?php if ($page > 1): ?>
        <a href="<?= $baseUrl ?>&page=<?= $page - 1 ?>" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm border border-slate-700/60 transition-colors">← Prev</a>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        for ($p = $start; $p <= $end; $p++):
        ?>
        <a href="<?= $baseUrl ?>&page=<?= $p ?>"
            class="px-3 py-1.5 rounded-lg text-sm border transition-colors
            <?= $p === $page ? 'bg-indigo-600 text-white border-indigo-500' : 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700/60' ?>">
            <?= $p ?>
        </a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="<?= $baseUrl ?>&page=<?= $page + 1 ?>" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm border border-slate-700/60 transition-colors">Next →</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- Add Subscriber Modal                                                        -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div x-show="addModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="addModal = false"></div>
    <div class="relative w-full max-w-lg bg-[#111827] border border-slate-700/60 rounded-2xl shadow-2xl p-6" @click.stop>
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-lg font-bold text-slate-100">Add Subscriber</h2>
                <p class="text-xs text-slate-500 mt-0.5">Manually add a contact to your list</p>
            </div>
            <button @click="addModal = false" class="p-2 rounded-lg text-slate-500 hover:text-slate-300 hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5">Email Address <span class="text-red-400">*</span></label>
                    <input type="email" name="email" required placeholder="user@example.com"
                        class="w-full px-3 py-2 rounded-lg bg-[#0B0F19] border border-slate-700/60 text-slate-200 placeholder-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">First Name</label>
                        <input type="text" name="first_name" placeholder="Jane"
                            class="w-full px-3 py-2 rounded-lg bg-[#0B0F19] border border-slate-700/60 text-slate-200 placeholder-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Last Name</label>
                        <input type="text" name="last_name" placeholder="Doe"
                            class="w-full px-3 py-2 rounded-lg bg-[#0B0F19] border border-slate-700/60 text-slate-200 placeholder-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5">Phone</label>
                    <input type="tel" name="phone" placeholder="+1 555 000 0000"
                        class="w-full px-3 py-2 rounded-lg bg-[#0B0F19] border border-slate-700/60 text-slate-200 placeholder-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Add to List</label>
                        <select name="list_id" class="w-full px-3 py-2 rounded-lg bg-[#0B0F19] border border-slate-700/60 text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                            <option value="">— None —</option>
                            <?php foreach ($allLists as $list): ?>
                            <option value="<?= $list['id'] ?>" <?= $listFilter === (int)$list['id'] ? 'selected' : '' ?>><?= e($list['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Status</label>
                        <select name="status" class="w-full px-3 py-2 rounded-lg bg-[#0B0F19] border border-slate-700/60 text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                            <option value="active">Active</option>
                            <option value="unsubscribed">Unsubscribed</option>
                            <option value="bounced">Bounced</option>
                            <option value="complained">Complained</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-6 pt-5 border-t border-slate-800">
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold shadow-lg shadow-indigo-900/40 transition-colors">
                    Add Subscriber
                </button>
                <button type="button" @click="addModal = false" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium border border-slate-700/60 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Delete Confirm Modal ────────────────────────────────────────────────── -->
<div x-show="deleteModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="deleteModal = false"></div>
    <div class="relative w-full max-w-sm bg-[#111827] border border-slate-700/60 rounded-2xl shadow-2xl p-6 text-center" @click.stop>
        <div class="w-14 h-14 rounded-full bg-red-500/10 flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <h3 class="text-base font-bold text-slate-100 mb-1">Delete Subscriber?</h3>
        <p class="text-sm text-slate-400 mb-6">This will permanently delete this subscriber and all their associated data.</p>
        <form method="POST">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" :value="deleteId">
            <div class="flex gap-3">
                <button type="button" @click="deleteModal = false" class="flex-1 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium border border-slate-700/60 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-red-600 hover:bg-red-500 text-white text-sm font-semibold transition-colors">
                    Delete
                </button>
            </div>
        </form>
    </div>
</div>

</div><!-- end x-data -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
