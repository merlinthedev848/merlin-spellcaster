<?php
/**
 * core/ModuleManager.php
 * PHP 8.5+ — Hook system + Module registry
 */
declare(strict_types=1);

class ModuleManager
{
    private static array $hooks   = [];
    private static array $navItems = [];
    private static array $loaded  = [];

    // ─── Hook System ──────────────────────────────────────────────────────────

    public static function addHook(string $hookName, callable $callback, int $priority = 10): void
    {
        self::$hooks[$hookName][$priority][] = $callback;
    }

    public static function triggerAction(string $hookName, mixed ...$args): void
    {
        if (!isset(self::$hooks[$hookName])) return;
        ksort(self::$hooks[$hookName]);
        foreach (self::$hooks[$hookName] as $callbacks) {
            foreach ($callbacks as $cb) {
                call_user_func_array($cb, $args);
            }
        }
    }

    public static function applyFilter(string $hookName, mixed $value, mixed ...$args): mixed
    {
        if (!isset(self::$hooks[$hookName])) return $value;
        ksort(self::$hooks[$hookName]);
        foreach (self::$hooks[$hookName] as $callbacks) {
            foreach ($callbacks as $cb) {
                $value = call_user_func($cb, $value, ...$args);
            }
        }
        return $value;
    }

    // ─── Module Loading ───────────────────────────────────────────────────────

    /**
     * Load all active modules from the DB settings table.
     */
    public static function loadModules(PDO $db): void
    {
        $active = self::getActiveModules($db);
        foreach ($active as $slug) {
            if (isset(self::$loaded[$slug])) continue;
            $path = dirname(__DIR__) . "/modules/{$slug}/module.php";
            if (file_exists($path)) {
                require_once $path;
                self::$loaded[$slug] = true;
            }
        }
    }

    /**
     * Read active modules from settings table.
     */
    public static function getActiveModules(PDO $db): array
    {
        try {
            $st = $db->query("SELECT folder_name FROM modules WHERE is_active = 1");
            return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    // ─── Nav Item Registry ────────────────────────────────────────────────────

    /**
     * Called by a module's module.php to register a sidebar nav entry.
     *
     * @param string $label      Visible text
     * @param string $url        Href
     * @param string $icon       SVG path data (d= attribute content)
     * @param array  $activeOn   Array of page basenames (without .php) that make this entry active
     * @param string $section    Sidebar section label (e.g. 'Modules')
     * @param int    $priority   Sort order within section
     */
    public static function registerNavItem(
        string $label,
        string $url,
        string $icon       = '',
        array  $activeOn   = [],
        string $section    = 'Modules',
        int    $priority   = 50
    ): void {
        self::$navItems[$section][$priority][] = compact('label','url','icon','activeOn');
    }

    /**
     * Render all registered nav items into the sidebar.
     * Called from includes/header.php in the nav section.
     */
    public static function renderNavItems(string $currentPage): void
    {
        if (empty(self::$navItems)) return;

        foreach (self::$navItems as $section => $priorityGroups) {
            ksort($priorityGroups);
            echo '<div class="nav-section-label">' . htmlspecialchars($section, ENT_QUOTES, 'UTF-8') . '</div>';
            foreach ($priorityGroups as $items) {
                foreach ($items as $item) {
                    $isActive = in_array($currentPage, $item['activeOn'], true);
                    $activeClass = $isActive ? 'active' : '';
                    $svgPath = $item['icon'] ? '<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' . $item['icon'] . '</svg>' : '';
                    echo '<a href="' . htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') . '" class="nav-link ' . $activeClass . '">'
                       . $svgPath
                       . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')
                       . '</a>';
                }
            }
        }
    }

    // ─── Manifest Discovery ───────────────────────────────────────────────────

    /**
     * Discover all installed modules by scanning for manifest.json files.
     */
    public static function discoverModules(): array
    {
        $base    = dirname(__DIR__) . '/modules/';
        $modules = [];
        if (!is_dir($base)) return $modules;

        foreach (glob($base . '*/manifest.json') ?: [] as $manifestPath) {
            $data = json_decode(file_get_contents($manifestPath) ?: '{}', true);
            if (!empty($data['slug'])) {
                $data['_path'] = dirname($manifestPath);
                $modules[$data['slug']] = $data;
            }
        }
        return $modules;
    }

    /**
     * Run a module's install.php (schema creation etc).
     */
    public static function installModule(string $slug, PDO $db): bool
    {
        $installPath = dirname(__DIR__) . "/modules/{$slug}/install.php";
        if (!file_exists($installPath)) return true; // no install script needed
        try {
            require $installPath; // install.php has access to $db
            return true;
        } catch (Throwable $e) {
            error_log("[ModuleManager] Install failed for {$slug}: " . $e->getMessage());
            return false;
        }
    }
}