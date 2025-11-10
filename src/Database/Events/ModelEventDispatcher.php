<?php

declare(strict_types=1);

namespace Zephyr\Database\Events;

/**
 * Model Event Dispatcher
 *
 * Model event'lerini yönetir:
 * - creating, created, updating, updated, saving, saved
 * - deleting, deleted, restoring, restored
 * - Observer pattern support
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class ModelEventDispatcher
{
    /**
     * Registered event listeners per model
     * 
     * [
     *   'App\\Models\\User' => [
     *     'creating' => [callable1, callable2],
     *     'created' => [callable1]
     *   ]
     * ]
     */
    protected static array $listeners = [];

    /**
     * Registered observers per model
     * 
     * [
     *   'App\\Models\\User' => UserObserver::class
     * ]
     */
    protected static array $observers = [];

    /**
     * Available model events
     */
    protected static array $events = [
        'creating', 'created', 'updating', 'updated',
        'saving', 'saved', 'deleting', 'deleted',
        'restoring', 'restored'
    ];

    /**
     * Register event listener
     */
    public static function listen(string $model, string $event, callable $callback): void
    {
        if (!in_array($event, static::$events)) {
            throw new \InvalidArgumentException("Invalid event: {$event}");
        }

        static::$listeners[$model][$event][] = $callback;
    }

    /**
     * Register observer
     */
    public static function observe(string $model, string|object $observer): void
    {
        static::$observers[$model] = $observer;
    }

    /**
     * Fire model event
     */
    public static function fire(string $model, string $event, $modelInstance): bool
    {
        // Fire regular listeners
        $result = static::fireListeners($model, $event, $modelInstance);
        
        if ($result === false) {
            return false;
        }

        // Fire observer method
        return static::fireObserver($model, $event, $modelInstance);
    }

    /**
     * Fire registered listeners
     */
    protected static function fireListeners(string $model, string $event, $modelInstance): bool
    {
        if (!isset(static::$listeners[$model][$event])) {
            return true;
        }

        foreach (static::$listeners[$model][$event] as $callback) {
            $result = $callback($modelInstance);
            
            // If any listener returns false, halt the operation
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fire observer method
     */
    protected static function fireObserver(string $model, string $event, $modelInstance): bool
    {
        if (!isset(static::$observers[$model])) {
            return true;
        }

        $observer = static::$observers[$model];
        
        if (is_string($observer)) {
            $observer = new $observer;
        }

        if (method_exists($observer, $event)) {
            $result = $observer->$event($modelInstance);
            
            // If observer returns false, halt the operation
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clear all listeners and observers (for testing)
     */
    public static function clear(): void
    {
        static::$listeners = [];
        static::$observers = [];
    }

    /**
     * Get registered listeners for model
     */
    public static function getListeners(string $model): array
    {
        return static::$listeners[$model] ?? [];
    }

    /**
     * Get registered observer for model
     */
    public static function getObserver(string $model): string|object|null
    {
        return static::$observers[$model] ?? null;
    }
}