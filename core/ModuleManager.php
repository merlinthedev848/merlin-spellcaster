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

    /**
     * Get list of enabled module IDs
     */
    public static function getEnabledModuleIds(): array {
        $enabledStr = getSetting('enabled_modules_list', '');
        if ($enabledStr === '') {
            return ['sms_marketing', 'maps_scraper', 'ai_copywriter', 'survey_builder', 'visual_builder', 'deliverability_sentinel', 'workflow_engine', 'predictive_scoring', 'web_personalization', 'data_enrichment', 'domain_warmup', 'ab_testing', 'link_rotator', 'rss_to_email', 'viral_loops', 'webhooks', 'list_scraper', 'fomo_timers', 'email_verifier'];
        }
        $list = explode(',', $enabledStr);
        if (!in_array('sms_marketing', $list, true)) {
            $list[] = 'sms_marketing';
        }
        return $list;
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
        $enabled = self::getEnabledModuleIds();
        $key = array_search($id, $enabled, true);

        if ($key !== false) {
            unset($enabled[$key]);
            $status = false;
        } else {
            $enabled[] = $id;
            $status = true;
        }

        setSetting('enabled_modules_list', implode(',', array_filter($enabled)));
        return $status;
    }
}
