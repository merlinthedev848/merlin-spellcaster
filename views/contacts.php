<?php
declare(strict_types=1);
$currentListId = (int)($_GET['list_id'] ?? 0);
$currentTagId = (int)($_GET['tag_id'] ?? 0);
$q = $_GET['q'] ?? '';
$action = $_GET['action'] ?? '';

$currentSort = $_GET['sort'] ?? 'created_at';
$currentOrder = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

function sortLink(string $field, string $currentSort, string $currentOrder): string {
    $nextOrder = ($currentSort === $field && $currentOrder === 'asc') ? 'desc' : 'asc';
    $params = $_GET;
    $params['sort'] = $field;
    $params['order'] = $nextOrder;
    return '?' . http_build_query($params);
}

function sortCaret(string $field, string $currentSort, string $currentOrder): string {
    if ($currentSort !== $field) return '';
    return $currentOrder === 'asc' ? ' ▲' : ' ▼';
}
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Contacts</h1>
        <p>Manage lists, create CRM records, filter tags, and run CSV imports.</p>
    </div>
    <div style="display: flex; gap: 12px;">
        <a href="?action=add" class="btn btn-primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>Add Contact</a>
        <a href="?action=import" class="btn btn-secondary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>Import CSV</a>
        <a href="?action=new_list" class="btn btn-secondary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>Create List</a>
        <a href="?action=new_tag" class="btn btn-secondary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>Create Tag</a>
    </div>
</div>

<!-- Dynamic Action Cards -->
<?php if ($action === 'new_list'): ?>
    <div class="card" style="margin-bottom: 24px; max-width: 600px;">
        <div class="card-header"><span class="card-title">Create New List</span></div>
        <form method="post" action="?action=create_list">
            <?= Auth::csrfField() ?>
            <div class="form-group">
                <label class="form-label" for="name">List Name</label>
                <input class="form-control" type="text" id="name" name="name" required placeholder="e.g. Weekly Newsletter">
            </div>
            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" placeholder="Notes about this list segment..."></textarea>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="?" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>Save List</button>
            </div>
        </form>
    </div>
<?php elseif ($action === 'new_tag'): ?>
    <div class="card" style="margin-bottom: 24px; max-width: 600px;">
        <div class="card-header"><span class="card-title">Create CRM Tag</span></div>
        <form method="post" action="?action=create_tag">
            <?= Auth::csrfField() ?>
            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label" for="tag_name">Tag Name</label>
                <input class="form-control" type="text" id="tag_name" name="name" required placeholder="e.g. Lead, Customer, VIP">
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="?" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>Create Tag</button>
            </div>
        </form>
    </div>
