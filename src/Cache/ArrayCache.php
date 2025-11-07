<?php

declare(strict_types=1);

namespace Zephyr\Cache;

/**
 * Array Cache (Testler ve Geliştirme için)
 *
 * CacheInterface'i implemente eder, veriyi request süresince hafızada tutar.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class ArrayCache implements CacheInterface
{
    /**
     * Hafıza-içi depolama alanı.
     * @var array<string, array{value: mixed, expires: int}>
     */
    private array $storage = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }
        return $this->storage[$key]['value'] ?? $default;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $this->storage[$key] = [
            'value' => $value,
            'expires' => time() + $ttl,
        ];
        return true;
    }

    public function has(string $key): bool
    {
        if (!isset($this->storage[$key])) {
            return false;
        }

        // Süresi dolmuş mu kontrol et
        if (time() > $this->storage[$key]['expires']) {
            $this->forget($key);
            return false;
        }

        return true;
    }

    public function forget(string $key): bool
    {
        unset($this->storage[$key]);
        return true;
    }

    public function flush(): bool
    {
        $this->storage = [];
        return true;
    }
}