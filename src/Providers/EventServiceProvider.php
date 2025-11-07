<?php

declare(strict_types=1);

namespace Zephyr\Providers;

use Zephyr\Core\App;
use Zephyr\Events\EventDispatcher;

/**
 * Event/Listener Servis Sağlayıcısı
 *
 * EventDispatcher'ı container'a kaydeder ve
 * config/events.php dosyasındaki eşleşmeleri yükler.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class EventServiceProvider
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
        // 1. EventDispatcher'ı singleton olarak kaydet
        $this->app->singleton(EventDispatcher::class, function ($app) {
            return new EventDispatcher($app);
        });

        // 2. 'event' alias'ını (takma ad) kaydet
        $this->app->bind('event', function ($app) {
            return $app->resolve(EventDispatcher::class);
        });
    }

    /**
     * Servisleri (config'leri okuyarak) başlatır.
     */
    public function boot(): void
    {
        // config/events.php dosyasındaki tüm eşleşmeleri yükle
        $eventsConfig = $this->app->resolve('config')->get('events', []);

        $dispatcher = $this->app->resolve(EventDispatcher::class);

        foreach ($eventsConfig as $event => $listeners) {
            foreach ($listeners as $listener) {
                $dispatcher->listen($event, $listener);
            }
        }
    }
}