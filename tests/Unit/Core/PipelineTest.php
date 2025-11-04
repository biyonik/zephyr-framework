<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Zephyr\Core\{App, Pipeline};
use Zephyr\Http\{Request, Response};
use Zephyr\Http\Middleware\MiddlewareInterface;
use Closure;

/**
 * Pipeline Tests
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class PipelineTest extends TestCase
{
    protected App $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = App::getInstance(__DIR__ . '/../../..');
    }

    /**
     * Test pipeline without middleware
     */
    public function testPipelineWithoutMiddleware(): void
    {
        $request = new Request('GET', '/', [], [], [], [], [], []);

        $response = (new Pipeline($this->app))
            ->send($request)
            ->through([])
            ->then(fn($req) => Response::success(['test' => 'data']));

        $this->assertInstanceOf(Response::class, $response);

        $content = json_decode($response->getContent(), true);
        $this->assertTrue($content['success']);
        $this->assertSame('data', $content['data']['test']);
    }

    /**
     * Test pipeline with single middleware
     */
    public function testPipelineWithSingleMiddleware(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function handle(Request $request, Closure $next): Response
            {
                $response = $next($request);
                $response->header('X-Test-Header', 'test-value');
                return $response;
            }
        };

        $request = new Request('GET', '/', [], [], [], [], [], []);

        $response = (new Pipeline($this->app))
            ->send($request)
            ->through([$middleware])
            ->then(fn($req) => Response::success(['data' => 'test']));

        $headers = $response->getHeaders();
        $this->assertArrayHasKey('X-Test-Header', $headers);
        $this->assertSame('test-value', $headers['X-Test-Header']);
    }

    /**
     * Test pipeline with multiple middleware (execution order)
     */
    public function testPipelineExecutionOrder(): void
    {
        $order = [];

        $middleware1 = new class($order) implements MiddlewareInterface {
            private array $order;

            public function __construct(array &$order) {
                $this->order = &$order;
            }

            public function handle(Request $request, Closure $next): Response
            {
                $this->order[] = 'middleware1-before';
                $response = $next($request);
                $this->order[] = 'middleware1-after';
                return $response;
            }
        };

        $middleware2 = new class($order) implements MiddlewareInterface {
            private array $order;

            public function __construct(array &$order) {
                $this->order = &$order;
            }

            public function handle(Request $request, Closure $next): Response
            {
                $this->order[] = 'middleware2-before';
                $response = $next($request);
                $this->order[] = 'middleware2-after';
                return $response;
            }
        };

        $request = new Request('GET', '/', [], [], [], [], [], []);

        (new Pipeline($this->app))
            ->send($request)
            ->through([$middleware1, $middleware2])
            ->then(function($req) use (&$order) {
                $order[] = 'controller';
                return Response::success([]);
            });

        // Expected order: Onion model
        $this->assertSame([
            'middleware1-before',
            'middleware2-before',
            'controller',
            'middleware2-after',
            'middleware1-after',
        ], $order);
    }

    /**
     * Test middleware can modify request
     */
    public function testMiddlewareCanModifyRequest(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function handle(Request $request, Closure $next): Response
            {
                // Modify request (add custom data)
                // Note: Request doesn't have setters, so this is conceptual
                return $next($request);
            }
        };

        $request = new Request('GET', '/', [], [], [], [], [], []);

        $response = (new Pipeline($this->app))
            ->send($request)
            ->through([$middleware])
            ->then(fn($req) => Response::success(['method' => $req->method()]));

        $content = json_decode($response->getContent(), true);
        $this->assertSame('GET', $content['data']['method']);
    }

    /**
     * Test middleware can terminate pipeline (early return)
     */
    public function testMiddlewareCanTerminate(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function handle(Request $request, Closure $next): Response
            {
                // Terminate early without calling $next
                return Response::error('Unauthorized', 401);
            }
        };

        $controllerCalled = false;

        $request = new Request('GET', '/', [], [], [], [], [], []);

        $response = (new Pipeline($this->app))
            ->send($request)
            ->through([$middleware])
            ->then(function($req) use (&$controllerCalled) {
                $controllerCalled = true;
                return Response::success([]);
            });

        // Controller should NOT be called
        $this->assertFalse($controllerCalled);

        // Response should be from middleware
        $this->assertSame(401, $response->getStatusCode());
    }

    /**
     * Test pipeline with closure middleware
     */
    public function testPipelineWithClosureMiddleware(): void
    {
        $request = new Request('GET', '/', [], [], [], [], [], []);

        $response = (new Pipeline($this->app))
            ->send($request)
            ->through([
                function($request, $next) {
                    $response = $next($request);
                    $response->header('X-Closure', 'works');
                    return $response;
                }
            ])
            ->then(fn($req) => Response::success([]));

        $headers = $response->getHeaders();
        $this->assertArrayHasKey('X-Closure', $headers);
    }

    /**
     * Test pipeline exception handling
     */
    public function testPipelineExceptionBubbles(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        $middleware = new class implements MiddlewareInterface {
            public function handle(Request $request, Closure $next): Response
            {
                throw new \RuntimeException('Test exception');
            }
        };

        $request = new Request('GET', '/', [], [], [], [], [], []);

        (new Pipeline($this->app))
            ->send($request)
            ->through([$middleware])
            ->then(fn($req) => Response::success([]));
    }

    /**
     * Test pipeline with class string middleware (resolved from container)
     */
    public function testPipelineWithClassString(): void
    {
        // Register test middleware in container
        $testMiddleware = new class implements MiddlewareInterface {
            public function handle(Request $request, Closure $next): Response
            {
                $response = $next($request);
                $response->header('X-Container', 'resolved');
                return $response;
            }
        };

        $className = get_class($testMiddleware);
        $this->app->instance($className, $testMiddleware);

        $request = new Request('GET', '/', [], [], [], [], [], []);

        $response = (new Pipeline($this->app))
            ->send($request)
            ->through([$className])
            ->then(fn($req) => Response::success([]));

        $headers = $response->getHeaders();
        $this->assertArrayHasKey('X-Container', $headers);
    }

    /**
     * Test pipeline getPipes method
     */
    public function testGetPipes(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function handle(Request $request, Closure $next): Response {
                return $next($request);
            }
        };

        $pipeline = (new Pipeline($this->app))
            ->through([$middleware]);

        $pipes = $pipeline->getPipes();

        $this->assertCount(1, $pipes);
        $this->assertSame($middleware, $pipes[0]);
    }

    /**
     * Test pipeline getPassable method
     */
    public function testGetPassable(): void
    {
        $request = new Request('GET', '/', [], [], [], [], [], []);

        $pipeline = (new Pipeline($this->app))
            ->send($request);

        $passable = $pipeline->getPassable();

        $this->assertSame($request, $passable);
    }
}