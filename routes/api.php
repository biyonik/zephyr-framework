<?php

/**
 * API Routes
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

use Zephyr\Http\{Request, Response};
use App\Controllers\TestController;

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
            'GET /test' => 'Test controller index',
            'GET /test/{id}' => 'Test controller show',
            'POST /test' => 'Test controller store',
        ]
    ]);
});

// Test routes with controller
$router->get('/test', [TestController::class, 'index']);
$router->get('/test/{id}', [TestController::class, 'show']);
$router->post('/test', [TestController::class, 'store']);

// Test route with constraints
$router->get('/users/{id}', function(Request $request) {
    return Response::success([
        'user_id' => $request->param('id'),
        'message' => 'User details'
    ]);
})->where('id', '[0-9]+'); // Only numeric IDs

// Test route group
$router->group(['prefix' => '/admin'], function($router) {
    $router->get('/dashboard', function() {
        return Response::success(['message' => 'Admin dashboard']);
    });
    
    $router->get('/users', function() {
        return Response::success(['message' => 'Admin users list']);
    });
});