<?php

declare(strict_types=1);

namespace Zephyr\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Zephyr\Auth\Contracts\Authenticatable;
use Zephyr\Support\Config;

/**
 * JWT Manager - ENHANCED (Refresh Token Support)
 *
 * Access token + Refresh token ile güvenli auth sistemi.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class JwtManager
{
    protected string $secret;
    protected string $algo;
    protected int $expiry;
    protected int $refreshExpiry;
    protected string $issuer;

    public function __construct()
    {
        $this->secret = Config::get('auth.jwt.secret');
        $this->algo = Config::get('auth.jwt.algo');
        $this->expiry = Config::get('auth.jwt.expiry');
        $this->refreshExpiry = Config::get('auth.jwt.refresh_expiry');
        $this->issuer = Config::get('auth.jwt.issuer');

        if (empty($this->secret)) {
            throw new \RuntimeException('JWT_SECRET .env dosyasında tanımlanmamış.');
        }
    }

    /**
     * Verilen kullanıcı için yeni bir Access Token oluşturur.
     *
     * @param Authenticatable $user
     * @param array $customClaims Ekstra claim'ler (opsiyonel)
     * @return string
     */
    public function createToken(Authenticatable $user, array $customClaims = []): string
    {
        $time = time();

        $payload = array_merge([
            'iss' => $this->issuer,
            'iat' => $time,
            'exp' => $time + $this->expiry,
            'sub' => $user->getAuthId(),
            'type' => 'access', // ✅ Token tipini belirt
        ], $customClaims);

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * Verilen kullanıcı için yeni bir Refresh Token oluşturur.
     *
     * Refresh token daha uzun ömürlüdür ve sadece yeni access token
     * almak için kullanılır.
     *
     * @param Authenticatable $user
     * @return string
     */
    public function createRefreshToken(Authenticatable $user): string
    {
        $time = time();

        $payload = [
            'iss' => $this->issuer,
            'iat' => $time,
            'exp' => $time + $this->refreshExpiry,
            'sub' => $user->getAuthId(),
            'type' => 'refresh', // ✅ Refresh token olduğunu belirt
            'jti' => bin2hex(random_bytes(16)), // ✅ Unique token ID
        ];

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * Bir token'ı doğrular ve payload'ı döndürür.
     *
     * @param string $token
     * @param string|null $expectedType 'access' veya 'refresh' (opsiyonel)
     * @return object|null
     */
    public function decodeToken(string $token, ?string $expectedType = null): ?object
    {
        try {
            $payload = JWT::decode($token, new Key($this->secret, $this->algo));

            // Token tipini kontrol et
            if ($expectedType !== null && isset($payload->type) && $payload->type !== $expectedType) {
                return null; // Yanlış token tipi
            }

            return $payload;

        } catch (\Firebase\JWT\ExpiredException $e) {
            // Token süresi dolmuş
            return null;
        } catch (\Exception $e) {
            // Diğer hatalar (invalid signature, malformed token, etc.)
            return null;
        }
    }

    /**
     * Bir refresh token ile yeni access token üretir.
     *
     * @param string $refreshToken
     * @param Authenticatable $user
     * @return string|null Yeni access token veya null (başarısız)
     */
    public function refreshAccessToken(string $refreshToken, Authenticatable $user): ?string
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

        // 3. Yeni access token üret
        return $this->createToken($user);
    }

    /**
     * Token'ın geçerli olup olmadığını kontrol eder (bool).
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