<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Zephyr\Core\App;
use Zephyr\Http\{Request, Response};

/**
 * Integration test for typed route parameters
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class TypedRouteParametersTest extends TestCase
{
    protected App $app;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->app = App::getInstance(__DIR__ . '/../..');
        $this->app->loadEnvironment();
        $this->app->loadConfiguration();
        $this->app->registerProviders();
    }

    public function testIntegerRouteParameter(): void
    {
        $router = $this->app->router();
        
        // Register route with typed parameter
        $router->get('/users/{id}', function(Request $request, int $id) {
            return Response::success([
                'user_id' => $id,
                'type' => gettype($id)
            ]);
        });
        
        // Simulate request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users/123';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $router->dispatch($request);
        $content = json_decode($response->getContent(), true);
        
        // ✅ Verify type coercion worked
        $this->assertTrue($content['success']);
        $this->assertSame(123, $content['data']['user_id']);
        $this->assertSame('integer', $content['data']['type']);
    }

    public function testFloatRouteParameter(): void
    {
        $router = $this->app->router();
        
        $router->get('/price/{amount}', function(Request $request, float $amount) {
            return Response::success([
                'price' => $amount,
                'tax' => $amount * 0.20
            ]);
        });
        
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/price/99.99';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $router->dispatch($request);
        $content = json_decode($response->getContent(), true);
        
        $this->assertEqualsWithDelta(99.99, $content['data']['price'], 0.01);
        $this->assertEqualsWithDelta(19.998, $content['data']['tax'], 0.001);
    }

    public function testBoolRouteParameter(): void
    {
        $router = $this->app->router();
        
        $router->get('/status/{active}', function(Request $request, bool $active) {
            return Response::success([
                'is_active' => $active,
                'status' => $active ? 'enabled' : 'disabled'
            ]);
        });
        
        // Test with "1"
        $_SERVER['REQUEST_URI'] = '/status/1';
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $router->dispatch($request);
        $content = json_decode($response->getContent(), true);
        
        $this->assertTrue($content['data']['is_active']);
        $this->assertSame('enabled', $content['data']['status']);
    }
}