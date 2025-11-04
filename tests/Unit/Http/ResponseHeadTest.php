<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Zephyr\Http\{Request, Response};

/**
 * Response HEAD Request Tests
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class ResponseHeadTest extends TestCase
{
    public function testHeadRequestDetectionViaRequest(): void
    {
        // Create HEAD request
        $request = new Request(
            method: 'HEAD',
            uri: '/api/test',
            headers: [],
            query: [],
            body: [],
            files: [],
            server: ['REQUEST_METHOD' => 'HEAD'],
            cookies: []
        );
        
        $response = Response::success(['data' => 'test']);
        $response->setRequest($request);
        
        // Verify request is associated
        $this->assertSame($request, $response->getRequest());
        $this->assertTrue($request->isMethod('HEAD'));
    }

    public function testGetRequestIsNotHead(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/api/test',
            headers: [],
            query: [],
            body: [],
            files: [],
            server: ['REQUEST_METHOD' => 'GET'],
            cookies: []
        );
        
        $response = Response::success(['data' => 'test']);
        $response->setRequest($request);
        
        $this->assertFalse($request->isMethod('HEAD'));
    }

    public function testResponseWithoutRequestUsesServerFallback(): void
    {
        // Save original
        $original = $_SERVER['REQUEST_METHOD'] ?? null;
        
        // Set HEAD
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        
        $response = Response::success(['data' => 'test']);
        
        // Response should detect HEAD from $_SERVER
        $reflection = new \ReflectionClass($response);
        $method = $reflection->getMethod('isHeadRequest');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($response));
        
        // Restore
        if ($original !== null) {
            $_SERVER['REQUEST_METHOD'] = $original;
        } else {
            unset($_SERVER['REQUEST_METHOD']);
        }
    }
}