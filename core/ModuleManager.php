<?php
declare(strict_types=1);

/**
 * Module & Plugin Manager for Merlin V2.
 * Scans directories for extensions, handles toggling statuses, and registers routes/menus.
 */
class ModuleManager {
    /**
     * Get a list of all discovered modules
     */
    public static function getModules(): array {
        $modulesDir = dirname(__DIR__) . '/modules';
        if (!file_exists($modulesDir)) {
            @mkdir($modulesDir, 0755, true);
        }

        $modules = [];
        $dirs = glob($modulesDir . '/*', GLOB_ONLYDIR);
        
        if ($dirs !== false) {
            foreach ($dirs as $dir) {
                $configFile = $dir . '/module.json';
                if (file_exists($configFile)) {
                    $json = json_decode(file_get_contents($configFile), true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($json['id'])) {
                        $id = $json['id'];
                        $json['dir'] = $dir;
                        $json['enabled'] = self::isEnabled($id);
                        $modules[$id] = $json;
                    }
                }
            }
        }
        return $modules;
    }

    public static function getEnabledModuleIds(bool $forceRefresh = false): array {
        static $cache = null;
        if ($cache !== null && !$forceRefresh) {
            return $cache;
        }

        $enabledStr = getSetting('enabled_modules_list', 'INITIAL_STATE');
        
        $suites = ['scoring_analytics', 'lead_generation', 'deliverability_suite', 'conversion_suite'];
        
        if ($enabledStr === 'INITIAL_STATE' || $enabledStr === '') {
            $list = array_merge(['ai_copywriter', 'ai_settings', 'seo_auditor'], $suites);
            setSetting('enabled_modules_list', implode(',', $list));
            $cache = $list;
            return $list;
        }
        
        if ($enabledStr === 'NONE') {
            $cache = [];
            return [];
        }
        
        $list = explode(',', $enabledStr);
        $archived = ['lead_scoring', 'predictive_scoring', 'list_scraper', 'maps_scraper', 'data_enrichment', 'deliverability_sentinel', 'domain_warmup', 'email_verifier', 'fomo_timers', 'link_rotator', 'viral_loops', 'survey_builder', 'ab_testing', 'web_personalization', 'hello_world'];
        $list = array_diff($list, $archived);
        
        $cache = array_values(array_unique(array_filter($list)));
        return $cache;
    }

    /**
     * Get details of all enabled modules
     */
    public static function getEnabledModules(): array {
        $all = self::getModules();
        $enabledIds = self::getEnabledModuleIds();
        $enabled = [];
        
        foreach ($enabledIds as $id) {
            if (isset($all[$id])) {
                $enabled[$id] = $all[$id];
            }
        }
        return $enabled;
    }

    /**
     * Check if a specific module is enabled
     */
    public static function isEnabled(string $id): bool {
        return in_array($id, self::getEnabledModuleIds(), true);
    }

    /**
     * Toggle module status
     */
    public static function toggleModule(string $id): bool {
        $enabled = self::getEnabledModuleIds(true);
        $key = array_search($id, $enabled, true);

        if ($key !== false) {
            unset($enabled[$key]);
            $status = false;
        } else {
            $enabled[] = $id;
            $status = true;
        }

        $enabled = array_filter($enabled);
        $saveValue = empty($enabled) ? 'NONE' : implode(',', $enabled);
        setSetting('enabled_modules_list', $saveValue);
        self::getEnabledModuleIds(true); // reload cache
        return $status;
    }
}
