<?php

declare(strict_types=1);

namespace Zephyr\Database\Events;

/**
 * Base Model Observer
 * 
 * Custom observer'lar bu sınıfı extend edebilir
 */
abstract class ModelObserver
{
    /**
     * Handle the model "creating" event
     */
    public function creating($model): bool
    {
        return true;
    }

    /**
     * Handle the model "created" event
     */
    public function created($model): void
    {
        //
    }

    /**
     * Handle the model "updating" event
     */
    public function updating($model): bool
    {
        return true;
    }

    /**
     * Handle the model "updated" event
     */
    public function updated($model): void
    {
        //
    }

    /**
     * Handle the model "saving" event
     */
    public function saving($model): bool
    {
        return true;
    }

    /**
     * Handle the model "saved" event
     */
    public function saved($model): void
    {
        //
    }

    /**
     * Handle the model "deleting" event
     */
    public function deleting($model): bool
    {
        return true;
    }

    /**
     * Handle the model "deleted" event
     */
    public function deleted($model): void
    {
        //
    }

    /**
     * Handle the model "restoring" event
     */
    public function restoring($model): bool
    {
        return true;
    }

    /**
     * Handle the model "restored" event
     */
    public function restored($model): void
    {
        //
    }
}