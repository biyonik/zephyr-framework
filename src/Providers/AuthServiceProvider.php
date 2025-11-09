<?php

declare(strict_types=1);

namespace Zephyr\Providers;

use App\Models\User;
use Zephyr\Core\App;
use Zephyr\Auth\AuthManager;
use Zephyr\Auth\EloquentUserProvider;
use Zephyr\Auth\JwtManager;
use Zephyr\Auth\Contracts\UserProvider;
use Zephyr\Auth\Gate; // <-- YENİ
use Zephyr\Support\Config;

// Örnek modeller (kullanıcı tanımları için)
// use App\Models\User;
// use App\Models\Post;

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
            $modelClass = Config::get('auth.provider.model', User::class);
            return new EloquentUserProvider($modelClass);
        });

        // 3. Ana AuthManager'ı singleton olarak kaydet
        // Bağımlılıkları (JwtManager, UserProvider) otomatik olarak çekecek
        $this->app->singleton(AuthManager::class);

        // 4. YENİ: Gate (Yetkilendirme) sınıfını singleton olarak kaydet
        $this->app->singleton(Gate::class, function ($app) {
            return new Gate(
                $app->resolve(AuthManager::class),
                Config::get('auth.provider.model', User::class)
            );
        });

        // 'auth' alias'ı (takma adı) ekleyelim
        $this->app->bind('auth', fn($app) => $app->resolve(AuthManager::class));
    }

    /**
     * Servisleri (config'leri okuyarak) başlatır.
     *
     * YETKİLENDİRME (AUTHORIZATION) KURALLARI BURADA TANIMLANIR.
     */
    public function boot(): void
    {
        // Gate sınıfını konteynerden al
        $gate = $this->app->resolve(Gate::class);

        /*
        |--------------------------------------------------------------------------
        | Yetkilendirme (Gate) Kuralları
        |--------------------------------------------------------------------------
        |
        | Uygulamanızın yetki kontrollerini burada tanımlayın.
        |
        | Örnek:
        | $gate->define('update-post', function (User $user, Post $post) {
        |     // Sadece admin VEYA gönderinin sahibi güncelleyebilir
        |     return $user->isAdmin() || $user->id === $post->user_id;
        | });
        |
        | Controller içinde kullanımı:
        | authorize('update-post', $post); // Yetkisi yoksa 403 fırlatır
        |
        */

        // Örnek: Sadece 'admin' rolüne sahip kullanıcılar
        // $gate->define('view-admin-panel', function (User $user) {
        //     // User modelinize bir isAdmin() metodu eklemeniz gerekir
        //     return $user->isAdmin();
        // });
    }
}