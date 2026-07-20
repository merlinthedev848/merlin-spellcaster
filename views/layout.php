<?php
declare(strict_types=1);
$appName = getSetting('app_name', 'Merlin Spellcaster');
$appUrl = rtrim(getSetting('app_url', 'http://localhost/merlin-spellcaster'), '/');
$currentRoute = defined('CURRENT_ROUTE') ? CURRENT_ROUTE : '/';
$adminEmail = $_SESSION['user_email'] ?? 'admin@domain.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Dashboard') ?> — <?= e($appName) ?></title>
    <link rel="stylesheet" href="<?= e($appUrl) ?>/assets/css/theme.css?v=2.2">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Top Navigation Header Styles */
        .top-navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 60px;
            border-bottom: 1px solid var(--theme-border);
            padding: 0 48px;
            background-color: var(--theme-white);
            position: sticky;
            top: 0;
            z-index: 90;
            box-shadow: 0 1px 2px 0 rgba(0,0,0,0.03);
        }
        .top-navbar-search {
            position: relative;
            display: flex;
            align-items: center;
        }
        .top-navbar-search svg {
            position: absolute;
            left: 12px;
            color: var(--theme-dark-slate);
            pointer-events: none;
        }
        .top-navbar-search input {
            padding: 8px 12px 8px 36px;
            font-size: 13px;
            border-radius: 20px;
            border: 1px solid var(--theme-border);
            width: 240px;
            outline: none;
            background-color: var(--theme-bg);
            transition: all 0.15s ease;
        }
        .top-navbar-search input:focus {
            width: 320px;
            border-color: var(--theme-blurple);
            background-color: var(--theme-white);
            box-shadow: 0 0 0 3px rgba(99, 91, 255, 0.1);
        }
        .user-menu {
            position: relative;
            display: inline-block;
        }
        .user-menu-trigger {
            display: flex;
            align-items: center;
            gap: 8px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.15s ease;
        }
        .user-menu-trigger:hover {
            background-color: var(--theme-bg);
        }
        .user-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: var(--theme-blurple);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }
        .user-email-display {
            font-size: 13px;
            font-weight: 500;
            color: var(--theme-dark);
        }
        .user-dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 36px;
            background-color: var(--theme-white);
            min-width: 160px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--theme-border);
            border-radius: 6px;
            z-index: 120;
            padding: 4px 0;
            animation: fadeIn 0.15s ease;
        }
        .user-dropdown-link {
            display: block;
            padding: 10px 16px;
            color: var(--theme-dark);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.1s ease;
        }
        .user-dropdown-link:hover {
            background-color: var(--theme-bg);
            color: var(--theme-blurple);
        }
        .user-dropdown-link.logout {
            color: var(--danger);
            border-top: 1px solid var(--theme-border);
        }
        .user-dropdown-link.logout:hover {
            background-color: var(--danger-light);
            color: var(--danger);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Mobile Responsive Styling */
        @media screen and (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.2s ease;
                z-index: 100;
                box-shadow: 2px 0 8px rgba(0,0,0,0.1);
            }
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            .top-navbar {
                padding: 0 16px !important;
            }
            main.main-content {
                padding: 16px !important;
            }
            .grid {
                grid-template-columns: 1fr !important;
            }
            .grid-1-3 {
                grid-template-columns: 1fr !important;
            }
            .grid-3 {
                grid-template-columns: 1fr !important;
            }
            .top-navbar-search input {
                width: 140px !important;
            }
            .top-navbar-search input:focus {
                width: 170px !important;
            }
            .user-email-display {
                display: none !important;
            }
            .mobile-menu-toggle {
                display: flex !important;
                align-items: center;
                justify-content: center;
                background: none;
                border: none;
                cursor: pointer;
                padding: 8px;
                color: var(--theme-dark);
                margin-right: 12px;
            }
        }
        @media screen and (min-width: 769px) {
            .mobile-menu-toggle {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar Layout -->
    <div class="sidebar">
        <a href="<?= e($appUrl) ?>/" class="sidebar-brand">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
            </svg>
            <span><?= e($appName) ?></span>
        </a>
        
        <nav style="flex-grow: 1;">
            <ul class="sidebar-menu">
                <li>
                    <a href="<?= e($appUrl) ?>/dashboard" class="sidebar-link <?= ($currentRoute === '/' || $currentRoute === '/dashboard') ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="9"></rect>
                            <rect x="14" y="3" width="7" height="5"></rect>
                            <rect x="14" y="12" width="7" height="9"></rect>
                            <rect x="3" y="16" width="7" height="5"></rect>
                        </svg>
                        Dashboard
                    </a>
                </li>
                <?php if (Database::getTenantSubdomain() === null): ?>
                <li>
                    <a href="<?= e($appUrl) ?>/super/tenants" class="sidebar-link <?= (str_starts_with($currentRoute, '/super/tenants')) ? 'active' : '' ?>" style="border-left: 3px solid #f59e0b;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;">
                            <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                            <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                            <line x1="6" y1="6" x2="6.01" y2="6"></line>
                            <line x1="6" y1="18" x2="6.01" y2="18"></line>
                        </svg>
                        <span style="color: #f59e0b; font-weight: 700;">Tenants SaaS</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="<?= e($appUrl) ?>/contacts" class="sidebar-link <?= (str_starts_with($currentRoute, '/contacts')) ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        Contacts (CRM)
                    </a>
                </li>
                <li>
                    <a href="<?= e($appUrl) ?>/tags" class="sidebar-link <?= (str_starts_with($currentRoute, '/tags')) ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                            <line x1="7" y1="7" x2="7.01" y2="7"></line>
                        </svg>
                        CRM Tags
                    </a>
                </li>
                <li>
                    <a href="<?= e($appUrl) ?>/forms" class="sidebar-link <?= (str_starts_with($currentRoute, '/forms')) ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        Forms
                    </a>
                </li>
                <li>
                    <a href="<?= e($appUrl) ?>/templates" class="sidebar-link <?= (str_starts_with($currentRoute, '/templates')) ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9"></path>
                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                        </svg>
                        Templates
                    </a>
                </li>
                <li>
                    <a href="<?= e($appUrl) ?>/campaigns" class="sidebar-link <?= (str_starts_with($currentRoute, '/campaigns')) ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        Campaigns
                    </a>
                </li>
                <li>
                    <a href="<?= e($appUrl) ?>/automations" class="sidebar-link <?= (str_starts_with($currentRoute, '/automations')) ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                        Automations
                    </a>
                </li>
                <li>
                    <a href="<?= e($appUrl) ?>/analytics" class="sidebar-link <?= (str_starts_with($currentRoute, '/analytics')) ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                        Analytics
                    </a>
                </li>
                <?php
                $activeMods = ModuleManager::getEnabledModules();
                $isModuleActive = ($currentRoute === '/extensions');
                foreach ($activeMods as $mod) {
                    if (!empty($mod['menu_path']) && str_starts_with($currentRoute, $mod['menu_path'])) {
                        $isModuleActive = true;
                        break;
                    }
                }
                ?>
                <li>
                    <a href="<?= e($appUrl) ?>/media" class="sidebar-link <?= (str_starts_with($currentRoute, '/media')) ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                        Media Library
                    </a>
                </li>
                <li class="menu-item-has-children">
                    <a href="<?= e($appUrl) ?>/extensions" class="sidebar-link <?= $isModuleActive ? 'active' : '' ?>" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            <span>Modules</span>
                        </div>
                        <?php if (!empty($activeMods)): ?>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.2s; transform: <?= $isModuleActive ? 'rotate(90deg)' : 'rotate(0deg)' ?>;"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        <?php endif; ?>
                    </a>
                    <?php if (!empty($activeMods)): ?>
                        <ul class="sidebar-submenu" style="list-style: none; padding-left: 28px; margin: 4px 0 8px 0; display: flex; flex-direction: column; gap: 4px;">
                            <?php 
                            $menuCount = 0;
                            $hasMore = false;
                            foreach ($activeMods as $modId => $modInfo): 
                                if (empty($modInfo['menu_label']) || empty($modInfo['menu_path'])) continue;
                                $menuCount++;
                                if ($menuCount > 6) {
                                    $hasMore = true;
                                    continue;
                                }
                                $label = $modInfo['menu_label'];
                                $path = $modInfo['menu_path'];
                                $isActive = (str_starts_with($currentRoute, $path)) ? 'active' : '';
                            ?>
                                <li>
                                    <a href="<?= e($appUrl . $path) ?>" class="sidebar-link <?= $isActive ?>" style="padding: 6px 12px; font-size: 13px; color: <?= $isActive ? 'var(--theme-white)' : '#adbdcc' ?>; background-color: <?= $isActive ? 'rgba(255, 255, 255, 0.08)' : 'transparent' ?>; justify-content: flex-start; gap: 8px;">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 12px; height: 12px; flex-shrink: 0; color: <?= $isActive ? 'var(--theme-blurple)' : 'inherit' ?>;">
                                            <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                                            <polyline points="2 17 12 22 22 17"></polyline>
                                            <polyline points="2 12 12 17 22 12"></polyline>
                                        </svg>
                                        <span><?= e($label) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <?php if ($hasMore): ?>
                                <li>
                                    <a href="<?= e($appUrl) ?>/extensions" class="sidebar-link" style="padding: 6px 12px; font-size: 12px; color: var(--theme-blurple); justify-content: flex-start; gap: 8px; font-weight: 600;">
                                        ⚡ View All Modules...
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>
                </li>
                <li>
                    <a href="<?= e($appUrl) ?>/settings" class="sidebar-link <?= ($currentRoute === '/settings') ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                        Settings
                    </a>
                </li>
                <li>
                    <a href="<?= e($appUrl) ?>/diagnostics" class="sidebar-link <?= ($currentRoute === '/diagnostics') ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        Diagnostics
                    </a>
                </li>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <div>Connected Database</div>
            <div style="color: #ffffff; font-weight: 500; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                <?= e($GLOBALS['db_name'] ?? 'SQLite/Unknown') ?>
            </div>
            <div style="margin-top: 8px;">Merlin V2 • PHP 8.5+</div>
        </div>
    </div>

    <!-- Top Utility Navbar -->
    <div class="main-wrapper">
        <header class="top-navbar">
            <!-- Mobile Menu Toggle Button -->
            <button class="mobile-menu-toggle" onclick="toggleSidebar(event)">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>
            
            <!-- Search field -->
            <div class="top-navbar-search">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <form action="<?= e($appUrl) ?>/contacts" method="GET">
                    <input type="text" name="q" placeholder="Global contact search...">
                </form>
            </div>
            
            <!-- User Dropdown Menu -->
            <div class="user-menu">
                <button class="user-menu-trigger" onclick="toggleUserDropdown(event)">
                    <div class="user-avatar"><?= substr(e($adminEmail), 0, 1) ?></div>
                    <span class="user-email-display"><?= e($adminEmail) ?></span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="color: var(--theme-dark-slate);"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>
                <div class="user-dropdown-menu" id="userDropdown">
                    <a href="<?= e($appUrl) ?>/settings" class="user-dropdown-link">Settings Profile</a>
                    <a href="<?= e($appUrl) ?>/diagnostics" class="user-dropdown-link">System Diagnostics</a>
                    <a href="<?= e($appUrl) ?>/logout" class="user-dropdown-link logout">Sign Out</a>
                </div>
            </div>
        </header>

        <!-- Main Body pane -->
        <main class="main-content" style="padding-top: 32px;">
            <div class="toast-container" id="toastContainer">
                <?php if (isset($_SESSION['flash_success'])): ?>
                    <div class="toast" style="background-color: var(--theme-dark); border-left: 4px solid var(--success);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <?= e($_SESSION['flash_success']) ?>
                    </div>
                    <?php unset($_SESSION['flash_success']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['flash_error'])): ?>
                    <div class="toast" style="background-color: var(--theme-dark); border-left: 4px solid var(--danger);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                        <?= e($_SESSION['flash_error']) ?>
                    </div>
                    <?php unset($_SESSION['flash_error']); ?>
                <?php endif; ?>
            </div>

            <?php include $viewPath; ?>
        </main>
    </div>

    <script>
        // Dismiss toasts
        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-20px)';
                setTimeout(() => toast.remove(), 500);
            });
        }, 4000);

        // User dropdown menu toggler
        function toggleUserDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById("userDropdown");
            const isVisible = dropdown.style.display === "block";
            dropdown.style.display = isVisible ? "none" : "block";
        }

        // Close dropdown when clicking outside
        window.addEventListener("click", function() {
            document.getElementById("userDropdown").style.display = "none";
        });

        // Toggle responsive mobile sidebar
        function toggleSidebar(event) {
            event.stopPropagation();
            const sidebar = document.querySelector(".sidebar");
            sidebar.classList.toggle("mobile-open");
        }
        
        // Dismiss mobile sidebar when clicking outside the menu panel
        document.addEventListener("click", function(event) {
            const sidebar = document.querySelector(".sidebar");
            const toggleBtn = document.querySelector(".mobile-menu-toggle");
            if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains("mobile-open")) {
                if (!sidebar.contains(event.target) && (!toggleBtn || !toggleBtn.contains(event.target))) {
                    sidebar.classList.remove("mobile-open");
                }
            }
        });
    </script>
</body>
</html>
