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
class RateLimitMiddleware implements \Zephyr\Http\Middleware\MiddlewareInterface
{
    private string $storageDir;
    private int $maxAttempts;
    private int $decayMinutes;
    private array $skipConfig;
    private bool $enabled;

    public function __construct()
    {
        // *** DEFENSIVE: Get config with fallback ***
        $config = Config::get('rate_limit');
        
        if (!is_array($config)) {
            // Config not loaded - use safe defaults
            $this->enabled = false;
            $this->storageDir = sys_get_temp_dir() . '/zephyr-rate-limits';
            $this->maxAttempts = 60;
            $this->decayMinutes = 1;
            $this->skipConfig = ['ips' => [], 'local_env' => true];
            
            error_log('WARNING: rate_limit config not found, rate limiting disabled');
            return;
        }
        
        $this->enabled = $config['enabled'] ?? true;
        $this->storageDir = $config['storage_path'] ?? sys_get_temp_dir() . '/zephyr-rate-limits';
        $this->maxAttempts = $config['max_attempts'] ?? 60;
        $this->decayMinutes = $config['decay_minutes'] ?? 1;
        $this->skipConfig = $config['skip'] ?? ['ips' => [], 'local_env' => true];

        // Ensure directory exists
        if (!is_dir($this->storageDir)) {
            if (!@mkdir($this->storageDir, 0755, true) && !is_dir($this->storageDir)) {
                error_log("WARNING: Could not create rate limit storage directory: {$this->storageDir}");
                $this->enabled = false;
            }
        }
    }

    public function handle(Request $request, Closure $next): Response
    {
        // *** DEFENSIVE: Check if enabled ***
        if (!$this->enabled) {
            return $next($request);
        }
        
        // Check skip conditions
        if ($this->shouldSkip($request)) {
            return $next($request);
        }
        
        // Check for route-specific limits
        $this->applyRouteSpecificLimits($request);
        
        $key = $this->resolveRequestSignature($request);
        
        // Calculate window start time (rounded to decay window)
        $windowStartTime = $this->calculateWindowStart();
        $expiresAt = $windowStartTime + ($this->decayMinutes * 60);
        
        // Get file path with window start time
        $file = $this->getFilePath($key, $windowStartTime);

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
            $retryAfter = max(0, $resetAt - time());

            return Response::json([
                'error' => 'Too many requests',
                'message' => 'Rate limit exceeded',
                'retry_after' => $retryAfter
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => (string)$this->maxAttempts,
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset' => (string)$resetAt,
                'Retry-After' => (string)$retryAfter
            ]);
        }

        // Save updated data with file lock to prevent race conditions
        $this->writeFileWithLock($file, [
            'attempts' => $attempts,
            'reset_at' => $resetAt,
            'expires_at' => $expiresAt,
            'window_start' => $windowStartTime
        ]);

        // Continue to next middleware/controller
        $response = $next($request);

        // Add rate limit headers
        $remaining = max(0, $this->maxAttempts - $attempts);

