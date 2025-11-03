<?php

declare(strict_types=1);

namespace Zephyr\Support;

/**
 * Environment Variable Helper
 * 
 * Provides a simple interface for accessing environment variables
 * with type casting and default values.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Env
{
    /**
     * Get an environment variable value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        return static::parseValue($value);
    }

    /**
     * Parse environment variable value
     * 
     * Converts string values to appropriate types:
     * - "true"/"false" to boolean
     * - "null" to null
     * - numeric strings to int/float
     */
    protected static function parseValue(string $value): mixed
    {
        // Handle boolean values
        $lowercase = strtolower($value);
        
        if ($lowercase === 'true' || $lowercase === '(true)') {
            return true;
        }
        
        if ($lowercase === 'false' || $lowercase === '(false)') {
            return false;
        }
        
        // Handle null
        if ($lowercase === 'null' || $lowercase === '(null)') {
            return null;
        }
        
        // Handle empty string
        if ($lowercase === 'empty' || $lowercase === '(empty)') {
            return '';
        }

        // Handle quoted strings
        if (preg_match('/^"(.+)"$/', $value, $matches)) {
            return $matches[1];
        }

        // Handle numeric values
        if (is_numeric($value)) {
            // Check if it's an integer
            if ((string)(int)$value === $value) {
                return (int)$value;
            }
            
            // Otherwise treat as float
            return (float)$value;
        }

        return $value;
    }

    /**
     * Set an environment variable (runtime only)
     */
    public static function set(string $key, mixed $value): void
    {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("$key=$value");
    }

    /**
     * Check if an environment variable exists
     */
    public static function has(string $key): bool
    {
        return isset($_ENV[$key]) || isset($_SERVER[$key]) || getenv($key) !== false;
    }

    /**
     * Remove an environment variable (runtime only)
     */
    public static function forget(string $key): void
    {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }
}