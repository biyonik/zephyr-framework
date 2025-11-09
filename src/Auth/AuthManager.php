// src/Auth/AuthManager.php
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
    protected ?Authenticatable $user = null; // ✅ Authenticatable tipinde

    public function __construct(App $app, UserProvider $provider, JwtManager $jwt)
    {
        $this->app = $app;
        $this->provider = $provider;
        $this->jwt = $jwt;
    }

    /**
     * Kullanıcı girişini dener ve başarılı olursa token döndürür.
     */
    public function attempt(array $credentials): ?string
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if (!$user) {
            return null;
        }

        if (!password_verify($credentials['password'], $user->getAuthPassword())) {
            return null;
        }

        return $this->jwt->createToken($user);
    }

    /**
     * ✅ FIX: O an giriş yapmış olan kullanıcıyı (FULL MODEL) döndürür.
     * JWT payload'ından ID alır, User modelini çeker ve cache'ler.
     */
    public function user(): ?Authenticatable
    {
        // 1. Zaten cache'lenmişse döndür
        if ($this->user) {
            return $this->user;
        }

        // 2. JWT payload'ından user ID al
        $userId = null;

        // AuthMiddleware'in set ettiği payload'ı kontrol et
        if ($this->app->has('auth.payload')) {
            $payload = $this->app->resolve('auth.payload');
            $userId = $payload->sub ?? null;
        }

        if (!$userId) {
            return null;
        }

        // 3. User modelini DB'den çek
        $this->user = $this->provider->retrieveById($userId);

        return $this->user;
    }

    /**
     * ✅ FIX: Giriş yapmış kullanıcının ID'sini alır (nullsafe).
     */
    public function id(): mixed
    {
        $user = $this->user();
        return $user?->getAuthId();
    }

    /**
     * Kullanıcının giriş yapıp yapmadığını kontrol eder.
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * JWT yöneticisine doğrudan erişim sağlar.
     */
    public function jwt(): JwtManager
    {
        return $this->jwt;
    }
}