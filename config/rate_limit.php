<?php

declare(strict_types=1);

/**
 * Rate Limiting Configuration
 * 
 * Controls API request throttling to prevent abuse.
 * 
 * @author Ahmet ALTUN
 * @email ahmet.altun60@gmail.com
 * @github github.com/biyonik
 */

return [
    
    /*
    |--------------------------------------------------------------------------
    | Enable Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Set to false to completely disable rate limiting (useful for development).
    |
    */
    'enabled' => env('RATE_LIMIT_ENABLED', true),
    
    /*
    |--------------------------------------------------------------------------
    | Max Attempts
    |--------------------------------------------------------------------------
    |
    | Maximum number of requests allowed within the decay window.
    | Default: 60 requests per minute
    |
    */
    'max_attempts' => (int) env('RATE_LIMIT_MAX_ATTEMPTS', 60),
    
    /*
    |--------------------------------------------------------------------------
    | Decay Minutes
    |--------------------------------------------------------------------------
    |
    | Time window (in minutes) for rate limiting.
    | After this time, the counter resets.
    |
    */
    'decay_minutes' => (int) env('RATE_LIMIT_DECAY_MINUTES', 1),
    
    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | Directory where rate limit data is stored.
    | Must be writable by the web server.
    |
    */
    'storage_path' => env(
        'RATE_LIMIT_STORAGE_PATH',
        dirname(__DIR__, 2) . '/storage/framework/rate-limits'
    ),
    
    /*
    |--------------------------------------------------------------------------
    | Signature Generator
    |--------------------------------------------------------------------------
    |
    | Strategy for generating request signatures.
    | Options: 'ip', 'user', 'ip_and_route'
    |
    | - 'ip': Rate limit per IP address (global)
    | - 'user': Rate limit per authenticated user
    | - 'ip_and_route': Rate limit per IP + endpoint (recommended)
    |
    */
    'signature' => env('RATE_LIMIT_SIGNATURE', 'ip_and_route'),
    
    /*
    |--------------------------------------------------------------------------
    | Skip Conditions
    |--------------------------------------------------------------------------
    |
    | Conditions under which rate limiting is bypassed.
    |
    */
    'skip' => [
        // Skip for specific IPs (whitelist)
        'ips' => array_filter(explode(',', env('RATE_LIMIT_SKIP_IPS', ''))),
        
        // Skip in local environment
        'local_env' => (bool) env('RATE_LIMIT_SKIP_LOCAL', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Custom Limits per Route
    |--------------------------------------------------------------------------
    |
    | Define custom rate limits for specific routes or route groups.
    |
    */
    'routes' => [
        // Example: Tighter limit for login endpoint (prevent brute force)
        '/api/auth/login' => [
            'max_attempts' => 5,
            'decay_minutes' => 1,
        ],
        
        // Example: Tighter limit for registration
        '/api/auth/register' => [
            'max_attempts' => 3,
            'decay_minutes' => 5,
        ],
        
        // Example: More generous for read operations
        '/api/users' => [
            'max_attempts' => 100,
            'decay_minutes' => 1,
        ],
    ],
];