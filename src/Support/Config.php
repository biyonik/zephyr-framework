<?php

declare(strict_types=1);

namespace Zephyr\Support;

/**
 * Configuration Manager
 * 
 * Loads and manages application configuration from PHP files.
 * Supports dot notation for nested configuration access.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Config
{
    /**
     * All loaded configuration items
     */
    protected static array $items = [];

    /**
     * Configuration path
     */
    protected static ?string $path = null;

    /**
     * Load configuration files from a directory
     */
    public static function load(string $path): void
    {
        static::$path = rtrim($path, '/');
        static::$items = [];

        if (!is_dir(static::$path)) {
            return;
        }

        // Load all PHP files from config directory
        foreach (glob(static::$path . '/*.php') as $file) {
            $key = basename($file, '.php');
            $config = require $file;
            
            if (is_array($config)) {
                static::$items[$key] = $config;
            }
        }
    }

    /**
     * Get a configuration value using dot notation
     * 
     * @example Config::get('database.default')
     * @example Config::get('app.name', 'Default App')
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, static::$items)) {
            return static::$items[$key];
        }

        $segments = explode('.', $key);
        $value = static::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a configuration value using dot notation
     * 
     * @example Config::set('database.default', 'sqlite')
     */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current = &static::$items;

        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }

        $current = $value;
    }

    /**
     * Check if a configuration key exists
     */
    public static function has(string $key): bool
    {
        if (array_key_exists($key, static::$items)) {
            return true;
        }

        $segments = explode('.', $key);
        $value = static::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /**
     * Get all configuration items
     */
    public static function all(): array
    {
        return static::$items;
    }

    /**
     * YENİ: Önbellekten yüklenen tüm config dizisini ayarlar.
     * Bu metot, App::loadConfiguration() tarafından kullanılır.
     *
     * @param array $items Önbelleğe alınmış yapılandırma dizisi
     */
    public static function setAll(array $items): void
    {
        static::$items = $items;
    }

    /**
     * Clear all configuration items
     */
    public static function clear(): void
    {
        static::$items = [];
    }

    /**
     * Merge configuration with existing values
     */
    public static function merge(string $key, array $value): void
    {
        $current = static::get($key, []);
        
        if (!is_array($current)) {
            $current = [];
        }

        static::set($key, array_merge($current, $value));
    }

    /**
     * Reload configuration from files
     */
    public static function reload(): void
    {
        if (static::$path) {
            static::load(static::$path);
        }
    }
}