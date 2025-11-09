<?php

declare(strict_types=1);

namespace Zephyr\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Zephyr\Auth\Contracts\Authenticatable;
use Zephyr\Support\Config;
use Zephyr\Cache\CacheInterface;

class JwtManager
{
    protected string $secret;
    protected string $algo;
    protected int $expiry;
    protected int $refreshExpiry;
    protected string $issuer;
    protected CacheInterface $cache; // ✅ Cache injection

    public function __construct(CacheInterface $cache)
    {
        $this->secret = Config::get('auth.jwt.secret');
        $this->algo = Config::get('auth.jwt.algo');
        $this->expiry = Config::get('auth.jwt.expiry');
        $this->refreshExpiry = Config::get('auth.jwt.refresh_expiry');
        $this->issuer = Config::get('auth.jwt.issuer');
        $this->cache = $cache; // ✅ Cache bağımlılığı

        if (empty($this->secret)) {
            throw new \RuntimeException('JWT_SECRET .env dosyasında tanımlanmamış.');
        }
    }

    /**
     * Access Token oluşturur.
     */
    public function createToken(Authenticatable $user, array $customClaims = []): string
    {
        $time = time();

        $payload = array_merge([
            'iss' => $this->issuer,
            'iat' => $time,
            'exp' => $time + $this->expiry,
            'sub' => $user->getAuthId(),
            'type' => 'access',
        ], $customClaims);

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * Refresh Token oluşturur.
     */
    public function createRefreshToken(Authenticatable $user): string
    {
        $time = time();

        $payload = [
            'iss' => $this->issuer,
            'iat' => $time,
            'exp' => $time + $this->refreshExpiry,
            'sub' => $user->getAuthId(),
            'type' => 'refresh',
            'jti' => bin2hex(random_bytes(16)), // ✅ Unique token ID
        ];

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * Token'ı doğrular ve payload döndürür.
     */
    public function decodeToken(string $token, ?string $expectedType = null): ?object
    {
        try {
            $payload = JWT::decode($token, new Key($this->secret, $this->algo));

            // Token tipini kontrol et
            if ($expectedType !== null && isset($payload->type) && $payload->type !== $expectedType) {
                return null;
            }

            // ✅ Blacklist kontrolü (refresh token için)
            if (isset($payload->jti) && $this->isBlacklisted($payload->jti)) {
                return null;
            }

            return $payload;

        } catch (\Firebase\JWT\ExpiredException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * ✅ FIX: Refresh token ile yeni access token + yeni refresh token üretir.
     * Eski refresh token'ı blacklist'e ekler (rotation).
     *
     * @return array{access_token: string, refresh_token: string}|null
     */
    public function refreshAccessToken(string $refreshToken, Authenticatable $user): ?array
    {
        // 1. Refresh token'ı doğrula
        $payload = $this->decodeToken($refreshToken, 'refresh');

        if (!$payload) {
            return null; // Geçersiz veya süresi dolmuş
        }

        // 2. Token kullanıcıya ait mi?
        if ($payload->sub !== $user->getAuthId()) {
            return null; // Güvenlik: Token başka kullanıcıya ait!
        }

        // 3. ✅ Eski refresh token'ı blacklist'e ekle
        $this->blacklistRefreshToken($payload->jti, $payload->exp);

        // 4. ✅ Yeni token pair üret
        return [
            'access_token' => $this->createToken($user),
            'refresh_token' => $this->createRefreshToken($user), // Yeni refresh token
        ];
    }

    /**
     * ✅ Refresh token'ı blacklist'e ekler.
     */
    protected function blacklistRefreshToken(string $jti, int $expiry): void
    {
        // Token expire olana kadar blacklist'te tut
        $ttl = max(1, $expiry - time());
        $this->cache->set("refresh_token_blacklist:{$jti}", '1', $ttl);
    }

    /**
     * ✅ Token blacklist'te mi kontrol eder.
     */
    protected function isBlacklisted(string $jti): bool
    {
        return $this->cache->has("refresh_token_blacklist:{$jti}");
    }

    /**
     * Token'ın geçerli olup olmadığını kontrol eder.
     */
    public function isValid(string $token, ?string $expectedType = null): bool
    {
        return $this->decodeToken($token, $expectedType) !== null;
    }

    /**
     * Token'dan kullanıcı ID'sini çıkarır.
     */
    public function getUserId(string $token): mixed
    {
        $payload = $this->decodeToken($token);
        return $payload->sub ?? null;
    }

    /**
     * Token'ın kalan süresini (saniye) döndürür.
     */
    public function getTimeToExpire(string $token): ?int
    {
        $payload = $this->decodeToken($token);

        if (!$payload || !isset($payload->exp)) {
            return null;
        }

        $remaining = $payload->exp - time();
        return max(0, $remaining);
    }
}