<?php elseif ($action === 'add'): ?>
    <div class="card" style="margin-bottom: 24px; max-width: 680px;">
        <div class="card-header"><span class="card-title">Quick Add Contact</span></div>
        <form method="post" action="?action=add_contact">
            <?= Auth::csrfField() ?>
            <div class="form-group" style="position: relative;">
                <label class="form-label" for="email">Email Address</label>
                <input class="form-control" type="email" id="email" name="email" required placeholder="name@domain.com" oninput="checkDuplicateEmail(this)" autocomplete="off">
                <div id="email-suggestions-box" style="display:none; position:absolute; top:100%; left:0; width:100%; max-height:200px; overflow-y:auto; background:white; border:1px solid var(--theme-border); border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.1); z-index:999; margin-top:4px;"></div>
                <div id="email-error-msg" style="display:none; font-size:12px; margin-top:4px; font-weight:600;"></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="first_name">First Name</label>
                    <input class="form-control" type="text" id="first_name" name="first_name" placeholder="John">
                </div>
                <div class="form-group">
                    <label class="form-label" for="last_name">Last Name</label>
                    <input class="form-control" type="text" id="last_name" name="last_name" placeholder="Doe">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="list_id">Assign to List</label>
                <select class="form-control" id="list_id" name="list_id">
                    <option value="0">Do not assign to list</option>
                    <?php foreach ($lists as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label">Assign CRM Tags</label>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; padding: 12px; border: 1px solid var(--theme-border); border-radius: 6px; background-color: #fafbfc;">
                    <?php foreach ($tags as $t): ?>
                        <label style="display: inline-flex; align-items: center; gap: 6px; background-color: white; border: 1px solid var(--theme-border); padding: 4px 10px; border-radius: 20px; font-size: 12px; cursor: pointer; font-weight: 500;">
                            <input type="checkbox" name="tags[]" value="<?= $t['id'] ?>" style="accent-color: var(--theme-blurple);">
                            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: <?= e($t['color'] ?? '#635bff') ?>;"></span>
                            <?= e($t['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="?" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>Save Contact →</button>
            </div>
        </form>
    </div>
<?php elseif ($action === 'import'): ?>
    <div class="card" style="margin-bottom: 24px; max-width: 680px;">
        <div class="card-header"><span class="card-title">Import Contacts from CSV</span></div>
        <form method="post" action="?action=import_csv" enctype="multipart/form-data">
            <?= Auth::csrfField() ?>
            <div style="background-color: var(--theme-blurple-light); color: var(--theme-dark-slate); border-radius: 6px; padding: 12px; font-size: 12px; margin-bottom: 16px; border: 1px solid rgba(99,91,255,0.1);">
                <strong>Required CSV Headers:</strong> <code>email</code>. <br>
                <strong>Optional Headers:</strong> <code>first_name</code>, <code>last_name</code>.
            </div>
            <div class="form-group">
                <label class="form-label" for="csv_file">Choose CSV File</label>
                <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="import_list_id">Assign All to List</label>
                <select class="form-control" id="import_list_id" name="list_id" required>
                    <option value="0">Do not assign to list</option>
                    <?php foreach ($lists as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label">Apply CRM Tags to All Imported Contacts</label>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; padding: 12px; border: 1px solid var(--theme-border); border-radius: 6px; background-color: #fafbfc;">
                    <?php foreach ($tags as $t): ?>
                        <label style="display: inline-flex; align-items: center; gap: 6px; background-color: white; border: 1px solid var(--theme-border); padding: 4px 10px; border-radius: 20px; font-size: 12px; cursor: pointer; font-weight: 500;">
                            <input type="checkbox" name="tags[]" value="<?= $t['id'] ?>" style="accent-color: var(--theme-blurple);">
                            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: <?= e($t['color'] ?? '#635bff') ?>;"></span>
                            <?= e($t['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="?" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>Process Import</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Main Layout Split -->
<div class="grid grid-1-3">
    <!-- Left Pane: Segments and Tags -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <div class="card" style="padding: 16px;">
            <div class="card-header" style="margin-bottom: 12px;"><span class="card-title">Segments</span></div>
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <a href="?" class="sidebar-link <?= ($currentListId === 0 && $currentTagId === 0) ? 'active' : '' ?>" style="color: var(--theme-dark); background-color: <?= ($currentListId === 0 && $currentTagId === 0) ? 'var(--theme-blurple-light)' : 'transparent' ?>; justify-content: space-between;">
                    <span>All Contacts</span>
                </a>
                <?php foreach ($lists as $l): ?>
                    <a href="?list_id=<?= $l['id'] ?>" class="sidebar-link <?= ($currentListId === (int)$l['id']) ? 'active' : '' ?>" style="color: var(--theme-dark); background-color: <?= ($currentListId === (int)$l['id']) ? 'var(--theme-blurple-light)' : 'transparent' ?>; justify-content: space-between;">
                        <span><?= e($l['name']) ?></span>
                        <span style="background-color: var(--theme-border); color: var(--theme-dark-slate); font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 10px;"><?= $l['subscriber_count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card" style="padding: 16px;">
            <div class="card-header" style="margin-bottom: 12px;"><span class="card-title">CRM Tags</span></div>
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <?php foreach ($tags as $t): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; border-radius: 6px; background-color: <?= ($currentTagId === (int)$t['id']) ? 'var(--theme-blurple-light)' : 'transparent' ?>;">
                        <a href="?tag_id=<?= $t['id'] ?>" style="display: flex; align-items: center; gap: 8px; text-decoration: none; color: var(--theme-dark); flex-grow: 1; font-weight: 500; font-size: 13px;">
                            <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background-color: <?= e($t['color'] ?? '#635bff') ?>; flex-shrink: 0;"></span>
                            <?= e($t['name']) ?>
                        </a>
                        <form method="post" action="?action=delete_tag&tag_id=<?= $t['id'] ?>" onsubmit="return confirm('Are you sure you want to delete this tag?');" style="display: flex; align-items: center; margin-bottom: 0;">
                            <?= Auth::csrfField() ?>
                            <button type="submit" style="background: none; border: none; cursor: pointer; color: var(--danger); padding: 0 4px; line-height: 1;" title="Delete tag"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Right Pane: CRM Table -->
    <div style="display: flex; flex-direction: column; gap: 16px;">
        <!-- Smart Segments Quick Filter Bar -->
        <?php $currentSegment = $_GET['segment'] ?? ''; ?>
        <div style="display: flex; gap: 8px; font-size: 13px; flex-wrap: wrap;">
            <a href="?<?= http_build_query(array_merge($_GET, ['segment' => ''])) ?>" class="btn <?= $currentSegment === '' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 6px 14px; font-size: 12px; font-weight: 600;">All Contacts</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['segment' => 'active_openers'])) ?>" class="btn <?= $currentSegment === 'active_openers' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 6px 14px; font-size: 12px; font-weight: 600;">🔥 Active Openers (30d)</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['segment' => 'high_intent'])) ?>" class="btn <?= $currentSegment === 'high_intent' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 6px 14px; font-size: 12px; font-weight: 600;">⭐ High Intent (Score 50+)</a>
            <a href="?<?= http_build_query(array_merge($_GET, ['segment' => 'cold_contacts'])) ?>" class="btn <?= $currentSegment === 'cold_contacts' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 6px 14px; font-size: 12px; font-weight: 600;">❄️ Cold Contacts</a>
        </div>

        <div class="card" style="padding: 16px; flex-direction: row; gap: 12px; align-items: center; justify-content: space-between;">
            <form method="get" action="" style="display: flex; gap: 12px; align-items: center; flex-grow: 1; margin-bottom: 0;">
                <?php if ($currentListId > 0): ?>
                    <input type="hidden" name="list_id" value="<?= $currentListId ?>">
                <?php endif; ?>
                <?php if ($currentTagId > 0): ?>
                    <input type="hidden" name="tag_id" value="<?= $currentTagId ?>">
                <?php endif; ?>
                <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Search contacts by email or name..." style="max-width: 320px; margin-bottom: 0;">
                <select name="limit" onchange="this.form.submit()" class="form-control" style="margin-bottom: 0; max-width: 120px; font-size: 13px; padding: 6px 10px; height: auto;" title="Contacts per page">
                    <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25 / page</option>
                    <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50 / page</option>
                    <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100 / page</option>
                    <option value="250" <?= $limit === 250 ? 'selected' : '' ?>>250 / page</option>
                    <option value="500" <?= $limit === 500 ? 'selected' : '' ?>>500 / page</option>
                    <option value="1000" <?= $limit >= 1000 ? 'selected' : '' ?>>All (1000)</option>
                </select>
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if ($q !== '' || $currentListId > 0 || $currentTagId > 0): ?>
                    <a href="?" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </form>
            
            <!-- Bulk Action Delete Button & Tag assignments -->
            <div style="display: flex; gap: 8px; align-items: center;">
                <select id="bulk_tag_id" class="form-control" style="margin-bottom: 0; max-width: 160px; font-size: 13px; padding: 6px 12px; height: auto;">
                    <option value="">-- Apply/Remove Tag --</option>
                    <?php foreach ($tags as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-secondary" onclick="submitBulkTag('add')" style="padding: 6px 12px; font-size: 12px; font-weight: 600;">Add Tag</button>
                <button type="button" class="btn btn-secondary" onclick="submitBulkTag('remove')" style="padding: 6px 12px; font-size: 12px; font-weight: 600;">Remove Tag</button>
                <button type="button" class="btn btn-danger" onclick="submitMassDelete()" style="padding: 6px 12px; font-size: 12px; font-weight: 600;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>Delete</button>
            </div>
        </div>

        <!-- Unified Mass Action Wrapper Form -->
        <form id="mass_delete_form" method="post" action="?action=mass_delete">
            <?= Auth::csrfField() ?>
            <!-- Hidden tag field for tags routing -->
            <input type="hidden" name="bulk_tag_id" id="hidden_bulk_tag_id" value="">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px; padding: 12px 20px; text-align: center;">
                                <input type="checkbox" id="check_all" onclick="toggleSelectAll(this)" style="cursor: pointer; accent-color: var(--theme-blurple);">
                            </th>
                            <th><a href="<?= sortLink('email', $currentSort, $currentOrder) ?>" style="text-decoration: none; color: inherit; font-weight: bold;">Email Address<?= sortCaret('email', $currentSort, $currentOrder) ?></a></th>
                            <th><a href="<?= sortLink('name', $currentSort, $currentOrder) ?>" style="text-decoration: none; color: inherit; font-weight: bold;">Name<?= sortCaret('name', $currentSort, $currentOrder) ?></a></th>
                            <th><a href="<?= sortLink('tag', $currentSort, $currentOrder) ?>" style="text-decoration: none; color: inherit; font-weight: bold;">Tags<?= sortCaret('tag', $currentSort, $currentOrder) ?></a></th>
                            <th><a href="<?= sortLink('status', $currentSort, $currentOrder) ?>" style="text-decoration: none; color: inherit; font-weight: bold;">Status<?= sortCaret('status', $currentSort, $currentOrder) ?></a></th>
                            <th><a href="<?= sortLink('created_at', $currentSort, $currentOrder) ?>" style="text-decoration: none; color: inherit; font-weight: bold;">Joined Date<?= sortCaret('created_at', $currentSort, $currentOrder) ?></a></th>
                            <th style="width: 80px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contacts)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--theme-dark-slate); padding: 40px;">No contacts matching the criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contacts as $c): ?>
                                <tr>
                                    <td style="text-align: center; padding: 16px 20px;">
                                        <input type="checkbox" name="selected_contacts[]" value="<?= $c['id'] ?>" class="contact-checkbox" style="cursor: pointer; accent-color: var(--theme-blurple);" onclick="updateHeaderCheckbox()">
                                    </td>
                                    <?php
                                    $emailColor = 'var(--theme-dark)';
                                    if ($c['status'] === 'bounced') {
                                        $emailColor = '#e53e3e';
                                    } elseif ($c['status'] === 'unsubscribed') {
                                        $emailColor = '#d69e2e';
                                    }
                                    ?>
                                    <td style="font-weight: 600; color: <?= $emailColor ?>;">
                                        <div style="display: inline-flex; align-items: center; gap: 6px; vertical-align: middle;">
                                            <?php if (!empty($c['country_code'])): ?>
                                                <img src="https://flagcdn.com/w20/<?= e(strtolower($c['country_code'])) ?>.png" alt="<?= e($c['country_name']) ?>" style="border-radius: 1px; border: 1px solid var(--theme-border); flex-shrink: 0;" width="16" title="<?= e($c['city']) ? e($c['city']) . ', ' : '' ?><?= e($c['country_name']) ?> (IP: <?= e($c['ip_address']) ?>)">
                                            <?php endif; ?>
                                            <?= e($c['email']) ?>
                                        </div>
                                    </td>
                                    <td><?= e(trim($c['first_name'] . ' ' . $c['last_name'])) !== '' ? e(trim($c['first_name'] . ' ' . $c['last_name'])) : '—' ?></td>
                                    <td>
                                        <div style="display: flex; flex-wrap: wrap; gap: 4px; max-width: 200px;">
                                            <?php foreach ($c['tags'] as $tg): ?>
                                                <a href="?tag_id=<?= $tg['id'] ?>" style="font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 12px; background-color: <?= e($tg['color']) ?>20; color: <?= e($tg['color']) ?>; border: 1px solid <?= e($tg['color']) ?>40; white-space: nowrap; text-decoration: none; cursor: pointer; transition: all 0.15s ease;" title="Filter by <?= e($tg['name']) ?> tag">
                                                    <?= e($tg['name']) ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= e($c['status']) ?>">
                                            <?= e($c['status']) ?>
                                        </span>
                                    </td>
                                    <td style="color: var(--theme-dark-slate);"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                                    <td>
                                        <a href="<?= e(getSetting('app_url')) ?>/contacts/view?id=<?= $c['id'] ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">Profile</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Pagination Footer Bar -->
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-top: 1px solid var(--theme-border); background-color: #fafbfc;">
                    <span style="font-size: 13px; color: var(--theme-dark-slate);">
                        Showing <strong><?= min($offset + 1, $totalContacts) ?></strong> to <strong><?= min($offset + $limit, $totalContacts) ?></strong> of <strong><?= $totalContacts ?></strong> contacts
                    </span>
                    
                    <div style="display: flex; gap: 6px; align-items: center;">
                        <?php
                        $buildUrl = function(int $p) use ($currentListId, $currentTagId, $q, $limit) {
                            $params = ['page' => $p, 'limit' => $limit];
                            if ($currentListId > 0) $params['list_id'] = $currentListId;
                            if ($currentTagId > 0) $params['tag_id'] = $currentTagId;
                            if ($q !== '') $params['q'] = $q;
                            return '?' . http_build_query($params);
                        };
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <a href="<?= $buildUrl(1) ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">« First</a>
                            <a href="<?= $buildUrl($page - 1) ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">‹ Prev</a>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <a href="<?= $buildUrl($i) ?>" class="btn <?= ($i === $page) ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 4px 8px; font-size: 11px; <?= ($i === $page) ? 'background-color: var(--theme-blurple); border-color: var(--theme-blurple); color: white;' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= $buildUrl($page + 1) ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">Next ›</a>
                            <a href="<?= $buildUrl($totalPages) ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">Last »</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div id="floating_bulk_bar" style="display: none; position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background: var(--theme-dark); color: white; padding: 12px 24px; border-radius: 30px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); z-index: 1000; align-items: center; gap: 16px; border: 1px solid rgba(255,255,255,0.1);">
    <span id="selected_count_badge" style="background: var(--theme-blurple); color: white; padding: 4px 10px; border-radius: 12px; font-weight: bold; font-size: 12px;">0 Selected</span>
    <select id="bulk_tag_id_bottom" class="form-control" style="margin-bottom: 0; width: 160px; font-size: 13px; padding: 6px 12px; height: auto; background: #1e293b; color: white; border-color: rgba(255,255,255,0.2);">
        <option value="">-- Apply/Remove Tag --</option>
        <?php foreach ($tags as $t): ?>
            <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="button" class="btn btn-secondary" onclick="submitBulkTagBottom('add')" style="padding: 6px 12px; font-size: 12px; font-weight: 600; border-color: rgba(255,255,255,0.2); color: white; background: transparent;">Add Tag</button>
    <button type="button" class="btn btn-secondary" onclick="submitBulkTagBottom('remove')" style="padding: 6px 12px; font-size: 12px; font-weight: 600; border-color: rgba(255,255,255,0.2); color: white; background: transparent;">Remove Tag</button>
    <button type="button" class="btn btn-danger" onclick="submitMassDelete()" style="padding: 6px 12px; font-size: 12px; font-weight: 600; background: #ef4444; border-color: #ef4444;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>Delete Selected</button>
</div>

<script>
    // Update floating bar status
    function updateFloatingBar() {
        const checked = document.querySelectorAll(".contact-checkbox:checked");
        const bar = document.getElementById("floating_bulk_bar");
        const badge = document.getElementById("selected_count_badge");
        
        if (checked.length > 0) {
            bar.style.display = 'flex';
            badge.innerText = checked.length + " Selected";
        } else {
            bar.style.display = 'none';
        }
    }

    // Master checkbox toggle
    function toggleSelectAll(master) {
        const checkboxes = document.querySelectorAll(".contact-checkbox");
        checkboxes.forEach(cb => cb.checked = master.checked);
        updateFloatingBar();
    }

    // Header checkbox sync
    function updateHeaderCheckbox() {
        const checkAll = document.getElementById("check_all");
        const total = document.querySelectorAll(".contact-checkbox").length;
        const checked = document.querySelectorAll(".contact-checkbox:checked").length;
        
        checkAll.checked = (total > 0 && total === checked);
        updateFloatingBar();
    }

    // Submit mass delete form
    function submitMassDelete() {
        const checked = document.querySelectorAll(".contact-checkbox:checked");
        if (checked.length === 0) {
            alert("Please select at least one contact to delete.");
            return;
        }
        if (confirm("Are you sure you want to permanently delete " + checked.length + " selected contacts? This will clear all their tag assignments and activity logs. This action is irreversible.")) {
            const form = document.getElementById("mass_delete_form");
            form.action = "?action=mass_delete";
            form.submit();
        }
    }

    // Submit bulk tag add or remove (from top bar)
    function submitBulkTag(mode) {
        const checked = document.querySelectorAll(".contact-checkbox:checked");
        const selectTag = document.getElementById("bulk_tag_id");
        
        if (checked.length === 0) {
            alert("Please select at least one contact to apply changes.");
            return;
        }
        
        if (selectTag.value === "") {
            alert("Please select a tag to apply from the dropdown list.");
            return;
        }

        document.getElementById("hidden_bulk_tag_id").value = selectTag.value;
        const form = document.getElementById("mass_delete_form");
        
        if (mode === "add") {
            form.action = "?action=mass_tag_add";
        } else {
            form.action = "?action=mass_tag_remove";
        }
        form.submit();
    }

    // Submit bulk tag add or remove (from floating bottom bar)
    function submitBulkTagBottom(mode) {
        const checked = document.querySelectorAll(".contact-checkbox:checked");
        const selectTag = document.getElementById("bulk_tag_id_bottom");
        
        if (checked.length === 0) {
            alert("Please select at least one contact to apply changes.");
            return;
        }
        
        if (selectTag.value === "") {
            alert("Please select a tag to apply from the dropdown list.");
            return;
        }

        document.getElementById("hidden_bulk_tag_id").value = selectTag.value;
        const form = document.getElementById("mass_delete_form");
        
        if (mode === "add") {
            form.action = "?action=mass_tag_add";
        } else {
            form.action = "?action=mass_tag_remove";
        }
        form.submit();
    }

    let debounceTimer;
    function checkDuplicateEmail(input) {
        clearTimeout(debounceTimer);
        const email = input.value.trim();
        const errorDiv = document.getElementById("email-error-msg");
        const suggestionsDiv = document.getElementById("email-suggestions-box");
        const submitBtn = input.form.querySelector('button[type="submit"]');

        if (!email) {
            errorDiv.style.display = "none";
            suggestionsDiv.style.display = "none";
            if (submitBtn) submitBtn.disabled = false;
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch('<?= e(BASE_PATH) ?>/contacts/check-email?email=' + encodeURIComponent(email))
                .then(r => r.json())
                .then(data => {
                    // 1. Render Autocomplete Suggestions overlay
                    suggestionsDiv.innerHTML = "";
                    if (data.matches && data.matches.length > 0) {
                        suggestionsDiv.style.display = "block";
                        data.matches.forEach(match => {
                            const item = document.createElement("div");
                            item.style.padding = "8px 12px";
                            item.style.cursor = "pointer";
                            item.style.borderBottom = "1px solid var(--theme-border)";
                            item.style.fontSize = "12px";
                            item.style.color = "var(--theme-dark)";
                            item.style.transition = "background 0.2s";
                            item.innerHTML = `<strong style="color: var(--theme-blurple);">${match.email}</strong> ${match.first_name || match.last_name ? `(${match.first_name} ${match.last_name})` : ''}`;
                            
                            item.onmouseover = () => item.style.backgroundColor = "var(--theme-bg)";
                            item.onmouseout = () => item.style.backgroundColor = "transparent";
                            item.onclick = () => {
                                input.value = match.email;
                                checkDuplicateEmail(input);
                                suggestionsDiv.style.display = "none";
                            };
                            suggestionsDiv.appendChild(item);
                        });
                    } else {
                        suggestionsDiv.style.display = "none";
                    }

                    // 2. Render exact duplicate warning status
                    if (data.exists) {
                        errorDiv.style.display = "block";
                        errorDiv.style.color = "var(--danger)";
                        errorDiv.innerText = "⚠️ Contact already exists in your system.";
                        if (submitBtn) submitBtn.disabled = true;
                    } else {
                        errorDiv.style.display = "none";
                        if (submitBtn) submitBtn.disabled = false;
                    }
                })
                .catch(() => {
                    errorDiv.style.display = "none";
                    suggestionsDiv.style.display = "none";
                    if (submitBtn) submitBtn.disabled = false;
                });
        }, 200);
    }

    // Close suggestions box if clicking outside form
    document.addEventListener("click", function(e) {
        const box = document.getElementById("email-suggestions-box");
        const emailInput = document.getElementById("email");
        if (box && e.target !== emailInput && !box.contains(e.target)) {
            box.style.display = "none";
        }
    });
</script>
