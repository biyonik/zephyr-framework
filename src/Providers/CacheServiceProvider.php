<?php

declare(strict_types=1);

namespace Zephyr\Providers;

use Zephyr\Core\App;
use Zephyr\Cache\CacheManager;
use Zephyr\Cache\CacheInterface;

/**
 * Cache Servis Sağlayıcısı
 *
 * CacheManager'ı ve arayüzlerini container'a kaydeder.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class CacheServiceProvider
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Servisleri container'a kaydeder.
     */
    public function register(): void
    {
        // 1. Ana CacheManager'ı singleton olarak kaydet
        $this->app->singleton(CacheManager::class, function ($app) {
            return new CacheManager($app);
        });

        // 2. Temel CacheInterface'i, CacheManager'ın *varsayılan*
        //    sürücüsüne bağla. (DI için)
        $this->app->bind(CacheInterface::class, function ($app) {
            return $app->resolve(CacheManager::class)->getDefaultDriver();
        });

        // 3. 'cache' alias'ını (takma ad) kaydet
        // Bu, cache() helper'ının app('cache') yapabilmesi içindir
        $this->app->bind('cache', function ($app) {
            return $app->resolve(CacheManager::class);
        });
    }

    public function boot(): void
    {
        // Boot işlemleri (gerekirse)
    }
}