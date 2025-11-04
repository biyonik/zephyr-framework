<?php

declare(strict_types=1);

namespace Zephyr\Http;

use Throwable;
use Zephyr\Core\{App, Router};
use Zephyr\Exceptions\Handler as ExceptionHandler;

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
     * 
     * This is the main entry point for all HTTP requests.
     * Exceptions are caught and converted to proper error responses.
     * 
     * @param Request $request The HTTP request
     * @return Response The HTTP response
     */
    public function handle(Request $request): Response
    {
        try {
            // ✅ Wrap routing in try/catch for exception handling
            // Later: Add middleware pipeline here
            return $this->router->dispatch($request);
            
        } catch (Throwable $e) {
            // ✅ Convert exception to error response
            return $this->handleException($e, $request);
        }
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