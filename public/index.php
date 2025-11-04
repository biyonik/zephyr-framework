<?php

declare(strict_types=1);

/**
 * Zephyr Framework - Public Entry Point
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

use Zephyr\Core\App;
use Zephyr\Http\Request;

/*
|--------------------------------------------------------------------------
| Check PHP Version
|--------------------------------------------------------------------------
*/

if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    die('PHP 8.0+ required. Current version: ' . PHP_VERSION);
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
*/

$autoloader = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoloader)) {
    die('Please run "composer install" to install dependencies.');
}

require $autoloader;

/*
|--------------------------------------------------------------------------
| Bootstrap The Application
|--------------------------------------------------------------------------
*/

try {
    $app = App::getInstance(__DIR__ . '/..');

    /*
    |--------------------------------------------------------------------------
    | Load Environment Variables
    |--------------------------------------------------------------------------
    */

    $app->loadEnvironment();

    /*
    |--------------------------------------------------------------------------
    | Load Configuration
    |--------------------------------------------------------------------------
    */

    $app->loadConfiguration();

    /*
    |--------------------------------------------------------------------------
    | Register Service Providers
    |--------------------------------------------------------------------------
    */

    $app->registerProviders();

    /*
    |--------------------------------------------------------------------------
    | Register Routes
    |--------------------------------------------------------------------------
    */

    require __DIR__ . '/../routes/api.php';

    /*
    |--------------------------------------------------------------------------
    | Capture & Register HTTP Request
    |--------------------------------------------------------------------------
    */

    $request = Request::capture();
    
    // ✅ CRITICAL FIX: Register request instance in container
    // This allows dependency injection to work properly for Request type hints
    $app->instance(Request::class, $request);

    /*
    |--------------------------------------------------------------------------
    | Handle The Request
    |--------------------------------------------------------------------------
    */

    $response = $app->handle($request);
    $response->send();

    /*
    |--------------------------------------------------------------------------
    | Terminate The Application
    |--------------------------------------------------------------------------
    */

    $app->terminate($request, $response);

} catch (\Throwable $e) {
    // Emergency error handler
    http_response_code(500);
    header('Content-Type: application/json');
    
    $debug = $_ENV['APP_DEBUG'] ?? false;
    
    if ($debug) {
        echo json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_slice($e->getTrace(), 0, 5)
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            'error' => true,
            'message' => 'Internal Server Error'
        ]);
    }
    
    // Log the error
    error_log(sprintf(
        "[%s] %s in %s:%d",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
}