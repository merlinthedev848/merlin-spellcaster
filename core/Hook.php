<?php
declare(strict_types=1);

/**
 * Event Hook and Action System for Merlin V2.
 * Allows modular extensions to register and fire lifecycle callbacks.
 */
class Hook {
    private static array $callbacks = [];

    /**
     * Register a callback function for a specific event hook
     */
    public static function register(string $hookName, callable $callback): void {
        self::$callbacks[$hookName][] = $callback;
    }

    /**
     * Fire an event hook, passing data by reference to allow callbacks to mutate states
     */
    public static function fire(string $hookName, mixed &$data = null): void {
        if (isset(self::$callbacks[$hookName])) {
            foreach (self::$callbacks[$hookName] as $callback) {
                try {
                    $callback($data);
                } catch (Throwable $e) {
                    error_log("Hook error (event: {$hookName}): " . $e->getMessage());
                }
            }
        }
    }
}
