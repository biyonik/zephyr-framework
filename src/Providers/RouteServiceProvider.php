<?php

declare(strict_types=1);

namespace Zephyr\Providers;

use Zephyr\Core\{App, Router};
use Zephyr\Http\Kernel;

/**
 * Route Service Provider
 * 
 * Registers routing services and loads application routes.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class RouteServiceProvider
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
        // Register Router as singleton
        $this->app->singleton(Router::class);
        
        // Register HTTP Kernel
        $this->app->singleton(Kernel::class);
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Routes will be loaded in public/index.php for now
        // Later we can load them here
    }
}