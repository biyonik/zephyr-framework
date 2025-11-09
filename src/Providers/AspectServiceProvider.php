<?php

declare(strict_types=1);

namespace Zephyr\Providers;

use Zephyr\Core\App;
use Zephyr\AOP\AspectManager;
use Zephyr\AOP\ProxyFactory;
use Zephyr\AOP\CacheableAspect;
use Zephyr\AOP\LoggableAspect;
use Zephyr\AOP\TransactionalAspect;

/**
 * AOP (Aspect-Oriented Programming) Servis Sağlayıcısı
 *
 * ProxyFactory'yi ve tüm Aspect işleyicilerini (Loggable, Cacheable vb.)
 * DI Konteynerine kaydeder.
 */
class AspectServiceProvider
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function register(): void
    {
        // 1. Ana Yöneticileri singleton olarak kaydet
        $this->app->singleton(AspectManager::class, function ($app) {
            return new AspectManager($app);
        });

        $this->app->singleton(ProxyFactory::class, function ($app) {
            return new ProxyFactory(
                $app,
                $app->resolve(AspectManager::class)
            );
        });

        // 2. Tüm Aspect İşleyicilerini kaydet
        // (Bu sınıflar kendi bağımlılıklarını DI üzerinden alacaklar)
        $this->app->singleton(CacheableAspect::class);
        $this->app->singleton(LoggableAspect::class);
        $this->app->singleton(TransactionalAspect::class);
    }

    public function boot(): void
    {
        // Boot işlemi gerekmiyor
    }
}