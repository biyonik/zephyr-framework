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
        
        // ✅ CRITICAL: Disable debug mode for production-like testing
        // (Specific tests will re-enable it when needed)
        Config::set('app.debug', false);
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
        
        $router->get('/test-error', function() {
            throw new NotFoundException('User not found');
        });
        
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test-error';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $this->app->handle($request);
        $content = json_decode($response->getContent(), true);
        
        // Check standardized format
        $this->assertFalse($content['success']);
        $this->assertSame('User not found', $content['error']['message']);
        $this->assertSame('NOT_FOUND', $content['error']['code']);
        
        // ✅ Simple errors should NOT have details (in production)
        $this->assertArrayNotHasKey('details', $content['error']);
        
        // Should have meta
        $this->assertArrayHasKey('meta', $content);
        $this->assertArrayHasKey('timestamp', $content['meta']);
        $this->assertArrayHasKey('request_id', $content['meta']);
    }

    public function testValidationExceptionFormat(): void
    {
        $router = $this->app->router();
        
        $router->post('/test-validation', function() {
            throw new ValidationException([
                'email' => ['Email is required'],
                'password' => ['Password too short']
            ]);
        });
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/test-validation';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $this->app->handle($request);
        $content = json_decode($response->getContent(), true);
        
        // Check standardized format
        $this->assertFalse($content['success']);
        $this->assertSame('Validation failed', $content['error']['message']);
        $this->assertSame('VALIDATION_ERROR', $content['error']['code']);
        
        // Validation errors should be in details (even in production)
        $this->assertArrayHasKey('details', $content['error']);
        $this->assertArrayHasKey('email', $content['error']['details']);
        $this->assertArrayHasKey('password', $content['error']['details']);
        
        // Check actual validation messages
        $this->assertContains('Email is required', $content['error']['details']['email']);
        $this->assertContains('Password too short', $content['error']['details']['password']);
    }

    public function testManualErrorResponseFormat(): void
    {
        $router = $this->app->router();
        
        $router->get('/test-manual', function() {
            return Response::error('Custom error', 400, [
                'field' => 'value',
                'reason' => 'Something wrong'
            ]);
        });
        
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test-manual';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $this->app->handle($request);
        $content = json_decode($response->getContent(), true);
        
        // Check standardized format
        $this->assertFalse($content['success']);
        $this->assertSame('Custom error', $content['error']['message']);
        $this->assertArrayHasKey('details', $content['error']);
        $this->assertSame('value', $content['error']['details']['field']);
        $this->assertSame('Something wrong', $content['error']['details']['reason']);
    }

    public function testServerErrorInDebugMode(): void
    {
        // ✅ Explicitly enable debug mode for this test
        Config::set('app.debug', true);
        
        $router = $this->app->router();
        
        $router->get('/test-debug', function() {
            throw new \RuntimeException('Something went wrong');
        });
        
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test-debug';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $this->app->handle($request);
        $content = json_decode($response->getContent(), true);
        
        // Debug mode should include details
        $this->assertArrayHasKey('details', $content['error']);
        $this->assertArrayHasKey('exception', $content['error']['details']);
        $this->assertArrayHasKey('file', $content['error']['details']);
        $this->assertArrayHasKey('line', $content['error']['details']);
        $this->assertArrayHasKey('trace', $content['error']['details']);
        
        // Check exception details
        $this->assertSame('RuntimeException', $content['error']['details']['exception']);
        $this->assertIsString($content['error']['details']['file']);
        $this->assertIsInt($content['error']['details']['line']);
        $this->assertIsArray($content['error']['details']['trace']);
    }

    public function testServerErrorInProductionMode(): void
    {
        // ✅ Debug mode already disabled in setUp()
        
        $router = $this->app->router();
        
        $router->get('/test-prod', function() {
            throw new \RuntimeException('Something went wrong');
        });
        
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test-prod';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $this->app->handle($request);
        $content = json_decode($response->getContent(), true);
        
        // Production should NOT include debug details
        $this->assertArrayNotHasKey('details', $content['error']);
        
        // Should show generic message (not the actual exception message)
        $this->assertSame('Internal server error occurred', $content['error']['message']);
        
        // Should have proper status
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testAllErrorsHaveMeta(): void
    {
        $testRoutes = [
            ['GET', '/error-1', fn() => throw new NotFoundException()],
            ['POST', '/error-2', fn() => throw new ValidationException(['field' => ['error']])],
            ['GET', '/error-3', fn() => Response::error('Manual error', 400)],
        ];
        
        foreach ($testRoutes as $index => [$method, $uri, $handler]) {
            // Reset app for each iteration
            $this->setUp();
            
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
            
            $response = $this->app->handle($request);
            $content = json_decode($response->getContent(), true);
            
            // Every error must have meta
            $this->assertArrayHasKey('meta', $content, "Route {$uri} missing meta");
            $this->assertArrayHasKey('timestamp', $content['meta'], "Route {$uri} missing timestamp");
            $this->assertArrayHasKey('request_id', $content['meta'], "Route {$uri} missing request_id");
            
            // Timestamp should be valid ISO 8601 format
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
                $content['meta']['timestamp'],
                "Route {$uri} has invalid timestamp format"
            );
            
            // Request ID should start with 'req_'
            $this->assertStringStartsWith('req_', $content['meta']['request_id']);
        }
    }

    /**
     * Test that debug and validation details can coexist
     */
    public function testValidationErrorWithDebugMode(): void
    {
        // ✅ Enable debug mode
        Config::set('app.debug', true);
        
        $router = $this->app->router();
        
        $router->post('/test-validation-debug', function() {
            throw new ValidationException([
                'email' => ['Invalid email']
            ], 'Validation error with debug');
        });
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/test-validation-debug';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $this->app->handle($request);
        $content = json_decode($response->getContent(), true);
        
        // Should have details
        $this->assertArrayHasKey('details', $content['error']);
        
        // Should have BOTH validation errors AND debug info
        $this->assertArrayHasKey('email', $content['error']['details'], 'Missing validation errors');
        $this->assertArrayHasKey('exception', $content['error']['details'], 'Missing debug info');
        $this->assertArrayHasKey('file', $content['error']['details'], 'Missing debug info');
        
        // Validation errors should be preserved
        $this->assertContains('Invalid email', $content['error']['details']['email']);
    }
}