<?php

declare(strict_types=1);

namespace Zephyr\Providers;

use Zephyr\Core\App;
use Zephyr\Auth\AuthManager;
use Zephyr\Auth\EloquentUserProvider;
use Zephyr\Auth\JwtManager;
use Zephyr\Auth\Contracts\UserProvider;
use Zephyr\Support\Config;

class AuthServiceProvider
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Auth servislerini container'a kaydeder.
     */
    public function register(): void
    {
        // 1. JwtManager'ı singleton olarak kaydet
        $this->app->singleton(JwtManager::class);

        // 2. UserProvider arayüzünü, Eloquent implementasyonuna bağla
        $this->app->singleton(UserProvider::class, function () {
            // config/auth.php'den User modelini okumamız lazım (Henüz yok, ekleyeceğiz)
            $modelClass = Config::get('auth.provider.model', \App\Models\User::class);
            return new EloquentUserProvider($modelClass);
        });

        // 3. Ana AuthManager'ı singleton olarak kaydet
        // Bağımlılıkları (JwtManager, UserProvider) otomatik olarak çekecek
        $this->app->singleton(AuthManager::class);
        
        // 'auth' alias'ı (takma adı) ekleyelim
        $this->app->bind('auth', fn($app) => $app->resolve(AuthManager::class));
    }

    public function boot(): void
    {
        // Boot işlemleri (gerekirse)
    }
}