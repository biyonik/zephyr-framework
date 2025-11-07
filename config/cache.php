<?php

declare(strict_types=1);

/**
 * Önbellek (Caching) Yapılandırması
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Varsayılan Önbellek Sürücüsü (Store)
    |--------------------------------------------------------------------------
    |
    | cache() helper'ı kullanıldığında varsayılan olarak hangi sürücünün
    | kullanılacağını belirler.
    |
    | Önerilen: 'file' (paylaşımlı sunucular için)
    |
    */
    'default' => env('CACHE_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Önbellek Sürücüleri (Stores)
    |--------------------------------------------------------------------------
    |
    | Desteklenen sürücüler: "file", "array" (testler için)
    |
    */
    'stores' => [

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'default_ttl' => (int) env('CACHE_DEFAULT_TTL', 3600), // 1 saat (saniye)
        ],

        'array' => [
            'driver' => 'array',
            'default_ttl' => 3600,
            // 'array' sürücüsü veriyi sadece o anki request (istek)
            // süresince hafızada tutar. Testler için idealdir.
        ],

        // Gelecekte eklenebilir: 'redis', 'memcached'
    ],

    /*
    |--------------------------------------------------------------------------
    | Önbellek Anahtarı Öneki (Prefix)
    |--------------------------------------------------------------------------
    |
    | Önbelleğe kaydedilen tüm anahtarların başına bu önek eklenir.
    | Bu, aynı sunucudaki farklı uygulamaların cache'lerinin karışmasını engeller.
    |
    */
    'prefix' => env('CACHE_PREFIX', 'zephyr'),

];