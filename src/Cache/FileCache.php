<?php

declare(strict_types=1);

namespace Zephyr\Cache;

/**
 * File-Based Cache
 *
 * Simple file-based cache implementation for shared hosting environments.
 * Uses filesystem for storage - no extensions required.
 *
 * @author Ahmet ALTUN
 * @email ahmet.altun60@gmail.com
 * @github github.com/biyonik
 */
class FileCache implements CacheInterface
{
    /**
     * Cache storage directory
     */
    private string $storageDir;

    /**
     * Constructor
     *
     * @param string $storageDir Path to cache directory
     */
    public function __construct(string $storageDir)
    {
        $this->storageDir = rtrim($storageDir, '/');

        // Ensure directory exists
        if (!is_dir($this->storageDir) && !mkdir($concurrentDirectory = $this->storageDir, 0755, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $contents = @file_get_contents($file);
        if ($contents === false) {
            return $default;
        }

        $data = @unserialize($contents);
        if ($data === false) {
            return $default;
        }

        // Check expiration
        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            // Expired - delete and return default
            @unlink($file);
            return $default;
        }

        return $data['value'] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $file = $this->getFilePath($key);

        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl
        ];

        $tempFile = $file . '.tmp';

        // Write to temp file
        if (file_put_contents($tempFile, serialize($data)) === false) {
            return false;
        }

        // Atomic rename
        return @rename($tempFile, $file);
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function forget(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return true;
        }

        return @unlink($file);
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): bool
    {
        $files = glob($this->storageDir . '/*.cache');

        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            @unlink($file);
        }

        return true;
    }

    /**
     * Get file path for cache key
     *
     * @param string $key Cache key
     * @return string Full file path
     */
    private function getFilePath(string $key): string
    {
        $hash = hash('xxh3', $key);
        return $this->storageDir . "/{$hash}.cache";
    }

    /**
     * Cleanup expired cache files
     *
     * Can be called manually or by maintenance system.
     *
     * @return array Statistics
     */
    public function cleanup(): array
    {
        $files = glob($this->storageDir . '/*.cache');

        if ($files === false) {
            return ['cleaned' => 0, 'scanned' => 0];
        }

        $now = time();
        $cleaned = 0;
        $scanned = 0;

        foreach ($files as $file) {
            $scanned++;

            $contents = @file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            $data = @unserialize($contents);
            if ($data === false) {
                continue;
            }

            if (isset($data['expires_at']) && $data['expires_at'] < $now) {
                if (@unlink($file)) {
                    $cleaned++;
                }
            }
        }

        return [
            'cleaned' => $cleaned,
            'scanned' => $scanned
        ];
    }
}