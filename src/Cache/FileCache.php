<?php

declare(strict_types=1);

namespace Zephyr\Cache;

/**
 * File-Based Cache - FIXED (Race Condition Safe)
 *
 * Thread-safe file locking ile data corruption'ı önler.
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
     * Maximum lock wait time (seconds)
     */
    private int $maxLockWaitTime = 5;

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

        // Shared lock (okuma için)
        $fp = @fopen($file, 'r');
        if ($fp === false) {
            return $default;
        }

        // Shared lock al (birden fazla okuma yapılabilir)
        if (!$this->acquireLock($fp, LOCK_SH)) {
            fclose($fp);
            return $default;
        }

        try {
            $contents = stream_get_contents($fp);

            if ($contents === false) {
                return $default;
            }

            $data = @unserialize($contents);
            if ($data === false) {
                return $default;
            }

            // Check expiration
            if (isset($data['expires_at']) && $data['expires_at'] < time()) {
                // Lock'u serbest bırak, sonra sil
                flock($fp, LOCK_UN);
                fclose($fp);
                @unlink($file);
                return $default;
            }

            return $data['value'] ?? $default;

        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
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

        // Exclusive lock ile atomic write
        $fp = @fopen($file, 'c');
        if ($fp === false) {
            return false;
        }

        // Exclusive lock al (kimse okuyamaz/yazamaz)
        if (!$this->acquireLock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }

        try {
            // Dosyayı baştan yaz
            ftruncate($fp, 0);
            rewind($fp);

            $written = fwrite($fp, serialize($data));

            if ($written === false) {
                return false;
            }

            // Disk'e flush et (gerçekten yazıldığından emin ol)
            fflush($fp);

            return true;

        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
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

        // Silme işlemi için exclusive lock
        $fp = @fopen($file, 'r+');
        if ($fp === false) {
            return @unlink($file); // Lock alamadıysak direkt dene
        }

        if (!$this->acquireLock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }

        fclose($fp);
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

        $success = true;
        foreach ($files as $file) {
            if (!$this->forget(basename($file, '.cache'))) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get file path for cache key
     *
     * @param string $key Cache key
     * @return string Full file path
     */
    private function getFilePath(string $key): string
    {
        // xxh3 daha hızlı (PHP 8.1+), yoksa md5 kullan
        $hash = function_exists('hash') && in_array('xxh3', hash_algos())
            ? hash('xxh3', $key)
            : md5($key);

        return $this->storageDir . "/{$hash}.cache";
    }

    /**
     * Lock'u belirtilen timeout içinde almaya çalışır
     *
     * @param resource $fp File pointer
     * @param int $operation LOCK_SH veya LOCK_EX
     * @return bool Lock başarılı mı?
     */
    private function acquireLock($fp, int $operation): bool
    {
        $start = microtime(true);
        $wouldBlock = false;

        while (true) {
            if (flock($fp, $operation | LOCK_NB, $wouldBlock)) {
                return true;
            }

            // Timeout kontrolü
            if (microtime(true) - $start > $this->maxLockWaitTime) {
                return false;
            }

            // Bir süre bekle (exponential backoff)
            $waitTime = min(100000, 10000 * pow(2, (microtime(true) - $start)));
            usleep((int)$waitTime);
        }
    }

    /**
     * Cleanup expired cache files
     *
     * Thread-safe cleanup implementation.
     *
     * @return array Statistics
     */
    public function cleanup(): array
    {
        $files = glob($this->storageDir . '/*.cache');

        if ($files === false) {
            return ['cleaned' => 0, 'scanned' => 0, 'errors' => 0];
        }

        $now = time();
        $cleaned = 0;
        $scanned = 0;
        $errors = 0;

        foreach ($files as $file) {
            $scanned++;

            // Shared lock ile oku
            $fp = @fopen($file, 'r');
            if ($fp === false) {
                $errors++;
                continue;
            }

            if (!$this->acquireLock($fp, LOCK_SH)) {
                fclose($fp);
                $errors++;
                continue;
            }

            $contents = stream_get_contents($fp);
            flock($fp, LOCK_UN);
            fclose($fp);

            if ($contents === false) {
                $errors++;
                continue;
            }

            $data = @unserialize($contents);
            if ($data === false) {
                // Bozuk dosya, sil
                if (@unlink($file)) {
                    $cleaned++;
                }
                continue;
            }

            // Süresi dolmuş mu?
            if (isset($data['expires_at']) && $data['expires_at'] < $now) {
                if ($this->forget(basename($file, '.cache'))) {
                    $cleaned++;
                } else {
                    $errors++;
                }
            }
        }

        return [
            'cleaned' => $cleaned,
            'scanned' => $scanned,
            'errors' => $errors,
        ];
    }
}