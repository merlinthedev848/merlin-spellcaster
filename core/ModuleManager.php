<?php
declare(strict_types=1);

class ModuleManager {
    private static array $hooks = [];

    public static function loadModules(array $activeModules): void {
        foreach ($activeModules as $module) {
            $path = __DIR__ . "/../modules/{$module}/module.php";
            if (file_exists($path)) {
                include_once $path;
            }
        }
    }

    public static function addHook(string $hookName, callable $callback, int $priority = 10): void {
        self::$hooks[$hookName][$priority][] = $callback;
    }

    public static function triggerAction(string $hookName, ...$args): void {
        if (!isset(self::$hooks[$hookName])) return;
        ksort(self::$hooks[$hookName]);
        foreach (self::$hooks[$hookName] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }

    public static function applyFilter(string $hookName, mixed $value, ...$args): mixed {
        if (!isset(self::$hooks[$hookName])) return $value;
        ksort(self::$hooks[$hookName]);
        foreach (self::$hooks[$hookName] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $value = call_user_func($callback, $value, ...$args);
            }
        }
        return $value;
    }
}