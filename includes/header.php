<?php
/**
 * includes/header.php — Shared admin layout: session, auth, HTML head, sidebar
 * Included at the TOP of every admin page (after setting $pageTitle).
 * PHP 7.4+ compatible.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('APP_ROOT')) require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/core/Auth.php';
Auth::requireLogin();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Auth::checkCsrf()) {
    flash('error', 'Your session token expired. Please try again.');
    $refererPath = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?: '/admin/dashboard.php';
    sc_redirect(sc_safe_redirect_path($refererPath));
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
ModuleManager::triggerAction('admin_init', $currentPage);
$appName     = getSetting('app_name', 'Merlin Spellcaster');
$flash       = getFlash();
$user        = Auth::currentUser();
$csrfToken   = Auth::csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Dashboard') ?> — <?= e($appName) ?></title>
<meta name="robots" content="noindex,nofollow">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        brand: { DEFAULT: '#6366f1', dark: '#4f46e5', light: '#818cf8' }
      }
    }
  }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<meta name="csrf-token" content="<?= e($csrfToken) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; }
  html, body { height: 100%; }
  body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
  [x-cloak] { display: none !important; }

  /* Scrollbar */
  ::-webkit-scrollbar { width: 5px; height: 5px; }
  ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }

  /* Sidebar link */
  .nav-link {
    display: flex; align-items: center; gap: 10px; padding: 8px 12px;
    border-radius: 10px; font-size: 13.5px; font-weight: 500;
    color: #94a3b8; text-decoration: none;
    transition: all 0.15s ease;
  }
  .nav-link:hover { background: rgba(255,255,255,0.05); color: #e2e8f0; }
  .nav-link.active {
    background: rgba(99,102,241,0.15);
    color: #a5b4fc;
    border: 1px solid rgba(99,102,241,0.25);
    box-shadow: 0 0 12px rgba(99,102,241,0.1);
  }
  .nav-section-label {
    font-size: 10px; font-weight: 700; letter-spacing: 0.1em;
    text-transform: uppercase; color: #475569; padding: 4px 12px;
    margin-top: 16px; margin-bottom: 4px;
  }

  /* Cards */
  .card { background: #111827; border: 1px solid rgba(148,163,184,0.08); border-radius: 16px; }
  .card-sm { background: #111827; border: 1px solid rgba(148,163,184,0.08); border-radius: 12px; }

  /* Buttons */
  .btn { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; font-size: 13.5px; border-radius: 8px; padding: 8px 16px; transition: all 0.15s; cursor: pointer; border: none; text-decoration: none; }
  .btn-primary   { background: #6366f1; color: #fff; box-shadow: 0 0 20px rgba(99,102,241,0.25); }
  .btn-primary:hover   { background: #4f46e5; box-shadow: 0 0 28px rgba(99,102,241,0.4); }
  .btn-secondary { background: #1e293b; color: #cbd5e1; border: 1px solid rgba(148,163,184,0.1); }
  .btn-secondary:hover { background: #334155; color: #e2e8f0; }
  .btn-danger    { background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2); }
  .btn-danger:hover    { background: rgba(239,68,68,0.2); }
  .btn-success   { background: rgba(16,185,129,0.1); color: #34d399; border: 1px solid rgba(16,185,129,0.2); }
  .btn-success:hover   { background: rgba(16,185,129,0.2); }
  .btn-sm { padding: 5px 12px; font-size: 12.5px; }
  .btn-lg { padding: 12px 24px; font-size: 15px; }

  /* Form inputs */
  .form-input {
    background: #0f172a; border: 1px solid rgba(148,163,184,0.15); border-radius: 8px;
    padding: 9px 14px; color: #e2e8f0; font-size: 14px; width: 100%;
    transition: border-color 0.15s, box-shadow 0.15s;
    font-family: inherit;
  }
  .form-input:focus { outline: none; border-color: rgba(99,102,241,0.5); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
  .form-input::placeholder { color: #475569; }
  select.form-input { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; background-size: 16px; padding-right: 36px; }
  textarea.form-input { resize: vertical; min-height: 100px; }
  .form-label { display: block; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; margin-bottom: 6px; }
  .form-group { margin-bottom: 20px; }
  .form-hint { font-size: 12px; color: #475569; margin-top: 5px; }

  /* Badges */
  .badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 99px; font-size: 11.5px; font-weight: 600; }
  .badge-active    { background: rgba(16,185,129,0.1);  color: #34d399; border: 1px solid rgba(16,185,129,0.2); }
  .badge-draft     { background: rgba(148,163,184,0.08); color: #64748b; border: 1px solid rgba(148,163,184,0.15); }
  .badge-sent      { background: rgba(99,102,241,0.1);  color: #a5b4fc; border: 1px solid rgba(99,102,241,0.2); }
  .badge-sending   { background: rgba(6,182,212,0.1);   color: #22d3ee; border: 1px solid rgba(6,182,212,0.2); }
  .badge-scheduled { background: rgba(245,158,11,0.1);  color: #fbbf24; border: 1px solid rgba(245,158,11,0.2); }
  .badge-paused    { background: rgba(245,158,11,0.1);  color: #fbbf24; border: 1px solid rgba(245,158,11,0.2); }
  .badge-cancelled { background: rgba(239,68,68,0.1);   color: #f87171; border: 1px solid rgba(239,68,68,0.2); }
  .badge-bounced   { background: rgba(239,68,68,0.1);   color: #f87171; border: 1px solid rgba(239,68,68,0.2); }
  .badge-public    { background: rgba(16,185,129,0.1);  color: #34d399; border: 1px solid rgba(16,185,129,0.2); }
  .badge-private   { background: rgba(148,163,184,0.08); color: #64748b; border: 1px solid rgba(148,163,184,0.15); }

  /* Tables */
  .data-table { width: 100%; border-collapse: collapse; }
  .data-table thead th { padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #475569; border-bottom: 1px solid rgba(148,163,184,0.08); }
  .data-table tbody td { padding: 14px 16px; font-size: 13.5px; color: #cbd5e1; border-bottom: 1px solid rgba(148,163,184,0.05); vertical-align: middle; }
  .data-table tbody tr:last-child td { border-bottom: none; }
  .data-table tbody tr:hover td { background: rgba(255,255,255,0.02); }

  /* Stat cards */
  .stat-card { position: relative; overflow: hidden; }
  .stat-card::before { content: ''; position: absolute; top: -50%; right: -20%; width: 160px; height: 160px; border-radius: 50%; opacity: 0.05; }

  /* Animations */
  @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
  .fade-in { animation: fadeIn 0.3s ease forwards; }
  @keyframes pulse-glow { 0%, 100% { box-shadow: 0 0 8px rgba(16,185,129,0.4); } 50% { box-shadow: 0 0 16px rgba(16,185,129,0.8); } }
  .pulse-green { animation: pulse-glow 2s ease-in-out infinite; }
</style>
<?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body class="bg-[#0B0F19] text-slate-300 flex h-full" x-data="{ sidebarOpen: true }">

<!-- ═══ SIDEBAR ═══════════════════════════════════════════════════════════════ -->
<aside class="w-60 min-h-screen bg-[#0f172a] border-r border-slate-800/40 flex flex-col fixed left-0 top-0 z-40 transition-all duration-300">

  <!-- Logo -->
  <div class="px-5 py-5 border-b border-slate-800/40">
    <a href="/admin/dashboard.php" class="flex items-center gap-3 group">
      <div class="relative">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white font-black text-lg shadow-lg shadow-indigo-500/30 group-hover:shadow-indigo-500/50 transition-shadow">
          M
        </div>
        <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-500 border-2 border-[#0f172a] pulse-green"></div>
      </div>
      <div>
        <div class="text-white font-bold text-sm leading-none">Merlin</div>
        <div class="text-indigo-400 text-xs font-medium mt-0.5">Spellcaster v<?= APP_VERSION ?></div>
      </div>
    </a>
  </div>

  <!-- Navigation -->
  <nav class="flex-1 px-3 py-3 overflow-y-auto space-y-0.5">

    <div class="nav-section-label">Overview</div>
    <a href="/admin/dashboard.php"  class="nav-link <?= $currentPage === 'dashboard'  ? 'active' : '' ?>">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Dashboard
    </a>
    <a href="/admin/analytics.php"  class="nav-link <?= $currentPage === 'analytics'  ? 'active' : '' ?>">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
      Analytics
    </a>

    <div class="nav-section-label">Audience</div>
    <a href="/admin/subscribers.php" class="nav-link <?= $currentPage === 'subscribers' ? 'active' : '' ?>">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Subscribers
    </a>
    <a href="/admin/segments.php"   class="nav-link <?= $currentPage === 'segments'   ? 'active' : '' ?>">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
      Segments
    </a>
    <a href="/admin/lists.php"      class="nav-link <?= $currentPage === 'lists'      ? 'active' : '' ?>">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
      Lists
    </a>
    <a href="/admin/imports.php"    class="nav-link <?= $currentPage === 'imports'    ? 'active' : '' ?>">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
      Imports
    </a>

    <div class="nav-section-label">Campaigns</div>
    <a href="/admin/campaigns.php"  class="nav-link <?= $currentPage === 'campaigns'  ? 'active' : '' ?>">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
      Campaigns
    </a>
    <a href="/admin/templates.php"  class="nav-link <?= $currentPage === 'templates'  ? 'active' : '' ?>">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
      Templates
    </a>
    <a href="/admin/automation.php" class="nav-link <?= $currentPage === 'automation' ? 'active' : '' ?>">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
      Automation
    </a>
    <div class="nav-section-label">Forms</div>
    <a href="/admin/forms.php"      class="nav-link <?= $currentPage === 'forms'      ? 'active' : '' ?>">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Forms
    </a>

    <?php ModuleManager::renderNavItems($currentPage); ?>

    <div class="nav-section-label">Assets</div>
    <a href="/admin/media.php"      class="nav-link <?= $currentPage === 'media'      ? 'active' : '' ?>">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      Media Library
    </a>

    <div class="nav-section-label">System</div>
    <a href="/admin/modules.php"    class="nav-link <?= $currentPage === 'modules'    ? 'active' : '' ?>">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
      Modules
    </a>
    <a href="/admin/settings.php"   class="nav-link <?= $currentPage === 'settings'   ? 'active' : '' ?>">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Settings
    </a>
  </nav>

  <!-- User -->
  <div class="px-3 py-3 border-t border-slate-800/40">
    <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-white/5 transition-colors group">
      <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
        <?= strtoupper(substr($user['name'] ?? 'A', 0, 1)) ?>
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-semibold text-white truncate"><?= e($user['name'] ?? 'Admin') ?></div>
        <div class="text-xs text-slate-500 truncate"><?= e($user['email'] ?? '') ?></div>
      </div>
      <a href="/logout.php" title="Sign out" class="text-slate-600 hover:text-red-400 transition-colors flex-shrink-0">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      </a>
    </div>
  </div>
</aside>

<!-- ═══ MAIN CONTENT ══════════════════════════════════════════════════════════ -->
<div class="ml-60 flex-1 flex flex-col min-h-screen">

  <!-- Top Bar -->
  <header class="sticky top-0 z-30 bg-[#0B0F19]/80 backdrop-blur-xl border-b border-slate-800/40 px-8 py-4">
    <div class="flex items-center justify-between">
      <h1 class="text-lg font-bold text-white"><?= e($pageTitle ?? 'Dashboard') ?></h1>
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-2 text-xs text-slate-500">
          <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block" style="box-shadow:0 0 6px rgba(16,185,129,0.6)"></span>
          <?= e($appName) ?>
        </div>
        <a href="/admin/campaigns.php?action=create" class="btn btn-primary btn-sm">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          New Campaign
        </a>
      </div>
    </div>
  </header>

  <!-- Flash Message -->
  <?php if ($flash): ?>
  <?php
    $flashColors = [
      'success' => 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300',
      'error'   => 'bg-red-500/10 border-red-500/30 text-red-300',
      'warning' => 'bg-amber-500/10 border-amber-500/30 text-amber-300',
      'info'    => 'bg-indigo-500/10 border-indigo-500/30 text-indigo-300',
    ];
    $flashIcons = [
      'success' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
      'error'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
      'warning' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
      'info'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    ];
    $fc = $flashColors[$flash['type']] ?? $flashColors['info'];
    $fi = $flashIcons[$flash['type']] ?? $flashIcons['info'];
  ?>
  <div x-data="{show:true}" x-show="show" x-transition class="mx-8 mt-6">
    <div class="flex items-center gap-3 px-5 py-3.5 rounded-xl border <?= $fc ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><?= $fi ?></svg>
      <p class="text-sm font-medium flex-1"><?= e($flash['message']) ?></p>
      <button @click="show=false" class="opacity-60 hover:opacity-100 transition-opacity flex-shrink-0">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
  </div>
  <?php endif; ?>

  <!-- Page Content -->
  <main class="flex-1 p-8 fade-in">
