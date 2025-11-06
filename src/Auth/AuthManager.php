<?php

declare(strict_types=1);

namespace Zephyr\Auth;

use Zephyr\Auth\Contracts\UserProvider;
use Zephyr\Auth\Contracts\Authenticatable;
use Zephyr\Core\App;

class AuthManager
{
    protected App $app;
    protected UserProvider $provider;
    protected JwtManager $jwt;
    protected ?object $user = null; // Cachelenen kullanıcı payload'ı

    /**
     * Bağımlılıklar (DI) ile otomatik olarak yüklenir
     */
    public function __construct(App $app, UserProvider $provider, JwtManager $jwt)
    {
        $this->app = $app;
        $this->provider = $provider;
        $this->jwt = $jwt;
    }

    /**
     * Kullanıcı girişini dener ve başarılı olursa token döndürür.
     *
     * @param array $credentials ['email' => '...', 'password' => '...']
     * @return string|null Başarılıysa JWT, değilse null
     */
    public function attempt(array $credentials): ?string
    {
        // 1. Kullanıcıyı sağlayıcı (provider) aracılığıyla bul
        $user = $this->provider->retrieveByCredentials($credentials);

        if (!$user) {
            return null;
        }

        // 2. Şifreyi doğrula
        // (password_verify PHP'nin standart fonksiyonudur)
        if (!password_verify($credentials['password'], $user->getAuthPassword())) {
            return null;
        }

        // 3. Token oluştur
        return $this->jwt->createToken($user);
    }

    /**
     * O an giriş yapmış olan kullanıcıyı (veya token payload'ını) döndürür.
     * Bu metot, AuthMiddleware'in container'a kaydettiği veriyi okur.
     *
     * @return object|null
     */
    public function user(): ?object
    {
        if ($this->user) {
            return $this->user;
        }

        // AuthMiddleware'in kaydettiği 'auth.user'
        if ($this->app->has('auth.user')) {
            $this->user = $this->app->resolve('auth.user');
            return $this->user;
        }

        return null;
    }
    
    /**
     * Giriş yapmış kullanıcının ID'sini alır.
     */
    public function id(): mixed
    {
        return $this->user()->sub ?? null; // 'sub' JWT standardıdır
    }

    /**
     * Kullanıcının giriş yapıp yapmadığını kontrol eder.
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }
    
    /**
     * JWT yöneticisine doğrudan erişim sağlar (gerekirse).
     */
    public function jwt(): JwtManager
    {
        return $this->jwt;
    }
}