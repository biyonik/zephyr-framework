<?php

declare(strict_types=1);

namespace Zephyr\Http;

use Zephyr\Core\{App, Router};

/**
 * HTTP Kernel
 * 
 * Central request handler that manages middleware pipeline
 * and routes requests through the application.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Kernel
{
    /**
     * Application instance
     */
    protected App $app;

    /**
     * Router instance
     */
    protected Router $router;

    /**
     * Global middleware stack
     */
    protected array $middleware = [];

    /**
     * Route middleware aliases
     */
    protected array $routeMiddleware = [];

    /**
     * Constructor
     */
    public function __construct(App $app, Router $router)
    {
        $this->app = $app;
        $this->router = $router;
    }

    /**
     * Handle incoming HTTP request
     */
    public function handle(Request $request): Response
    {
        // For now, directly dispatch to router
        // Later we'll add middleware pipeline here
        return $this->router->dispatch($request);
    }

    /**
     * Terminate the request/response lifecycle
     */
    public function terminate(Request $request, Response $response): void
    {
        // Run any terminable middleware
        // Clean up resources
    }

    /**
     * Get global middleware
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Add global middleware
     */
    public function pushMiddleware(string $middleware): void
    {
        if (!in_array($middleware, $this->middleware)) {
            $this->middleware[] = $middleware;
        }
    }
}