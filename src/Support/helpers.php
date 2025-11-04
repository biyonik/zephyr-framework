<?php

declare(strict_types=1);

/**
 * Global Helper Functions
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

use Zephyr\Core\App;
use Zephyr\Support\Config;
use Zephyr\Support\Env;
use Zephyr\Http\Request;

if (!function_exists('app')) {
    /**
     * Get the application instance or resolve a service
     */
    function app(?string $abstract = null): mixed
    {
        if (is_null($abstract)) {
            return App::getInstance();
        }

        return App::getInstance()->resolve($abstract);
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     */
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable value
     */
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the base path of the application
     */
    function base_path(string $path = ''): string
    {
        $basePath = app()->basePath();
        
        return $path ? $basePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\') : $basePath;
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the storage path
     */
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the config path
     */
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('response')) {
    /**
     * Create a response instance
     */
    function response(mixed $content = '', int $status = 200, array $headers = []): \Zephyr\Http\Response
    {
        return new \Zephyr\Http\Response($content, $status, $headers);
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and die (development helper)
     */
    function dd(mixed ...$vars): never
    {
        foreach ($vars as $var) {
            dump($var);
        }
        
        die(1);
    }
}

if (!function_exists('value')) {
    /**
     * Return the value of the given value
     */
    function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('now')) {
    /**
     * Get current datetime
     */
    function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone(config('app.timezone', 'UTC')));
    }
}

if (!function_exists('ip_address')) {
    /**
     * Get client IP address
     */
    function ip_address(): string
    {
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            // In HTTP request context
            return app(Request::class)->ip();
        }
        
        // In CLI context
        return '127.0.0.1';
    }
}