<?php

declare(strict_types=1);

namespace Zephyr\Http\Middleware;

use Closure;
use Zephyr\Http\{Request, Response};

/**
 * Middleware Interface
 *
 * Defines the contract for HTTP middleware.
 * Middleware can inspect and modify requests before they reach
 * the controller, and responses after they leave the controller.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
interface MiddlewareInterface
{
    /**
     * Handle an incoming request
     *
     * The middleware should call $next($request) to pass the request
     * to the next middleware or controller. It can modify the request
     * before calling $next, and modify the response after $next returns.
     *
     * @param Request $request The incoming request
     * @param Closure $next The next middleware/controller in the pipeline
     * @return Response The response
     *
     * @example Basic middleware
     * ```php
     * public function handle(Request $request, Closure $next): Response
     * {
     *     // Before logic
     *     $request->header('X-Custom', 'value');
     *
     *     // Call next middleware/controller
     *     $response = $next($request);
     *
     *     // After logic
     *     $response->header('X-Processing-Time', '100ms');
     *
     *     return $response;
     * }
     * ```
     *
     * @example Terminating middleware (early return)
     * ```php
     * public function handle(Request $request, Closure $next): Response
     * {
     *     // Check authentication
     *     if (!$this->isAuthenticated($request)) {
     *         return Response::error('Unauthorized', 401);
     *     }
     *
     *     return $next($request);
     * }
     * ```
     */
    public function handle(Request $request, Closure $next): Response;
}