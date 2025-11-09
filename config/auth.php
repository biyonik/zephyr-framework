<?php

/**
 * Kimlik Doğrulama (Authentication) Yapılandırması
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

use App\Models\User;

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Ayarları
    |--------------------------------------------------------------------------
    */
    'jwt' => [
        /*
        | ZORUNLU: JWT_SECRET .env dosyanızda tanımlanmalıdır!
        |
        | Rastgele 32 karakter üretmek için:
        | php -r "echo bin2hex(random_bytes(32));"
        |
        | ⚠️ Production ortamında MUTLAKA ayarlayın!
        */
        'secret' => (function() {
            $secret = env('JWT_SECRET');

            if (empty($secret)) {
                // Development ortamında uyar, production'da hata fırlat
                if (env('APP_ENV') === 'production') {
                    throw new \RuntimeException(
                        'JWT_SECRET .env dosyasında tanımlanmalı! ' .
                        'Üretmek için: php -r "echo bin2hex(random_bytes(32));"'
                    );
                }

                // Development için geçici secret (her restart'ta değişir)
                error_log('⚠️  UYARI: JWT_SECRET tanımlı değil! Development için geçici secret kullanılıyor.');
                return bin2hex(random_bytes(32));
            }

            // Secret'ın yeterince güçlü olduğunu kontrol et
            if (strlen($secret) < 32) {
                throw new \RuntimeException(
                    'JWT_SECRET en az 32 karakter olmalı! ' .
                    'Üretmek için: php -r "echo bin2hex(random_bytes(32));"'
                );
            }

            return $secret;
        })(),

        /*
        | Token imzalama algoritması.
        | Önerilenler: HS256 (hızlı), HS512 (daha güvenli), RS256 (public/private key)
        */
        'algo' => env('JWT_ALGO', 'HS256'),

        /*
        | Token geçerlilik süresi (saniye cinsinden).
        | Varsayılan: 3600 saniye (1 saat)
        |
        | Öneriler:
        | - API: 1 saat (3600)
        | - Web: 12 saat (43200)
        | - Mobile: 7 gün (604800)
        */
        'expiry' => (int) env('JWT_EXPIRY', 3600),

        /*
        | Refresh token geçerlilik süresi (saniye cinsinden).
        | Varsayılan: 30 gün
        */
        'refresh_expiry' => (int) env('JWT_REFRESH_EXPIRY', 2592000), // 30 gün

        /*
        | Token'ı kimin yayınladığı (issuer).
        */
        'issuer' => env('APP_URL', 'http://localhost'),
    ],

    /**
     * Authentication providers
     */
    'provider' => [
        'model' => User::class,
    ],

    /**
     * Password reset token ayarları
     */
    'passwords' => [
        'expire' => 60, // Dakika cinsinden (1 saat)
        'throttle' => 60, // İstekler arası minimum süre (saniye)
    ],
];