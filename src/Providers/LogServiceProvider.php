<?php

declare(strict_types=1);

namespace Zephyr\Providers;

use Zephyr\Core\App;
use Zephyr\Logging\LogManager;
use Psr\Log\LoggerInterface;
use Zephyr\Logging\Processors\RequestProcessor;

/**
 * Loglama Servis Sağlayıcısı
 *
 * LogManager'ı ve PSR-3 arayüzünü container'a kaydeder.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class LogServiceProvider
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function register(): void
    {
        // 1. Ana LogManager'ı singleton olarak kaydet
        $this->app->singleton(LogManager::class, function ($app) {
            // LogManager'ın App container'ına ihtiyacı var
            return new LogManager($app);
        });

        $this->app->singleton(RequestProcessor::class, function ($app) {
            return new RequestProcessor($app);
        });

        // 2. PSR-3 arayüzünü LogManager'a bağla
        // Bu sayede DI ile LoggerInterface istendiğinde LogManager gelir
        $this->app->bind(LoggerInterface::class, function ($app) {
            return $app->resolve(LogManager::class);
        });

        // 3. 'log' alias'ını (takma ad) kaydet
        // Bu, log() helper'ının app('log') yapabilmesi içindir
        $this->app->bind('log', function ($app) {
            return $app->resolve(LogManager::class);
        });
    }

    public function boot(): void
    {
        // Boot işlemleri (gerekirse)
    }
}