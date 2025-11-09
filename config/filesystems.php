<?php

declare(strict_types=1);

/**
 * Dosya Sistemi (Filesystem) Yapılandırması
 *
 * "Disk" olarak adlandırılan farklı depolama sürücülerini tanımlar.
 * Her disk, bir sürücüyü (driver) ve bir kök dizini (root) temsil eder.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Varsayılan Dosya Sistemi Diski
    |--------------------------------------------------------------------------
    |
    | 'storage()' helper'ı disk belirtilmeden kullanıldığında
    | varsayılan olarak bu disk kullanılır.
    |
    | Önerilen: 'local' (Web'den erişilemeyen, özel dosyalar için)
    |
    */
    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Dosya Sistemi Diskleri (Disks)
    |--------------------------------------------------------------------------
    |
    | 'local': Özel dosyalar (loglar, cache, özel yüklemeler) içindir.
    |          'storage/app' dizinini kullanır. WEB'DEN ERİŞİLEMEZ.
    |
    | 'public': Herkesin erişebileceği (örn: avatar, galeri resimleri)
    |           dosyalar içindir. 'storage/app/public' dizinini kullanır.
    |           'php zephyr storage:link' komutu ile
    |           'public/storage' dizinine symlinklenir.
    |
    */
    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL', 'http://localhost') . '/storage',
            'visibility' => 'public',
        ],

        // Gelecekte eklenebilir:
        // 's3' => [
        //     'driver' => 's3',
        //     'key' => env('AWS_ACCESS_KEY_ID'),
        //     'secret' => env('AWS_SECRET_ACCESS_KEY'),
        //     'region' => env('AWS_DEFAULT_REGION'),
        //     'bucket' => env('AWS_BUCKET'),
        //     'url' => env('AWS_URL'),
        // ],

    ],

];