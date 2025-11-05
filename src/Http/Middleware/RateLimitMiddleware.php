<?php

declare(strict_types=1);

namespace Zephyr\Http\Middleware;

use Closure;
use Zephyr\Http\Request;
use Zephyr\Http\Response;
use Zephyr\Support\Config;
use Zephyr\Support\Maintenance;

/**
 * Rate Limit Middleware
 *
 * Implements token bucket algorithm with file-based storage.
 * Features lazy cleanup and probabilistic garbage collection.
 *
 * @author Ahmet ALTUN
 * @email ahmet.altun60@gmail.com
 * @github github.com/biyonik
 */
class RateLimitMiddleware
{
    private string $storageDir;
    private int $maxAttempts;
    private int $decayMinutes;

    public function __construct()
    {
        $config = Config::get('rate_limit');
        $this->storageDir = $config['storage_path'];
        $this->maxAttempts = $config['max_attempts'];
        $this->decayMinutes = $config['decay_minutes'];

        // Ensure directory exists
        if (!is_dir($this->storageDir) && !mkdir($concurrentDirectory = $this->storageDir, 0755, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
    }

    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->resolveRequestSignature($request);
        $ttl = $this->decayMinutes * 60;
        $expiresAt = time() + $ttl;

        // Get file path with embedded TTL
        $file = $this->getFilePath($key, $expiresAt);

        // LAZY CLEANUP: Check if file exists and is expired
        $data = $this->readFileWithLazyCleanup($file);

        // Initialize or get current attempts
        $attempts = $data['attempts'] ?? 0;
        $resetAt = $data['reset_at'] ?? $expiresAt;

        // Check if window has expired (reset counter)
        if ($resetAt <= time()) {
            $attempts = 0;
            $resetAt = $expiresAt;
        }

        // Increment attempts
        $attempts++;

        // Check if rate limit exceeded
        if ($attempts > $this->maxAttempts) {
            $retryAfter = $resetAt - time();

            return Response::json([
                'error' => 'Too many requests',
                'message' => 'Rate limit exceeded',
                'retry_after' => $retryAfter
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $this->maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => $resetAt,
                'Retry-After' => $retryAfter
            ]);
        }

        // Save updated data
        $this->writeFile($file, [
            'attempts' => $attempts,
            'reset_at' => $resetAt,
            'expires_at' => $expiresAt
        ]);

        // Continue to next middleware/controller
        $response = $next($request);

        // Add rate limit headers
        $remaining = max(0, $this->maxAttempts - $attempts);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $this->maxAttempts,
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => $resetAt
        ]);
    }

    /**
     * Generate unique request signature
     *
     * Combines IP, method, and path to create unique identifier.
     */
    private function resolveRequestSignature(Request $request): string
    {
        $ip = $request->ip();
        $method = $request->method();
        $path = $request->path();

        return "{$ip}:{$method}:{$path}";
    }

    /**
     * Get file path with embedded expires_at timestamp
     *
     * Format: {hash}_{expires_at}.json
     * This allows quick filtering without reading file contents.
     *
     * @param string $key Request signature
     * @param int $expiresAt Expiration timestamp
     * @return string Full file path
     */
    private function getFilePath(string $key, int $expiresAt): string
    {
        // Use xxHash for fast hashing (PHP 8.1+)
        $hash = hash('xxh3', $key);

        return $this->storageDir . "/{$hash}_{$expiresAt}.json";
    }

    /**
     * Extract expires_at from filename
     *
     * @param string $filename Base filename (not full path)
     * @return int|null Expiration timestamp or null if not found
     */
    private function extractExpiresAt(string $filename): ?int
    {
        // Pattern: {hash}_{timestamp}.json
        if (preg_match('/_(\d+)\.json$/', $filename, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Read file with lazy cleanup
     *
     * If file is expired, delete it and return null.
     * This is "lazy" because cleanup happens during normal operation.
     *
     * @param string $file Full file path
     * @return array|null File data or null if not exists/expired
     */
    private function readFileWithLazyCleanup(string $file): ?array
    {
        if (!file_exists($file)) {
            return null;
        }

        // Extract expires_at from filename (FAST - no file I/O)
        $basename = basename($file);
        $expiresAt = $this->extractExpiresAt($basename);

        // Check if expired based on filename
        if ($expiresAt !== null && $expiresAt < time()) {
            // LAZY CLEANUP: Delete expired file
            @unlink($file);

            Maintenance::log("Lazy cleanup: Deleted expired rate limit file", [
                'file' => $basename,
                'expired_at' => date('Y-m-d H:i:s', $expiresAt)
            ]);

            return null;
        }

        // Read and parse file
        $contents = @file_get_contents($file);
        if ($contents === false) {
            return null;
        }

        $data = json_decode($contents, true);

        // Fallback: Check expires_at from file contents (if filename parse failed)
        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            @unlink($file);
            return null;
        }

        return $data;
    }

    /**
     * Write data to file atomically
     *
     * Uses atomic write (write to temp, then rename) to prevent corruption.
     */
    private function writeFile(string $file, array $data): void
    {
        $tempFile = $file . '.tmp';

        // Write to temp file
        file_put_contents($tempFile, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        // Atomic rename
        rename($tempFile, $file);
    }

    /**
     * Cleanup expired rate limit files
     *
     * This is a "chunked" cleanup - processes limited number of files
     * to prevent timeout and excessive resource usage.
     *
     * Called probabilistically by App::terminate() or can be manually triggered.
     *
     * @return array Statistics about cleanup operation
     * @throws \JsonException
     */
    public static function cleanup(): array
    {
        $config = Config::get('rate_limit');
        $storageDir = $config['storage_path'];

        if (!is_dir($storageDir)) {
            return [
                'success' => true,
                'cleaned' => 0,
                'scanned' => 0,
                'message' => 'Storage directory does not exist'
            ];
        }

        $limits = Config::get('maintenance.limits');
        $maxExecutionTime = $limits['max_execution_time'];
        $maxFilesPerCycle = $limits['max_files_per_cycle'];

        $startTime = time();
        $startMicrotime = microtime(true);
        $now = time();

        $stats = [
            'cleaned' => 0,
            'scanned' => 0,
            'errors' => 0,
            'skipped' => 0,
        ];

        // Get all files in storage directory
        $files = glob($storageDir . '/*.json');

        if ($files === false) {
            return [
                'success' => false,
                'message' => 'Failed to scan storage directory',
                ...$stats
            ];
        }

        foreach ($files as $file) {
            // TIMEOUT PROTECTION: Check if we've exceeded max execution time
            if ((time() - $startTime) >= $maxExecutionTime) {
                Maintenance::log("Cleanup timeout after {$maxExecutionTime}s", $stats);
                break;
            }

            // CHUNK LIMIT: Stop after processing max files
            if ($stats['scanned'] >= $maxFilesPerCycle) {
                Maintenance::log("Cleanup reached file limit: {$maxFilesPerCycle}", $stats);
                break;
            }

            $stats['scanned']++;

            $basename = basename($file);

            // Extract expires_at from filename
            if (preg_match('/_(\d+)\.json$/', $basename, $matches)) {
                $expiresAt = (int) $matches[1];

                // Is expired?
                if ($expiresAt < $now) {
                    if (@unlink($file)) {
                        $stats['cleaned']++;
                    } else {
                        $stats['errors']++;
                        Maintenance::log("Failed to delete file: {$basename}");
                    }
                } else {
                    $stats['skipped']++;
                }
            } else {
                // Filename doesn't match pattern - might be old format
                // Try to read and check expires_at from content
                $data = @json_decode(file_get_contents($file), true);

                if ($data && isset($data['expires_at']) && $data['expires_at'] < $now) {
                    if (@unlink($file)) {
                        $stats['cleaned']++;
                    } else {
                        $stats['errors']++;
                    }
                } else {
                    $stats['skipped']++;
                }
            }
        }

        $duration = round(microtime(true) - $startMicrotime, 3);

        $result = [
            'success' => true,
            'duration_seconds' => $duration,
            'total_files' => count($files),
            ...$stats
        ];

        if ($stats['cleaned'] > 0) {
            Maintenance::log("RateLimit cleanup completed", $result);
        }

        return $result;
    }
}