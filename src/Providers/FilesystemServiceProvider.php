<?php

declare(strict_types=1);

namespace Zephyr\Providers;

use Zephyr\Core\App;
use Zephyr\Filesystem\FilesystemManager;
use Zephyr\Filesystem\FilesystemInterface;

/**
 * Dosya Sistemi Servis Sağlayıcısı
 *
 * FilesystemManager'ı container'a kaydeder.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class FilesystemServiceProvider
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
        // 1. Ana FilesystemManager'ı singleton olarak kaydet
        $this->app->singleton(FilesystemManager::class, function ($app) {
            return new FilesystemManager($app);
        });

        // 2. 'storage' alias'ını (takma ad) kaydet
        // Bu, storage() helper'ının app('storage') yapabilmesi içindir
        $this->app->bind('storage', function ($app) {
            return $app->resolve(FilesystemManager::class);
        });
    }
}