<?php

/**
 * Trusted Proxy Configuration
 * 
 * Define trusted proxy servers that are allowed to provide
 * X-Forwarded-For and similar headers.
 * 
 * WARNING: Only add proxy IPs you FULLY CONTROL!
 * Adding untrusted IPs creates a security vulnerability.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | List of IP addresses or CIDR ranges of trusted proxies.
    | These proxies are allowed to send X-Forwarded-* headers.
    |
    | Common scenarios:
    | - Behind Cloudflare: Add Cloudflare IP ranges
    | - Behind AWS ELB: Add ELB subnet
    | - Behind Nginx: Add Nginx server IP
    |
    | Examples:
    | - Single IP: '192.168.1.1'
    | - CIDR range: '10.0.0.0/8'
    | - Wildcard: '*' (DANGEROUS - trust all, use only in dev)
    |
    */
    'proxies' => env('TRUSTED_PROXIES') 
        ? explode(',', env('TRUSTED_PROXIES'))
        : [
            // Add your trusted proxy IPs here
            // '127.0.0.1',           // Localhost (for development)
            // '192.168.1.0/24',      // Local network
            // '10.0.0.0/8',          // Private network
        ],

    /*
    |--------------------------------------------------------------------------
    | Trusted Headers
    |--------------------------------------------------------------------------
    |
    | Headers to trust from proxies. Only enable headers your proxy sends.
    |
    | Available headers:
    | - FORWARDED       → Standard RFC 7239 header
    | - X_FORWARDED_FOR → Client IP chain
    | - X_FORWARDED_HOST → Original host
    | - X_FORWARDED_PORT → Original port
    | - X_FORWARDED_PROTO → Original protocol (http/https)
    |
    */
    'headers' => env('TRUSTED_PROXY_HEADERS')
        ? explode(',', env('TRUSTED_PROXY_HEADERS'))
        : [
            'X_FORWARDED_FOR',
            'X_FORWARDED_PROTO',
            // 'FORWARDED',
            // 'X_FORWARDED_HOST',
            // 'X_FORWARDED_PORT',
        ],
];