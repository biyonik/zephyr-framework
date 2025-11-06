<?php

declare(strict_types=1);

namespace Zephyr\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Zephyr\Auth\Contracts\Authenticatable;
use Zephyr\Support\Config;

/**
 * Sadece JWT oluşturma (encode) ve çözme (decode) işinden sorumlu sınıf.
 */
class JwtManager
{
    protected string $secret;
    protected string $algo;
    protected int $expiry;
    protected string $issuer;

    public function __construct()
    {
        $this->secret = Config::get('auth.jwt.secret');
        $this->algo = Config::get('auth.jwt.algo');
        $this->expiry = Config::get('auth.jwt.expiry');
        $this->issuer = Config::get('auth.jwt.issuer');

        if (empty($this->secret)) {
            throw new \RuntimeException('JWT_SECRET .env dosyasında tanımlanmamış.');
        }
    }

    /**
     * Verilen kullanıcı için yeni bir JWT oluşturur.
     */
    public function createToken(Authenticatable $user): string
    {
        $time = time();

        $payload = [
            'iss' => $this->issuer, // Yayınlayan
            'iat' => $time,          // Yayınlanma zamanı
            'exp' => $time + $this->expiry, // Son kullanma tarihi
            'sub' => $user->getAuthId(),    // Subject (Kullanıcı ID)
            // Token'a eklemek istediğiniz diğer özel veriler
        ];

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * Bir token'ı doğrular ve payload'ı döndürür.
     * (Bu, AuthMiddleware tarafından kullanılır)
     */
    public function decodeToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->secret, $this->algo));
        } catch (\Exception $e) {
            return null;
        }
    }
}