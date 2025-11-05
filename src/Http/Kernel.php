<?php

declare(strict_types=1);

namespace Zephyr\Http;

use Throwable;
use Zephyr\Core\{App, Pipeline, Router};
use Zephyr\Exceptions\Handler as ExceptionHandler;

/**
 * HTTP Kernel
 *
 * Central request handler that manages middleware pipeline
 * and routes requests through the application.
 *
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
     * Global middleware stack (runs on every request)
     *
     * These middleware run before routing happens.
     * Order matters - they execute in array order.
     *
     * @var array<class-string>
     */
    protected array $middleware = [
        // Global middleware will be registered here
        // Example:
        // \Zephyr\Http\Middleware\TrustedProxies::class,
        \Zephyr\Http\Middleware\CorsMiddleware::class,
        \Zephyr\Http\Middleware\RateLimitMiddleware::class,
    ];

    /**
     * Route middleware (can be assigned to specific routes)
     *
     * Aliases for middleware that can be attached to routes.
     *
     * @var array<string, class-string>
     */
    protected array $routeMiddleware = [
        // Route middleware aliases will be registered here
        // Example:
        'auth' => \Zephyr\Http\Middleware\AuthMiddleware::class,
        // 'throttle' => \Zephyr\Http\Middleware\ThrottleRequests::class,
    ];

    /**
     * Middleware groups
     *
     * Groups of middleware that can be assigned together.
     *
     * @var array<string, array<class-string>>
     */
    protected array $middlewareGroups = [
        'web' => [
            // Web middleware group
        ],
        'api' => [
            // API middleware group
        ],
    ];

    /**
     * Middleware priority
     *
     * Defines the order in which middleware should be sorted.
     * Lower priority number = executes earlier.
     *
     * @var array<class-string, int>
     */
    protected array $middlewarePriority = [
        // High priority (early execution)
        // \Zephyr\Http\Middleware\TrustedProxies::class => 10,
        // \Zephyr\Http\Middleware\Cors::class => 20,
        // \Zephyr\Http\Middleware\RateLimit::class => 30,
        // \Zephyr\Http\Middleware\Authenticate::class => 40,
        // \Zephyr\Http\Middleware\Validation::class => 50,
        // Low priority (late execution)
    ];

    /**
     * Constructor
     */
    public function __construct(App $app, Router $router)
    {
        $this->app = $app;
        $this->router = $router;
    }

    /**
     * Handle an incoming HTTP request
     *
     * This is the main entry point for all HTTP requests.
     *
     * Flow:
     * 1. Request enters
     * 2. Global middleware runs
     * 3. Router matches route
     * 4. Route middleware runs
     * 5. Controller executes
     * 6. Response returns through middleware stack
     *
     * @param Request $request The HTTP request
     * @return Response The HTTP response
     */
    public function handle(Request $request): Response
    {
        try {
            // ✅ NEW: Send request through middleware pipeline
            return (new Pipeline($this->app))
                ->send($request)
                ->through($this->gatherMiddleware($request))
                ->then(function (Request $request) {
                    // After middleware, dispatch to router
                    return $this->router->dispatch($request);
                });

        } catch (Throwable $e) {
            // Handle exceptions
            return $this->handleException($e, $request);
        }
    }

    /**
     * Gather all middleware for the request
     *
     * Combines global middleware with route-specific middleware.
     *
     * @param Request $request
     * @return array<class-string>
     */
    protected function gatherMiddleware(Request $request): array
    {
        $middleware = $this->middleware;

        // TODO: Add route-specific middleware here
        // This will be implemented when we add route middleware support
        // For now, just return global middleware

        return $this->sortMiddleware($middleware);
    }

    /**
     * Sort middleware by priority
     *
     * Ensures middleware runs in the correct order based on
     * the $middlewarePriority array.
     *
     * @param array<class-string> $middleware
     * @return array<class-string>
     */
    protected function sortMiddleware(array $middleware): array
    {
        if (empty($this->middlewarePriority)) {
            return $middleware;
        }

        $prioritized = [];
        $unprioritized = [];

        foreach ($middleware as $class) {
            if (isset($this->middlewarePriority[$class])) {
                $prioritized[$class] = $this->middlewarePriority[$class];
            } else {
                $unprioritized[] = $class;
            }
        }

        // Sort prioritized middleware
        asort($prioritized);

        // Combine: prioritized first, then unprioritized
        return array_merge(
            array_keys($prioritized),
            $unprioritized
        );
    }

    /**
     * Handle an exception and convert to response
     *
     * @param Throwable $e The exception
     * @param Request $request The request that caused the exception
     * @return Response Error response
     */
    protected function handleException(Throwable $e, Request $request): Response
    {
        // Get exception handler from container
        $handler = $this->app->resolve(ExceptionHandler::class);

        // Convert exception to response
        return $handler->handle($e, $request);
    }

    /**
     * Terminate the request/response lifecycle
     *
     * Allows middleware to perform cleanup after response is sent.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Run terminable middleware
        $this->terminateMiddleware($request, $response);
    }

    /**
     * Call terminate method on terminable middleware
     *
     * @param Request $request
     * @param Response $response
     */
    protected function terminateMiddleware(Request $request, Response $response): void
    {
        foreach ($this->middleware as $middleware) {
            if (!is_string($middleware)) {
                continue;
            }

            // Resolve middleware
            $instance = $this->app->resolve($middleware);

            // Call terminate if it exists
            if (method_exists($instance, 'terminate')) {
                $instance->terminate($request, $response);
            }
        }
    }

    /**
     * Get global middleware
     *
     * @return array<class-string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Add global middleware
     *
     * @param string $middleware Middleware class name
     * @return void
     */
    public function pushMiddleware(string $middleware): void
    {
        if (!in_array($middleware, $this->middleware, true)) {
            $this->middleware[] = $middleware;
        }
    }

    /**
     * Prepend middleware to the beginning of the stack
     *
     * @param string $middleware Middleware class name
     * @return void
     */
    public function prependMiddleware(string $middleware): void
    {
        if (!in_array($middleware, $this->middleware, true)) {
            array_unshift($this->middleware, $middleware);
        }
    }

    /**
     * Register route middleware alias
     *
     * @param string $name Alias name
     * @param string $middleware Middleware class name
     * @return void
     */
    public function aliasMiddleware(string $name, string $middleware): void
    {
        $this->routeMiddleware[$name] = $middleware;
    }

    /**
     * Get route middleware
     *
     * @return array<string, class-string>
     */
    public function getRouteMiddleware(): array
    {
        return $this->routeMiddleware;
    }

    /**
     * Register middleware group
     *
     * @param string $name Group name
     * @param array<class-string> $middleware Middleware classes
     * @return void
     */
    public function middlewareGroup(string $name, array $middleware): void
    {
        $this->middlewareGroups[$name] = $middleware;
    }

    /**
     * Get middleware groups
     *
     * @return array<string, array<class-string>>
     */
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * Set middleware priority
     *
     * @param string $middleware Middleware class name
     * @param int $priority Priority value (lower = earlier execution)
     * @return void
     */
    public function prioritizeMiddleware(string $middleware, int $priority): void
    {
        $this->middlewarePriority[$middleware] = $priority;
    }
}