<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Zephyr\Core\App;
use Zephyr\Http\{Request, Response};
use Zephyr\Exceptions\Http\{NotFoundException, ValidationException};
use Zephyr\Support\Config;

/**
 * Error Response Standardization Integration Tests
 * 
 * Tests that all errors (thrown exceptions, manual errors, etc.)
 * follow the standardized response format.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class ErrorResponseStandardizationTest extends TestCase
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

    protected function tearDown(): void
    {
        Config::clear();
        
        // Reset singleton
        $reflection = new \ReflectionClass(App::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        
        parent::tearDown();
    }

    public function testNotFoundExceptionFormat(): void
    {
        $router = $this->app->router();
        
        $router->get('/test', function() {
            throw new NotFoundException('User not found');
        });
        
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $router->dispatch($request);
        $content = json_decode($response->getContent(), true);
        
        // Check standardized format
        $this->assertFalse($content['success']);
        $this->assertSame('User not found', $content['error']['message']);
        $this->assertSame('NOT_FOUND', $content['error']['code']);
        $this->assertArrayNotHasKey('details', $content['error']);
        $this->assertArrayHasKey('meta', $content);
    }

    public function testValidationExceptionFormat(): void
    {
        $router = $this->app->router();
        
        $router->post('/test', function() {
            throw new ValidationException([
                'email' => ['Email is required'],
                'password' => ['Password too short']
            ]);
        });
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/test';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $router->dispatch($request);
        $content = json_decode($response->getContent(), true);
        
        // Check standardized format
        $this->assertFalse($content['success']);
        $this->assertSame('Validation failed', $content['error']['message']);
        $this->assertSame('VALIDATION_ERROR', $content['error']['code']);
        
        // Validation errors should be in details
        $this->assertArrayHasKey('details', $content['error']);
        $this->assertArrayHasKey('email', $content['error']['details']);
        $this->assertArrayHasKey('password', $content['error']['details']);
    }

    public function testManualErrorResponseFormat(): void
    {
        $router = $this->app->router();
        
        $router->get('/test', function() {
            return Response::error('Custom error', 400, [
                'field' => 'value',
                'reason' => 'Something wrong'
            ]);
        });
        
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $router->dispatch($request);
        $content = json_decode($response->getContent(), true);
        
        // Check standardized format
        $this->assertFalse($content['success']);
        $this->assertSame('Custom error', $content['error']['message']);
        $this->assertArrayHasKey('details', $content['error']);
        $this->assertSame('value', $content['error']['details']['field']);
    }

    public function testServerErrorInDebugMode(): void
    {
        // Enable debug mode
        Config::set('app.debug', true);
        
        $router = $this->app->router();
        
        $router->get('/test', function() {
            throw new \RuntimeException('Something went wrong');
        });
        
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $router->dispatch($request);
        $content = json_decode($response->getContent(), true);
        
        // Debug mode should include details
        $this->assertArrayHasKey('details', $content['error']);
        $this->assertArrayHasKey('exception', $content['error']['details']);
        $this->assertArrayHasKey('file', $content['error']['details']);
        $this->assertArrayHasKey('line', $content['error']['details']);
    }

    public function testServerErrorInProductionMode(): void
    {
        // Disable debug mode
        Config::set('app.debug', false);
        
        $router = $this->app->router();
        
        $router->get('/test', function() {
            throw new \RuntimeException('Something went wrong');
        });
        
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $router->dispatch($request);
        $content = json_decode($response->getContent(), true);
        
        // Production should NOT include debug details
        $this->assertArrayNotHasKey('details', $content['error']);
        
        // Should show generic message
        $this->assertSame('Internal server error occurred', $content['error']['message']);
    }

    public function testAllErrorsHaveMeta(): void
    {
        $testRoutes = [
            ['GET', '/not-found', fn() => throw new NotFoundException()],
            ['POST', '/validation', fn() => throw new ValidationException(['field' => ['error']])],
            ['GET', '/manual', fn() => Response::error('Manual error', 400)],
        ];
        
        foreach ($testRoutes as [$method, $uri, $handler]) {
            $router = $this->app->router();
            
            if ($method === 'GET') {
                $router->get($uri, $handler);
            } else {
                $router->post($uri, $handler);
            }
            
            $_SERVER['REQUEST_METHOD'] = $method;
            $_SERVER['REQUEST_URI'] = $uri;
            
            $request = Request::capture();
            $this->app->instance(Request::class, $request);
            
            $response = $router->dispatch($request);
            $content = json_decode($response->getContent(), true);
            
            // Every error must have meta
            $this->assertArrayHasKey('meta', $content);
            $this->assertArrayHasKey('timestamp', $content['meta']);
            $this->assertArrayHasKey('request_id', $content['meta']);
        }
    }
}