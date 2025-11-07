<?php

declare(strict_types=1);

namespace Zephyr\Cache;

use Zephyr\Core\App;

/**
 * Cache Yöneticisi (Cache Manager)
 *
 * config/cache.php dosyasını okur ve farklı önbellek sürücülerini
 * (store) yönetir. 'cache()' helper'ı bu sınıfa erişir.
 *
 * @mixin CacheInterface
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class CacheManager
{
    protected App $app;
    protected array $config;

    /**
     * Oluşturulan ve önbelleğe alınan sürücüler (stores).
     * @var array<string, CacheInterface>
     */
    protected array $stores = [];

    /**
     * Varsayılan sürücünün adı (örn: 'file').
     */
    protected string $defaultStore;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->config = $app->resolve('config')->get('cache');
        $this->defaultStore = $this->config['default'] ?? 'file';
    }

    /**
     * Belirtilen (veya varsayılan) sürücüyü (store) döndürür.
     *
     * @param string|null $name Sürücü adı (örn: 'file', 'array')
     * @return CacheInterface
     * @throws \InvalidArgumentException
     */
    public function store(string $name = null): CacheInterface
    {
        $name = $name ?? $this->defaultStore;

        // 1. Önbellekten kontrol et
        if (isset($this->stores[$name])) {
            return $this->stores[$name];
        }

        // 2. Konfigürasyonu al
        $config = $this->config['stores'][$name] ?? null;
        if (is_null($config)) {
            throw new \InvalidArgumentException("Cache store [{$name}] bulunamadı.");
        }

        // 3. Sürücüyü oluştur
        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';
        if (!method_exists($this, $driverMethod)) {
            throw new \InvalidArgumentException("Cache sürücüsü [{$config['driver']}] desteklenmiyor.");
        }

        // 4. Sürücüyü oluştur ve önbelleğe al
        return $this->stores[$name] = $this->$driverMethod($config);
    }

    /**
     * 'file' sürücüsünü (FileCache) oluşturur.
     */
    protected function createFileDriver(array $config): CacheInterface
    {
        // Mevcut Zephyr\Cache\FileCache sınıfımızı kullanıyoruz
        return new FileCache($config['path']);
    }

    /**
     * 'array' sürücüsünü (ArrayCache) oluşturur.
     */
    protected function createArrayDriver(array $config): CacheInterface
    {
        // Yeni Zephyr\Cache\ArrayCache sınıfımızı kullanıyoruz
        return new ArrayCache();
    }

    /**
     * Varsayılan sürücüyü (store) alır.
     */
    public function getDefaultDriver(): CacheInterface
    {
        return $this->store($this->defaultStore);
    }

    /**
     * Çağrıları (get, set, has vb.) varsayılan sürücüye yönlendirir.
     * Bu, cache()->get('key') gibi kullanımları sağlar.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->getDefaultDriver()->{$method}(...$parameters);
    }
}