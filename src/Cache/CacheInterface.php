<?php

declare(strict_types=1);

namespace Zephyr\Cache;

/**
 * Cache Interface
 *
 * Simple contract for cache implementations.
 *
 * @author Ahmet ALTUN
 * @email ahmet.altun60@gmail.com
 * @github github.com/biyonik
 */
interface CacheInterface
{
    /**
     * Retrieve an item from the cache
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store an item in the cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int $ttl Time to live in seconds
     * @return bool Success status
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool;

    /**
     * Check if item exists in cache
     *
     * @param string $key Cache key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Remove an item from the cache
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    public function forget(string $key): bool;

    /**
     * Clear all cached items
     *
     * @return bool Success status
     */
    public function flush(): bool;
}