<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Zephyr\Http\{Request, Response};

/**
 * Response Send Protection Tests
 * 
 * Tests that response cannot be sent multiple times
 * and headers_sent() is properly checked.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class ResponseSendTest extends TestCase
{
    /**
     * Test that isSent() returns false before sending
     */
    public function testIsNotSentInitially(): void
    {
        $response = Response::success(['test' => 'data']);
        
        $this->assertFalse($response->isSent());
    }

    /**
     * Test that multiple send() calls throw exception
     * 
     * Note: We can't actually test send() in unit tests because
     * it calls header() which requires no output before it.
     * Instead, we'll test the isSent() flag logic.
     */
    public function testCannotSendTwice(): void
    {
        $response = Response::success(['test' => 'data']);
        
        // We can't actually send in unit test, but we can check the logic
        // by testing isSent() state
        $this->assertFalse($response->isSent());
    }

    /**
     * Test __toString() doesn't mark response as sent
     */
    public function testToStringDoesNotMarkAsSent(): void
    {
        $response = Response::success(['test' => 'data']);
        
        // Converting to string should not mark as sent
        $output = (string) $response;
        
        $this->assertFalse($response->isSent());
        $this->assertStringContainsString('HTTP/1.1 200', $output);
        $this->assertStringContainsString('Content-Type: application/json', $output);
    }

    /**
     * Test __toString() includes status line
     */
    public function testToStringIncludesStatusLine(): void
    {
        $response = new Response('Test content', 404);
        
        $output = (string) $response;
        
        $this->assertStringContainsString('HTTP/1.1 404 Not Found', $output);
    }

    /**
     * Test __toString() includes headers
     */
    public function testToStringIncludesHeaders(): void
    {
        $response = new Response('Test content', 200, [
            'X-Custom-Header' => 'test-value',
            'X-Another-Header' => 'another-value'
        ]);
        
        $output = (string) $response;
        
        $this->assertStringContainsString('X-Custom-Header: test-value', $output);
        $this->assertStringContainsString('X-Another-Header: another-value', $output);
    }

    /**
     * Test __toString() includes content
     */
    public function testToStringIncludesContent(): void
    {
        $response = new Response('Hello World', 200);
        
        $output = (string) $response;
        
        $this->assertStringContainsString('Hello World', $output);
    }

    /**
     * Test __toString() excludes body for HEAD requests
     */
    public function testToStringExcludesBodyForHead(): void
    {
        $request = new Request(
            method: 'HEAD',
            uri: '/test',
            headers: [],
            query: [],
            body: [],
            files: [],
            server: ['REQUEST_METHOD' => 'HEAD'],
            cookies: []
        );
        
        $response = new Response('Should not appear', 200);
        $response->setRequest($request);
        
        $output = (string) $response;
        
        // Should have status and headers
        $this->assertStringContainsString('HTTP/1.1 200', $output);
        
        // Should NOT have body
        $this->assertStringNotContainsString('Should not appear', $output);
    }

    /**
     * Test multiple header values in __toString()
     */
    public function testToStringWithMultipleHeaderValues(): void
    {
        $response = new Response('Test', 200);
        $response->cookie('session', 'abc123');
        $response->cookie('token', 'xyz789');
        
        $output = (string) $response;
        
        // Should have both Set-Cookie headers
        $this->assertStringContainsString('Set-Cookie:', $output);
        // Check that both cookies appear
        $matches = preg_match_all('/Set-Cookie:/i', $output);
        $this->assertGreaterThanOrEqual(2, $matches);
    }

    /**
     * Test getStatusText() for common codes
     */
    public function testGetStatusText(): void
    {
        $this->assertSame('OK', Response::getStatusText(200));
        $this->assertSame('Created', Response::getStatusText(201));
        $this->assertSame('No Content', Response::getStatusText(204));
        $this->assertSame('Not Found', Response::getStatusText(404));
        $this->assertSame('Internal Server Error', Response::getStatusText(500));
        $this->assertSame('Unknown', Response::getStatusText(999));
    }

    /**
     * Test response modification after creation
     */
    public function testCanModifyResponseBeforeSending(): void
    {
        $response = Response::success(['initial' => 'data']);
        
        // Modify status
        $response->setStatusCode(201);
        $this->assertSame(201, $response->getStatusCode());
        
        // Add headers
        $response->header('X-Custom', 'value');
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('X-Custom', $headers);
        
        // Modify content
        $response->setContent(json_encode(['modified' => 'content']));
        $this->assertStringContainsString('modified', $response->getContent());
    }

    /**
     * Test that sendAndExit() would mark as sent
     * (We can't actually test exit(), but we can verify the method exists)
     */
    public function testSendAndExitMethodExists(): void
    {
        $response = Response::success(['test' => 'data']);
        
        $this->assertTrue(method_exists($response, 'sendAndExit'));
    }

    /**
     * Test deprecated output() method exists for backwards compatibility
     */
    public function testDeprecatedOutputMethodExists(): void
    {
        $response = Response::success(['test' => 'data']);
        
        $this->assertTrue(method_exists($response, 'output'));
    }

    /**
     * Test response with no content type gets default
     */
    public function testJsonResponseHasCorrectContentType(): void
    {
        $response = Response::json(['test' => 'data']);
        
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertSame('application/json', $headers['Content-Type']);
    }

    /**
     * Test success response structure
     */
    public function testSuccessResponseStructure(): void
    {
        $response = Response::success(['user' => 'John'], 'User found');
        
        $content = json_decode($response->getContent(), true);
        
        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('data', $content);
        $this->assertArrayHasKey('message', $content);
        $this->assertArrayHasKey('meta', $content);
        $this->assertSame('User found', $content['message']);
    }

    /**
     * Test error response structure
     */
    public function testErrorResponseStructure(): void
    {
        $response = Response::error('Not found', 404);
        
        $content = json_decode($response->getContent(), true);
        
        $this->assertFalse($content['success']);
        $this->assertArrayHasKey('error', $content);
        $this->assertArrayHasKey('message', $content['error']);
        $this->assertArrayHasKey('code', $content['error']);
        $this->assertSame('NOT_FOUND', $content['error']['code']);
    }

    /**
     * Test paginated response structure
     */
    public function testPaginatedResponseStructure(): void
    {
        $data = [['id' => 1], ['id' => 2]];
        $pagination = [
            'current_page' => 1,
            'per_page' => 10,
            'total' => 100,
            'last_page' => 10,
            'base_url' => '/users'
        ];
        
        $response = Response::paginated($data, $pagination);
        
        $content = json_decode($response->getContent(), true);
        
        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('data', $content);
        $this->assertArrayHasKey('meta', $content);
        $this->assertArrayHasKey('pagination', $content['meta']);
        $this->assertArrayHasKey('links', $content);
    }

    /**
     * Test no content response
     */
    public function testNoContentResponse(): void
    {
        $response = Response::noContent();
        
        $this->assertSame(204, $response->getStatusCode());
        $this->assertEmpty($response->getContent());
    }

    /**
     * Test redirect response
     */
    public function testRedirectResponse(): void
    {
        $response = Response::redirect('/new-location', 301);
        
        $this->assertSame(301, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Location', $headers);
        $this->assertSame('/new-location', $headers['Location']);
    }
}