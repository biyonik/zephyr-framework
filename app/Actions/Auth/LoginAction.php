<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Zephyr\Auth\AuthManager;
use Zephyr\Exceptions\Http\UnauthorizedException;

/**
 * Action: LoginAction
 *
 * Kullanıcı girişini dener ve başarılı olursa bir JWT döndürür.
 */
class LoginAction
{
    public function __construct(private readonly AuthManager $auth)
    {
    }

    /**
     * Eylemi çalıştırır.
     *
     * @param array $credentials ['email' => '...', 'password' => '...']
     * @return string JWT Token
     * @throws UnauthorizedException
     */
    public function execute(array $credentials): string
    {
        $token = $this->auth->attempt($credentials);

        if (!$token) {
            // Controller'ın yakalaması için standart bir HTTP hatası fırlat
            throw new UnauthorizedException('Geçersiz kimlik bilgileri');
        }

        return $token;
    }
}