<?php

declare(strict_types=1);

namespace Zephyr\Database\Events;

/**
 * Has Model Events Trait
 * 
 * Model sınıflarında kullanılır
 */
trait HasModelEvents
{
    /**
     * Boot the trait
     */
    protected static function bootHasModelEvents(): void
    {
        // Bu metot Model boot sürecinde otomatik çağrılır
    }

    /**
     * Register event listener
     */
    public static function creating(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class, 'creating', $callback);
    }

    public static function created(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class, 'created', $callback);
    }

    public static function updating(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class, 'updating', $callback);
    }

    public static function updated(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class, 'updated', $callback);
    }

    public static function saving(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class, 'saving', $callback);
    }

    public static function saved(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class, 'saved', $callback);
    }

    public static function deleting(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class, 'deleting', $callback);
    }

    public static function deleted(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class, 'deleted', $callback);
    }

    public static function restoring(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class, 'restoring', $callback);
    }

    public static function restored(callable $callback): void
    {
        ModelEventDispatcher::listen(static::class, 'restored', $callback);
    }

    /**
     * Register observer
     */
    public static function observe(string|object $observer): void
    {
        ModelEventDispatcher::observe(static::class, $observer);
    }

    /**
     * Fire model event
     */
    protected function fireModelEvent(string $event): bool
    {
        return ModelEventDispatcher::fire(static::class, $event, $this);
    }
}