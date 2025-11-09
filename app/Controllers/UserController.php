<?php

declare(strict_types=1);

namespace App\Controllers;

use Zephyr\Http\Request;
use Zephyr\Http\Response;
use App\Models\User;

/**
 * Kullanıcı İşlemleri Kontrolcüsü
 * (Doğrulanmış kullanıcılar için)
 */
class UserController
{
    /**
     * Giriş yapmış kullanıcının bilgilerini döndürür.
     * Bu bir "Query" (Sorgu) işlemidir.
     */
    public function me(Request $request): Response
    {
        // auth()->id() metodu, AuthMiddleware tarafından
        // doğrulanmış kullanıcının ID'sini (sub) verir.
        $userId = auth()->id();

        if (!$userId) {
            return Response::error('Doğrulama başarısız', 401);
        }

        // Basit "Query" (Sorgu) işlemleri için Action'a gerek yoktur.
        // Doğrudan Controller içinde yapılabilir.
        $user = User::find($userId);

        if (!$user) {
            return Response::error('Kullanıcı bulunamadı', 404);
        }

        return Response::success($user);
    }
}