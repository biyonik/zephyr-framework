<?php

/**
 * Kimlik Doğrulama (Authentication) Yapılandırması
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Ayarları
    |--------------------------------------------------------------------------
    |
    | JSON Web Token (JWT) ayarları.
    |
    */
    'jwt' => [
        /*
        | API token'larını imzalamak için kullanılacak gizli anahtar.
        | Bu anahtar .env dosyanızda ayarlanmalıdır (örn: 32 karakterli rastgele dize)
        */
        'secret' => env('JWT_SECRET', 'your-secret-key-change-this'), //

        /*
        | Token imzalama algoritması.
        | Önerilenler: HS256, HS512, RS256
        */
        'algo' => env('JWT_ALGO', 'HS256'), //

        /*
        | Token geçerlilik süresi (saniye cinsinden).
        | Varsayılan: 3600 saniye (1 saat)
        */
        'expiry' => (int) env('JWT_EXPIRY', 3600), //
        
        /*
        | Token'ı kimin yayınladığı (issuer).
        */
        'issuer' => env('APP_URL', 'http://localhost'), //
    ],

    /**
     * Authentication providers
     */
    'provider' => [
        'model' => \App\Models\User::class,
    ]
];