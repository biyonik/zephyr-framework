<?php

declare(strict_types=1);

namespace Zephyr\Providers;

use Zephyr\Core\App;

/**
 * Database Service Provider
 * 
 * Registers database services and connections.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class DatabaseServiceProvider
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Register services
     */
    public function register(): void
    {
        // Database connection will be registered here later
        // For now, just a placeholder
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Database migrations and seeders can be run here
    }
}