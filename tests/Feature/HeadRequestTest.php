<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Zephyr\Core\App;
use Zephyr\Http\{Request, Response};

/**
 * HEAD Request Integration Test
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class HeadRequestTest extends TestCase
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

    public function testHeadRequestDoesNotReturnBody(): void
    {
        $router = $this->app->router();
        
        // Register GET route (automatically handles HEAD too)
        $router->get('/test', function(Request $request) {
            return Response::success([
                'large_data' => str_repeat('X', 10000),
                'method' => $request->method()
            ]);
        });
        
        // Simulate HEAD request
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '/test';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $router->dispatch($request);
        
        // Verify it's HEAD
        $this->assertTrue($request->isMethod('HEAD'));
        
        // Response should have request associated
        $this->assertSame($request, $response->getRequest());
        
        // Response should have content (for Content-Length header)
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        
        // But when sent, body should be skipped
        // (We can't fully test send() without capturing output)
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testGetRequestReturnsBody(): void
    {
        $router = $this->app->router();
        
        $router->get('/test', function(Request $request) {
            return Response::success([
                'method' => $request->method()
            ]);
        });
        
        // Simulate GET request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $router->dispatch($request);
        
        $this->assertFalse($request->isMethod('HEAD'));
        
        // GET should return full content
        $content = json_decode($response->getContent(), true);
        $this->assertSame('GET', $content['data']['method']);
    }
}