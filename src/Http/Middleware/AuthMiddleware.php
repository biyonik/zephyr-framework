<?php

declare(strict_types=1);

namespace Zephyr\Http\Middleware;

use Closure;
use Zephyr\Http\{Request, Response};
use Zephyr\Exceptions\Http\UnauthorizedException;

/**
 * Authentication Middleware
 *
 * Ensures the request is authenticated before proceeding.
 *
 * TODO: This is a skeleton. Will be fully implemented when JWT auth is ready.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * Handle authentication
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     *
     * @throws UnauthorizedException If not authenticated
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get token from Authorization header
        $token = $request->bearerToken();

        if (!$token) {
            throw new UnauthorizedException('Authentication required');
        }

        // TODO: Validate JWT token here
        // For now, just check if token exists
        // In future:
        // 1. Decode JWT
        // 2. Verify signature
        // 3. Check expiry
        // 4. Load user from token
        // 5. Inject user into request

        // Placeholder validation
        if (strlen($token) < 10) {
            throw new UnauthorizedException('Invalid authentication token');
        }

        // Continue to next middleware/controller
        return $next($request);
    }

    /**
     * Validate JWT token (placeholder)
     *
     * TODO: Implement actual JWT validation
     *
     * @param string $token
     * @return bool
     */
    protected function validateToken(string $token): bool
    {
        // TODO: Implement JWT validation
        // Will use firebase/php-jwt library

        return !empty($token);
    }

    /**
     * Get user from token (placeholder)
     *
     * TODO: Implement user retrieval from JWT payload
     *
     * @param string $token
     * @return mixed
     */
    protected function getUserFromToken(string $token): mixed
    {
        // TODO: Decode JWT and get user data
        // Will query database based on user ID from token

        return null;
    }
}