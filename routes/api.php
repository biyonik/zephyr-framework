<?php

/**
 * API Routes
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

use Zephyr\Http\Response;

$router = app()->router();

// Welcome route
$router->get('/', function() {
    return Response::success([
        'name' => 'Zephyr Framework',
        'version' => app()->version(),
        'message' => 'Welcome to Zephyr API Framework!'
    ]);
});

// Health check
$router->get('/health', function() {
    return Response::success([
        'status' => 'healthy',
        'timestamp' => now()->format('Y-m-d H:i:s'),
        'environment' => app()->environment(),
    ]);
});

// API Info
$router->get('/api', function() {
    return Response::success([
        'framework' => 'Zephyr',
        'version' => app()->version(),
        'php_version' => PHP_VERSION,
        'endpoints' => [
            'GET /' => 'Welcome message',
            'GET /health' => 'Health check',
            'GET /api' => 'API information',
        ]
    ]);
});