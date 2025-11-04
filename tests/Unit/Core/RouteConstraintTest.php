<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Zephyr\Core\{App, Route};
use Zephyr\Http\{Request, Response};

/**
 * Route Constraint Validation Tests
 * 
 * Tests that route constraints properly validate parameters
 * and reject invalid values.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class RouteConstraintTest extends TestCase
{
    protected App $app;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->app = App::getInstance(__DIR__ . '/../../..');
    }

    /**
     * Test basic numeric constraint
     */
    public function testNumericConstraintMatchesNumbers(): void
    {
        $route = new Route(['GET'], '/users/{id}', fn($id) => "User: {$id}");
        $route->where('id', '[0-9]+');
        
        // ✅ Should match numeric IDs
        $this->assertTrue($route->matches('/users/123'));
        $this->assertTrue($route->matches('/users/1'));
        $this->assertTrue($route->matches('/users/999999'));
    }

    /**
     * Test numeric constraint rejects non-numeric values
     */
    public function testNumericConstraintRejectsNonNumeric(): void
    {
        $route = new Route(['GET'], '/users/{id}', fn($id) => "User: {$id}");
        $route->where('id', '[0-9]+');
        
        // ❌ Should NOT match non-numeric values
        $this->assertFalse($route->matches('/users/abc'));
        $this->assertFalse($route->matches('/users/12abc'));
        $this->assertFalse($route->matches('/users/abc123'));
        $this->assertFalse($route->matches('/users/user-123'));
    }

    /**
     * Test whereNumber() helper method
     */
    public function testWhereNumberHelper(): void
    {
        $route = new Route(['GET'], '/posts/{id}', fn($id) => "Post: {$id}");
        $route->whereNumber('id');
        
        $this->assertTrue($route->matches('/posts/42'));
        $this->assertFalse($route->matches('/posts/hello'));
    }

    /**
     * Test UUID constraint
     */
    public function testUuidConstraint(): void
    {
        $route = new Route(['GET'], '/resources/{uuid}', fn($uuid) => "Resource: {$uuid}");
        $route->whereUuid('uuid');
        
        // Valid UUID v4
        $this->assertTrue($route->matches('/resources/550e8400-e29b-41d4-a716-446655440000'));
        
        // Invalid UUIDs
        $this->assertFalse($route->matches('/resources/not-a-uuid'));
        $this->assertFalse($route->matches('/resources/123'));
        $this->assertFalse($route->matches('/resources/550e8400-e29b-41d4'));
    }

    /**
     * Test multiple constraints on different parameters
     */
    public function testMultipleConstraints(): void
    {
        $route = new Route(
            ['GET'], 
            '/posts/{year}/{month}/{slug}', 
            fn() => 'Post'
        );
        
        $route->where([
            'year' => '[0-9]{4}',
            'month' => '0[1-9]|1[0-2]',  // 01-12
            'slug' => '[a-z0-9-]+'
        ]);
        
        // ✅ Valid combinations
        $this->assertTrue($route->matches('/posts/2024/01/hello-world'));
        $this->assertTrue($route->matches('/posts/2024/12/test-post'));
        
        // ❌ Invalid year (not 4 digits)
        $this->assertFalse($route->matches('/posts/24/01/hello'));
        
        // ❌ Invalid month (not 01-12)
        $this->assertFalse($route->matches('/posts/2024/13/hello'));
        $this->assertFalse($route->matches('/posts/2024/00/hello'));
        
        // ❌ Invalid slug (contains uppercase or special chars)
        $this->assertFalse($route->matches('/posts/2024/01/Hello_World'));
    }

    /**
     * Test optional parameter with constraint
     */
    public function testOptionalParameterWithConstraint(): void
    {
        $route = new Route(['GET'], '/search/{query?}', fn() => 'Search');
        $route->where('query', '[a-z]+');
        
        // ✅ Without parameter (optional)
        $this->assertTrue($route->matches('/search'));
        $this->assertTrue($route->matches('/search/'));
        
        // ✅ With valid parameter
        $this->assertTrue($route->matches('/search/hello'));
        
        // ❌ With invalid parameter (contains numbers)
        $this->assertFalse($route->matches('/search/hello123'));
    }

    /**
     * Test whereIn() constraint
     */
    public function testWhereInConstraint(): void
    {
        $route = new Route(['GET'], '/status/{type}', fn() => 'Status');
        $route->whereIn('type', ['active', 'inactive', 'pending']);
        
        // ✅ Valid values
        $this->assertTrue($route->matches('/status/active'));
        $this->assertTrue($route->matches('/status/inactive'));
        $this->assertTrue($route->matches('/status/pending'));
        
        // ❌ Invalid values
        $this->assertFalse($route->matches('/status/deleted'));
        $this->assertFalse($route->matches('/status/archived'));
    }

    /**
     * Test whereAlpha() helper
     */
    public function testWhereAlphaHelper(): void
    {
        $route = new Route(['GET'], '/users/{name}', fn() => 'User');
        $route->whereAlpha('name');
        
        $this->assertTrue($route->matches('/users/John'));
        $this->assertTrue($route->matches('/users/Alice'));
        
        // ❌ Numbers not allowed
        $this->assertFalse($route->matches('/users/John123'));
        $this->assertFalse($route->matches('/users/123'));
    }

    /**
     * Test whereAlphaNumeric() helper
     */
    public function testWhereAlphaNumericHelper(): void
    {
        $route = new Route(['GET'], '/codes/{code}', fn() => 'Code');
        $route->whereAlphaNumeric('code');
        
        $this->assertTrue($route->matches('/codes/ABC123'));
        $this->assertTrue($route->matches('/codes/test'));
        $this->assertTrue($route->matches('/codes/123'));
        
        // ❌ Special characters not allowed
        $this->assertFalse($route->matches('/codes/ABC-123'));
        $this->assertFalse($route->matches('/codes/test_123'));
    }

    /**
     * Test default constraint for 'id' parameter
     */
    public function testDefaultIdConstraint(): void
    {
        // Without explicit where(), 'id' should use default numeric constraint
        $route = new Route(['GET'], '/users/{id}', fn() => 'User');
        
        // ✅ Numeric IDs work
        $this->assertTrue($route->matches('/users/123'));
        
        // ❌ Non-numeric should fail with default constraint
        // NOTE: This requires DEFAULT_CONSTRAINTS to be applied automatically
        // which is implemented in getConstraintForParameter()
        $this->assertFalse($route->matches('/users/abc'));
    }

    /**
     * Test parameter extraction with constraints
     */
    public function testParameterExtractionWithConstraints(): void
    {
        $route = new Route(['GET'], '/users/{id}/posts/{slug}', fn() => 'Post');
        $route->where([
            'id' => '[0-9]+',
            'slug' => '[a-z0-9-]+'
        ]);
        
        $params = $route->extractParameters('/users/123/posts/hello-world');
        
        $this->assertSame('123', $params['id']);
        $this->assertSame('hello-world', $params['slug']);
    }

    /**
     * Test that invalid URL doesn't extract parameters
     */
    public function testInvalidUrlDoesNotExtractParameters(): void
    {
        $route = new Route(['GET'], '/users/{id}', fn() => 'User');
        $route->where('id', '[0-9]+');
        
        // Invalid URL (non-numeric ID)
        $params = $route->extractParameters('/users/abc');
        
        // Should return empty array
        $this->assertEmpty($params);
    }

    /**
     * Test constraint recompilation when where() is called
     */
    public function testConstraintRecompilation(): void
    {
        $route = new Route(['GET'], '/posts/{id}', fn() => 'Post');
        
        // Initially matches both
        $this->assertTrue($route->matches('/posts/123'));
        $this->assertTrue($route->matches('/posts/abc'));
        
        // Add constraint - should recompile
        $route->where('id', '[0-9]+');
        
        // Now only matches numeric
        $this->assertTrue($route->matches('/posts/123'));
        $this->assertFalse($route->matches('/posts/abc'));
    }

    /**
     * Test multiple whereNumber() calls
     */
    public function testMultipleWhereNumberCalls(): void
    {
        $route = new Route(['GET'], '/api/{version}/users/{id}', fn() => 'User');
        $route->whereNumber(['version', 'id']);
        
        $this->assertTrue($route->matches('/api/1/users/123'));
        $this->assertFalse($route->matches('/api/v1/users/123'));
        $this->assertFalse($route->matches('/api/1/users/abc'));
    }

    /**
     * Test edge case: empty constraint pattern
     */
    public function testEmptyConstraintPattern(): void
    {
        $route = new Route(['GET'], '/test/{param}', fn() => 'Test');
        
        // Edge case: what if someone passes empty string?
        // Should fallback to default generic constraint
        $route->where('param', '');
        
        // With empty constraint, should still match (uses fallback)
        $this->assertTrue($route->matches('/test/anything'));
    }

    /**
     * Integration test: Full request flow with constraint validation
     */
    public function testFullRequestFlowWithConstraints(): void
    {
        $router = $this->app->router();
        
        $router->get('/users/{id}', function(Request $request, int $id) {
            return Response::success(['user_id' => $id]);
        })->whereNumber('id');
        
        // Valid request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users/42';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        $response = $router->dispatch($request);
        $content = json_decode($response->getContent(), true);
        
        $this->assertTrue($content['success']);
        $this->assertSame(42, $content['data']['user_id']);
    }

    /**
     * Integration test: Constraint rejection throws NotFoundException
     */
    public function testConstraintRejectionThrowsNotFoundException(): void
    {
        $this->expectException(\Zephyr\Exceptions\Http\NotFoundException::class);
        
        $router = $this->app->router();
        
        $router->get('/users/{id}', function() {
            return Response::success(['message' => 'User found']);
        })->whereNumber('id');
        
        // Invalid request (non-numeric ID)
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users/abc';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);
        
        // Should throw NotFoundException
        $router->dispatch($request);
    }
}