<?php

declare(strict_types=1);

namespace App\Controllers;

use Zephyr\Http\Request;
use Zephyr\Http\Response;
use Zephyr\Validation\ValidationSchema as V;
use App\Actions\Auth\RegisterUserAction;
use App\Actions\Auth\LoginAction;

/**
 * Kimlik Doğrulama Kontrolcüsü
 * Sorumluluğu: HTTP isteğini almak, doğrulamak, Action'a delege etmek, yanıt döndürmek.
 */
class AuthController
{
    /**
     * Yeni kullanıcı kaydı.
     */
    public function register(Request $request, RegisterUserAction $action): Response
    {
        // 1. Doğrulama
        $data = $request->validate(
            V::make()->shape([
                'name' => V::make()->string()->required()->min(2),
                'email' => V::make()->string()->required()->email(),
                'password' => V::make()->string()->required()->min(8)->password(),
            ])
        );

        // 2. Delege et (İşi Action sınıfı yapar)
        $user = $action->execute($data);

        // 3. HTTP Yanıtı
        return Response::success($user, 'Kayıt başarılı', 201);
    }

    /**
     * Kullanıcı girişi.
     */
    public function login(Request $request, LoginAction $action): Response
    {
        // 1. Doğrulama
        $credentials = $request->validate(
            V::make()->shape([
                'email' => V::make()->string()->required()->email(),
                'password' => V::make()->string()->required(),
            ])
        );

        // 2. Delege et
        $token = $action->execute($credentials);

        // 3. HTTP Yanıtı
        return Response::success(['token' => $token]);
    }
}