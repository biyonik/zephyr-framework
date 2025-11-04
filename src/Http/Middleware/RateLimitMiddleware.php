<?php

declare(strict_types=1);

namespace Zephyr\Http\Middleware;

use Closure;
use Zephyr\Http\{Request, Response};
use Zephyr\Exceptions\Http\RateLimitException;
use Zephyr\Support\Config;

/**
 * Rate Limit Middleware
 *
 * Protects API from abuse by limiting the number of requests
 * a client can make within a time window.
 *
 * Uses Token Bucket algorithm with file-based storage.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * Maximum requests allowed per window
     */
    protected int $maxAttempts;

    /**
     * Time window in seconds
     */
    protected int $decaySeconds;

    /**
     * Storage path for rate limit data
     */
    protected string $storagePath;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->maxAttempts = (int) Config::get('app.rate_limit_per_minute', 60);
        $this->decaySeconds = 60; // 1 minute
        $this->storagePath = storage_path('framework/rate-limits');

        // Ensure storage directory exists
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Handle rate limiting
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     *
     * @throws RateLimitException If rate limit exceeded
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->resolveRequestSignature($request);

        // Check if rate limit exceeded
        if (!$this->tooManyAttempts($key)) {
            // Increment attempts
            $this->hit($key);

            // Process request
            $response = $next($request);

            // Add rate limit headers
            return $this->addHeaders(
                $response,
                $this->maxAttempts,
                $this->availableAttempts($key)
            );
        }

        // Rate limit exceeded
        throw new RateLimitException(
            'Too many requests. Please slow down.',
            $this->getHeaders($key)
        );
    }

    /**
     * Resolve unique signature for the request
     *
     * Uses IP address as identifier.
     * Can be extended to include user ID, API key, etc.
     *
     * @param Request $request
     * @return string
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $ip = $request->ip();
        $route = $request->path();

        // Create unique key: ip|route
        return sha1($ip . '|' . $route);
    }

    /**
     * Check if too many attempts have been made
     *
     * @param string $key
     * @return bool
     */
    protected function tooManyAttempts(string $key): bool
    {
        return $this->attempts($key) >= $this->maxAttempts;
    }

    /**
     * Increment the counter for a given key
     *
     * @param string $key
     * @return int New attempt count
     */
    protected function hit(string $key): int
    {
        $attempts = $this->attempts($key) + 1;
        $expiresAt = time() + $this->decaySeconds;

        $this->store($key, [
            'attempts' => $attempts,
            'expires_at' => $expiresAt
        ]);

        return $attempts;
    }

    /**
     * Get the number of attempts for a key
     *
     * @param string $key
     * @return int
     */
    protected function attempts(string $key): int
    {
        $data = $this->retrieve($key);

        if (!$data) {
            return 0;
        }

        // Check if expired
        if ($data['expires_at'] < time()) {
            $this->clear($key);
            return 0;
        }

        return $data['attempts'];
    }

    /**
     * Get available attempts remaining
     *
     * @param string $key
     * @return int
     */
    protected function availableAttempts(string $key): int
    {
        return max(0, $this->maxAttempts - $this->attempts($key));
    }

    /**
     * Get seconds until rate limit resets
     *
     * @param string $key
     * @return int
     */
    protected function availableIn(string $key): int
    {
        $data = $this->retrieve($key);

        if (!$data) {
            return 0;
        }

        return max(0, $data['expires_at'] - time());
    }

    /**
     * Store data for a key
     *
     * @param string $key
     * @param array $data
     * @return void
     */
    protected function store(string $key, array $data): void
    {
        $file = $this->getFilePath($key);
        file_put_contents($file, serialize($data), LOCK_EX);
    }

    /**
     * Retrieve data for a key
     *
     * @param string $key
     * @return array|null
     */
    protected function retrieve(string $key): ?array
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $data = unserialize(file_get_contents($file));

        return is_array($data) ? $data : null;
    }

    /**
     * Clear data for a key
     *
     * @param string $key
     * @return void
     */
    protected function clear(string $key): void
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Get file path for a key
     *
     * @param string $key
     * @return string
     */
    protected function getFilePath(string $key): string
    {
        return $this->storagePath . '/' . $key . '.txt';
    }

    /**
     * Add rate limit headers to response
     *
     * @param Response $response
     * @param int $maxAttempts
     * @param int $remainingAttempts
     * @return Response
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        $response->header('X-RateLimit-Limit', (string) $maxAttempts);
        $response->header('X-RateLimit-Remaining', (string) $remainingAttempts);

        return $response;
    }

    /**
     * Get headers for rate limit exception
     *
     * @param string $key
     * @return array
     */
    protected function getHeaders(string $key): array
    {
        $retryAfter = $this->availableIn($key);

        return [
            'X-RateLimit-Limit' => (string) $this->maxAttempts,
            'X-RateLimit-Remaining' => '0',
            'Retry-After' => (string) $retryAfter,
            'X-RateLimit-Reset' => (string) (time() + $retryAfter),
        ];
    }

    /**
     * Clean up expired rate limit files (maintenance)
     *
     * Should be called periodically (e.g., via cron)
     *
     * @return int Number of files cleaned
     */
    public static function cleanup(): int
    {
        $storagePath = storage_path('framework/rate-limits');
        $cleaned = 0;

        if (!is_dir($storagePath)) {
            return 0;
        }

        $files = glob($storagePath . '/*.txt');

        foreach ($files as $file) {
            $data = unserialize(data: file_get_contents($file), options: []);

            if (is_array($data) && isset($data['expires_at']) && $data['expires_at'] < time()) {
                @unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }
}