        return $response->withHeaders([
            'X-RateLimit-Limit' => (string)$this->maxAttempts,
            'X-RateLimit-Remaining' => (string)$remaining,
            'X-RateLimit-Reset' => (string)$resetAt
        ]);
    }
    
    /**
     * Check if rate limiting should be skipped for this request
     */
    private function shouldSkip(Request $request): bool
    {
        // Skip in local environment
        if (($this->skipConfig['local_env'] ?? false)) {
            $env = Config::get('app.env', 'production');
            if ($env === 'local' || $env === 'development') {
                return true;
            }
        }
        
        // Skip for whitelisted IPs
        $skipIps = $this->skipConfig['ips'] ?? [];
        if (!empty($skipIps)) {
            $clientIp = $request->ip();
            if (in_array($clientIp, $skipIps, true)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Apply route-specific rate limits if defined
     */
    private function applyRouteSpecificLimits(Request $request): void
    {
        $routes = Config::get('rate_limit.routes', []);
        
        if (!is_array($routes)) {
            return;
        }
        
        $path = $request->path();
        
        if (isset($routes[$path]) && is_array($routes[$path])) {
            $routeConfig = $routes[$path];
            
            if (isset($routeConfig['max_attempts'])) {
                $this->maxAttempts = (int)$routeConfig['max_attempts'];
            }
            
            if (isset($routeConfig['decay_minutes'])) {
                $this->decayMinutes = (int)$routeConfig['decay_minutes'];
            }
        }
    }
    
    /**
     * Calculate window start time
     * 
     * Rounds down to the nearest decay window.
     * Example: If decay is 1 minute, rounds to minute boundary.
     */
    private function calculateWindowStart(): int
    {
        $decaySeconds = $this->decayMinutes * 60;
        return (int)(floor(time() / $decaySeconds) * $decaySeconds);
    }

    /**
     * Generate unique request signature
     */
    private function resolveRequestSignature(Request $request): string
    {
        $strategy = Config::get('rate_limit.signature', 'ip_and_route');
        
        return match($strategy) {
            'ip' => $request->ip(),
            'user' => $this->getUserIdentifier($request),
            'ip_and_route' => $request->ip() . ':' . $request->method() . ':' . $request->path(),
            default => $request->ip() . ':' . $request->method() . ':' . $request->path()
        };
    }
    
    /**
     * Get user identifier (for authenticated requests)
     */
    private function getUserIdentifier(Request $request): string
    {
        // If user is authenticated, use user ID
        $user = $request->user();
        if ($user && isset($user->id)) {
            return 'user:' . $user->id;
        }
        
        // Fallback to IP
        return 'guest:' . $request->ip();
    }

    /**
     * Get file path with embedded window start timestamp
     */
    private function getFilePath(string $key, int $windowStart): string
    {
        $hash = hash('xxh3', $key);
        return $this->storageDir . "/{$hash}_{$windowStart}.json";
    }

    /**
     * Extract window start from filename
     */
    private function extractWindowStart(string $filename): ?int
    {
        if (preg_match('/_(\d+)\.json$/', $filename, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    /**
     * Read file with lazy cleanup
     */
    private function readFileWithLazyCleanup(string $file): ?array
    {
        if (!file_exists($file)) {
            return null;
        }

        $basename = basename($file);
        $windowStart = $this->extractWindowStart($basename);

        if ($windowStart !== null) {
            $expiresAt = $windowStart + ($this->decayMinutes * 60);
            
            if ($expiresAt < time()) {
                @unlink($file);
                
                Maintenance::log("Lazy cleanup: Deleted expired rate limit file", [
                    'file' => $basename,
                    'expired_at' => date('Y-m-d H:i:s', $expiresAt)
                ]);

                return null;
            }
        }

        $fp = @fopen($file, 'r');
        if ($fp === false) {
            return null;
        }
        
        if (!flock($fp, LOCK_SH)) {
            fclose($fp);
            return null;
        }
        
        $contents = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        
        if ($contents === false) {
            return null;
        }

        $data = json_decode($contents, true);

        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            @unlink($file);
            return null;
        }

        return $data;
    }

    /**
     * Write data to file atomically with file lock
     */
    private function writeFileWithLock(string $file, array $data): void
    {
        if (file_exists($file)) {
            $fp = @fopen($file, 'r+');
            if ($fp === false) {
                $this->writeFile($file, $data);
                return;
            }
            
            if (flock($fp, LOCK_EX)) {
                $contents = stream_get_contents($fp);
                $currentData = $contents ? @json_decode($contents, true) : null;
                
                if ($currentData && isset($currentData['attempts'])) {
                    $data['attempts'] = max($data['attempts'], $currentData['attempts'] + 1);
                }
                
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
                
                flock($fp, LOCK_UN);
            }
            
            fclose($fp);
        } else {
            $this->writeFile($file, $data);
        }
    }
    
    /**
     * Write data to file atomically
     */
    private function writeFile(string $file, array $data): void
    {
        $tempFile = $file . '.tmp.' . uniqid('', true);
        @file_put_contents($tempFile, json_encode($data, JSON_PRETTY_PRINT));
        @rename($tempFile, $file);
    }

    /**
     * Cleanup expired rate limit files
     */
    public static function cleanup(): array
    {
        // *** DEFENSIVE: Get config with fallback ***
        $config = Config::get('rate_limit');
        
        if (!is_array($config)) {
            return [
                'success' => false,
                'message' => 'rate_limit config not loaded',
                'cleaned' => 0,
                'scanned' => 0
            ];
        }
        
        $storageDir = $config['storage_path'] ?? sys_get_temp_dir() . '/zephyr-rate-limits';

        if (!is_dir($storageDir)) {
            return [
                'success' => true,
                'cleaned' => 0,
                'scanned' => 0,
                'message' => 'Storage directory does not exist'
            ];
        }

        $limits = Config::get('maintenance.limits', [
            'max_execution_time' => 5,
            'max_files_per_cycle' => 1000
        ]);
        
        $maxExecutionTime = $limits['max_execution_time'] ?? 5;
        $maxFilesPerCycle = $limits['max_files_per_cycle'] ?? 1000;

        $startTime = time();
        $startMicrotime = microtime(true);
        $now = time();

        $stats = [
            'cleaned' => 0,
            'scanned' => 0,
            'errors' => 0,
            'skipped' => 0,
        ];

        $files = @glob($storageDir . '/*.json');

        if ($files === false) {
            return [
                'success' => false,
                'message' => 'Failed to scan storage directory',
                ...$stats
            ];
        }

        $decayMinutes = $config['decay_minutes'] ?? 1;

        foreach ($files as $file) {
            if ((time() - $startTime) >= $maxExecutionTime) {
                Maintenance::log("Cleanup timeout after {$maxExecutionTime}s", $stats);
                break;
            }

            if ($stats['scanned'] >= $maxFilesPerCycle) {
                Maintenance::log("Cleanup reached file limit: {$maxFilesPerCycle}", $stats);
                break;
            }

            $stats['scanned']++;
            $basename = basename($file);

            if (preg_match('/_(\d+)\.json$/', $basename, $matches)) {
                $windowStart = (int) $matches[1];
                $expiresAt = $windowStart + ($decayMinutes * 60);

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