<?php

declare(strict_types=1);

use Monolog\Level;

/**
 * Gelişmiş Loglama (Logging) Yapılandırması
 *
 * PSR-3 uyumlu (Monolog) loglama sistemi ayarları.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Varsayılan Log Kanalı
    |--------------------------------------------------------------------------
    |
    | log() helper'ı veya Log::info() kullanıldığında hangi kanalın
    | varsayılan olarak kullanılacağını belirler.
    |
    | 'stack', birden fazla kanala aynı anda yazmak için özel bir sürücüdür.
    |
    */
    'default' => env('LOG_CHANNEL', 'daily'),

    /*
    |--------------------------------------------------------------------------
    | Log Kanalları (Channels)
    |--------------------------------------------------------------------------
    |
    | Her kanal, bir loglama sürücüsünü (driver) ve ayarlarını temsil eder.
    | Örn: 'daily' (günlük dosya), 'single' (tek dosya), 'stderr' (konsol).
    |
    */
    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily'], // Varsayılan olarak 'daily' kanalına yönlendir
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/zephyr.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'permission' => 0664,
            'locking' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/zephyr.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14, // 14 günlük log tut (eskileri otomatik siler)
            'permission' => 0664,
            'locking' => true,
        ],

        'stderr' => [
            'driver' => 'stderr',
            'level' => env('LOG_LEVEL', 'debug'),
            'formatter' => 'line',
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        // Gelecekte eklenebilir: 'slack', 'database' vb.

        'maintenance' => [
            'driver' => 'single',
            'path' => storage_path('logs/maintenance.log'),
            'level' => 'info',
            'locking' => true,
        ],

    ],

];