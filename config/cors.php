<?php

/**
 * CORS Configuration
 *
 * Cross-Origin Resource Sharing (CORS) settings for your API.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Define which origins are allowed to access your API.
    |
    | - Use '*' to allow all origins (not recommended for production)
    | - Specify exact origins: ['https://example.com']
    | - Use wildcards: ['*.example.com', 'https://*.app.com']
    |
    */
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    |
    | HTTP methods that are allowed for CORS requests.
    |
    */
    'allowed_methods' => explode(',', env('CORS_ALLOWED_METHODS', 'GET,POST,PUT,DELETE,PATCH,OPTIONS')),

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers
    |--------------------------------------------------------------------------
    |
    | Headers that are allowed in CORS requests.
    |
    */
    'allowed_headers' => explode(',', env('CORS_ALLOWED_HEADERS', 'Content-Type,Authorization,X-Requested-With,X-CSRF-Token')),

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    |
    | Headers that browsers are allowed to access.
    |
    */
    'exposed_headers' => explode(',', env('CORS_EXPOSED_HEADERS', '')),

    /*
    |--------------------------------------------------------------------------
    | Max Age
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) the results of a preflight request can be cached.
    |
    */
    'max_age' => (int) env('CORS_MAX_AGE', 3600),

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials
    |--------------------------------------------------------------------------
    |
    | Whether to allow credentials (cookies, authorization headers, etc.)
    | in CORS requests.
    |
    | WARNING: Cannot be used with 'allowed_origins' => ['*']
    |
    */
    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),
];