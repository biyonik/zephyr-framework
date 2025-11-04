<?php

declare(strict_types=1);

namespace Zephyr\Http\Middleware;

use Closure;
use Zephyr\Http\{Request, Response};
use Zephyr\Support\Config;

/**
 * CORS Middleware
 *
 * Handles Cross-Origin Resource Sharing (CORS) headers.
 * Allows APIs to be accessed from different domains.
 *
 * Configuration is loaded from config/cors.php
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class CorsMiddleware implements MiddlewareInterface
{
    /**
     * Handle CORS headers
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflightRequest($request);
        }

        // Process the request
        $response = $next($request);

        // Add CORS headers to response
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Handle preflight OPTIONS request
     *
     * Preflight requests are sent by browsers before actual requests
     * to check if CORS is allowed.
     *
     * @param Request $request
     * @return Response
     */
    protected function handlePreflightRequest(Request $request): Response
    {
        $response = Response::noContent();

        $origin = $request->header('Origin');

        if ($origin && $this->isAllowedOrigin($origin)) {
            $response->header('Access-Control-Allow-Origin', $origin);
            $response->header('Access-Control-Allow-Methods', $this->getAllowedMethods());
            $response->header('Access-Control-Allow-Headers', $this->getAllowedHeaders());
            $response->header('Access-Control-Max-Age', (string) $this->getMaxAge());

            if ($this->supportsCredentials()) {
                $response->header('Access-Control-Allow-Credentials', 'true');
            }
        }

        return $response;
    }

    /**
     * Add CORS headers to response
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    protected function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->header('Origin');

        if (!$origin) {
            return $response;
        }

        if ($this->isAllowedOrigin($origin)) {
            $response->header('Access-Control-Allow-Origin', $origin);

            if ($this->supportsCredentials()) {
                $response->header('Access-Control-Allow-Credentials', 'true');
            }

            // Expose headers to the browser
            $exposedHeaders = $this->getExposedHeaders();
            if (!empty($exposedHeaders)) {
                $response->header('Access-Control-Expose-Headers', $exposedHeaders);
            }
        }

        return $response;
    }

    /**
     * Check if origin is allowed
     *
     * @param string $origin
     * @return bool
     */
    protected function isAllowedOrigin(string $origin): bool
    {
        $allowedOrigins = Config::get('cors.allowed_origins', ['*']);

        // Allow all origins
        if (in_array('*', $allowedOrigins, true)) {
            return true;
        }

        // Check exact match
        if (in_array($origin, $allowedOrigins, true)) {
            return true;
        }

        // Check wildcard patterns
        foreach ($allowedOrigins as $allowedOrigin) {
            if ($this->matchesPattern($origin, $allowedOrigin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if origin matches wildcard pattern
     *
     * Supports patterns like:
     * - *.example.com
     * - https://*.example.com
     *
     * @param string $origin
     * @param string $pattern
     * @return bool
     */
    protected function matchesPattern(string $origin, string $pattern): bool
    {
        if (!str_contains($pattern, '*')) {
            return false;
        }

        // Convert wildcard pattern to regex
        $regex = '#^' . str_replace(
                ['*', '.'],
                ['.*', '\.'],
                $pattern
            ) . '$#i';

        return (bool) preg_match($regex, $origin);
    }

    /**
     * Get allowed methods
     *
     * @return string Comma-separated methods
     */
    protected function getAllowedMethods(): string
    {
        $methods = Config::get('cors.allowed_methods', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']);

        if (is_array($methods)) {
            return implode(', ', $methods);
        }

        return (string) $methods;
    }

    /**
     * Get allowed headers
     *
     * @return string Comma-separated headers
     */
    protected function getAllowedHeaders(): string
    {
        $headers = Config::get('cors.allowed_headers', ['Content-Type', 'Authorization']);

        if (is_array($headers)) {
            return implode(', ', $headers);
        }

        return (string) $headers;
    }

    /**
     * Get exposed headers
     *
     * @return string Comma-separated headers
     */
    protected function getExposedHeaders(): string
    {
        $headers = Config::get('cors.exposed_headers', []);

        if (is_array($headers)) {
            return implode(', ', $headers);
        }

        return (string) $headers;
    }

    /**
     * Get max age for preflight cache
     *
     * @return int Seconds
     */
    protected function getMaxAge(): int
    {
        return (int) Config::get('cors.max_age', 3600);
    }

    /**
     * Check if credentials are supported
     *
     * @return bool
     */
    protected function supportsCredentials(): bool
    {
        return (bool) Config::get('cors.supports_credentials', false);
    }
}