<?php

/**
 * Application Configuration
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */
    'name' => env('APP_NAME', 'Zephyr Framework'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    | This value determines the "environment" your application is running in.
    | Common values: local, development, staging, production
    */
    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    | When enabled, detailed error messages with stack traces will be shown.
    | Should be disabled in production for security reasons.
    */
    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    */
    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    */
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale
    |--------------------------------------------------------------------------
    */
    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    | This key is used for encryption and should be a random 32 character string.
    */
    'key' => env('APP_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Service Providers
    |--------------------------------------------------------------------------
    | These providers will be automatically loaded on application bootstrap.
    */
    'providers' => [
        // Core providers
        \Zephyr\Providers\RouteServiceProvider::class,
        \Zephyr\Providers\DatabaseServiceProvider::class,
        
        // Application providers
        // \App\Providers\AppServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    | Class aliases to be registered when the application boots.
    */
    'aliases' => [
        'App' => \Zephyr\Core\App::class,
        'Config' => \Zephyr\Support\Config::class,
        'DB' => \Zephyr\Database\DB::class,
        'Route' => \Zephyr\Core\Router::class,
    ],
];