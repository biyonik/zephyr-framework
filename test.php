<?php

/**
 * Zephyr Framework Test Script
 * * Run this to test if the framework is working correctly.
 * Usage: php test.php
 * * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

require __DIR__ . '/vendor/autoload.php';

use Zephyr\Core\App;
use Zephyr\Http\Request;

echo "========================================\n";
echo "   Zephyr Framework Test Suite\n";
echo "========================================\n\n";

try {
    // Bootstrap application
    echo "1. Bootstrapping application... ";
    $app = App::getInstance(__DIR__);
    $app->loadEnvironment();
    $app->loadConfiguration();
    $app->registerProviders();
    echo "✅\n";

    // Load routes
    echo "2. Loading routes... ";
    require __DIR__ . '/routes/api.php';
    echo "✅\n";

    // Test route matching
    echo "3. Testing route matching... ";
    $router = $app->router();
    
    // Create mock request for testing
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';
    $request = Request::capture();
    
    // <-- GÜNCELLEME: Oluşturulan request'i container'a tanıt
    $app->instance(Request::class, $request);

    $response = $router->dispatch($request);
    echo "✅\n";

    // Test response
    echo "4. Testing response generation... ";
    $content = json_decode($response->getContent(), true);
    assert($content['success'] === true);
    assert($content['data']['name'] === 'Zephyr Framework');
    echo "✅\n";

    // Test dynamic route
    echo "5. Testing dynamic routes... ";
    $_SERVER['REQUEST_METHOD'] = 'GET'; // <-- GÜNCELLEME: Metodu net olarak belirt
    $_SERVER['REQUEST_URI'] = '/test/123';
    $request = Request::capture();
    
    // <-- GÜNCELLEME: Oluşturulan request'i container'a tanıt
    $app->instance(Request::class, $request);

    $response = $router->dispatch($request);
    $content = json_decode($response->getContent(), true);
    assert($content['data']['id'] === '123');
    echo "✅\n";

    // Test 404
    echo "6. Testing 404 handling... ";
    try {
        $_SERVER['REQUEST_METHOD'] = 'GET'; // <-- GÜNCELLEME: Metodu net olarak belirt
        $_SERVER['REQUEST_URI'] = '/non-existent';
        $request = Request::capture();
        
        // <-- GÜNCELLEME: Oluşturulan request'i container'a tanıt
        $app->instance(Request::class, $request);

        $response = $router->dispatch($request);
        echo "❌ (Should throw NotFoundException)\n";
    } catch (\Zephyr\Exceptions\Http\NotFoundException $e) {
        echo "✅\n";
    }

    // Test method not allowed
    echo "7. Testing method not allowed... ";
    try {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/test/123'; // Only GET is allowed
        $request = Request::capture();

        // <-- GÜNCELLEME: Oluşturulan request'i container'a tanıt
        $app->instance(Request::class, $request);

        $response = $router->dispatch($request);
        echo "❌ (Should throw MethodNotAllowedException)\n";
    } catch (\Zephyr\Exceptions\Http\MethodNotAllowedException $e) {
        echo "✅\n";
    }

    // Test container
    echo "8. Testing dependency injection... ";
    $app->bind('test.service', function() {
        return new stdClass();
    });
    $service = $app->resolve('test.service');
    assert($service instanceof stdClass);
    echo "✅\n";

    // Test singleton
    echo "9. Testing singleton binding... ";
    $app->singleton('test.singleton', function() {
        $obj = new stdClass();
        $obj->id = uniqid();
        return $obj;
    });
    $s1 = $app->resolve('test.singleton');
    $s2 = $app->resolve('test.singleton');
    assert($s1 === $s2);
    assert($s1->id === $s2->id);
    echo "✅\n";

    // Summary
    echo "\n========================================\n";
    echo "✅ All tests passed successfully!\n";
    echo "========================================\n";
    echo "\nFramework Information:\n";
    echo "- Version: " . $app->version() . "\n";
    echo "- Environment: " . $app->environment() . "\n";
    echo "- Debug Mode: " . ($app->isDebug() ? 'ON' : 'OFF') . "\n";
    echo "- PHP Version: " . PHP_VERSION . "\n";
    
    // Show registered routes
    echo "\nRegistered Routes:\n";
    $routes = $router->getRoutes();
    foreach ($routes as $method => $methodRoutes) {
        foreach ($methodRoutes as $route) {
            echo "- {$method} {$route->getUri()}\n";
        }
    }

} catch (Exception $e) {
    echo "❌\n";
    echo "\nError: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}