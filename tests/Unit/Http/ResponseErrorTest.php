<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Zephyr\Http\Response;

/**
 * Error Response Format Tests
 * 
 * Tests that all error responses follow the standardized format.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class ResponseErrorTest extends TestCase
{
    public function testSimpleErrorFormat(): void
    {
        $response = Response::error('User not found', 404);
        $content = json_decode($response->getContent(), true);
        
        // Check structure
        $this->assertArrayHasKey('success', $content);
        $this->assertArrayHasKey('error', $content);
        $this->assertArrayHasKey('meta', $content);
        
        // Check success is false
        $this->assertFalse($content['success']);
        
        // Check error structure
        $this->assertArrayHasKey('message', $content['error']);
        $this->assertArrayHasKey('code', $content['error']);
        $this->assertSame('User not found', $content['error']['message']);
        $this->assertSame('NOT_FOUND', $content['error']['code']);
        
        // Simple errors should NOT have details
        $this->assertArrayNotHasKey('details', $content['error']);
        
        // Check meta
        $this->assertArrayHasKey('timestamp', $content['meta']);
        $this->assertArrayHasKey('request_id', $content['meta']);
    }

    public function testErrorWithDetails(): void
    {
        $details = [
            'field1' => ['Error 1', 'Error 2'],
            'field2' => ['Error 3']
        ];
        
        $response = Response::error('Validation failed', 422, $details);
        $content = json_decode($response->getContent(), true);
        
        // Should have details
        $this->assertArrayHasKey('details', $content['error']);
        $this->assertSame($details, $content['error']['details']);
    }

    public function testErrorWithEmptyDetailsOmitsField(): void
    {
        // Empty array should not create details field
        $response = Response::error('Error', 400, []);
        $content = json_decode($response->getContent(), true);
        
        $this->assertArrayNotHasKey('details', $content['error']);
    }

    public function testErrorWithNullDetailsOmitsField(): void
    {
        // Null should not create details field
        $response = Response::error('Error', 400, null);
        $content = json_decode($response->getContent(), true);
        
        $this->assertArrayNotHasKey('details', $content['error']);
    }

    public function testErrorStatusCodes(): void
    {
        $testCases = [
            [400, 'BAD_REQUEST'],
            [401, 'UNAUTHORIZED'],
            [403, 'FORBIDDEN'],
            [404, 'NOT_FOUND'],
            [422, 'VALIDATION_ERROR'],
            [500, 'SERVER_ERROR'],
        ];
        
        foreach ($testCases as [$status, $expectedCode]) {
            $response = Response::error('Test', $status);
            $content = json_decode($response->getContent(), true);
            
            $this->assertSame($status, $response->getStatusCode());
            $this->assertSame($expectedCode, $content['error']['code']);
        }
    }

    public function testErrorMetaContainsTimestampAndRequestId(): void
    {
        $response = Response::error('Test', 400);
        $content = json_decode($response->getContent(), true);
        
        // Check timestamp format (ISO 8601)
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $content['meta']['timestamp']
        );
        
        // Check request_id format
        $this->assertStringStartsWith('req_', $content['meta']['request_id']);
    }

    public function testValidationErrorFormat(): void
    {
        $errors = [
            'email' => ['Email is required', 'Email must be valid'],
            'password' => ['Password must be at least 8 characters']
        ];
        
        $response = Response::error('Validation failed', 422, $errors);
        $content = json_decode($response->getContent(), true);
        
        // Validation errors should be in details
        $this->assertArrayHasKey('details', $content['error']);
        $this->assertSame($errors, $content['error']['details']);
        
        // Check structure
        $this->assertIsArray($content['error']['details']['email']);
        $this->assertCount(2, $content['error']['details']['email']);
        $this->assertCount(1, $content['error']['details']['password']);
    }

    public function testDebugInfoFormat(): void
    {
        // Simulate debug info
        $debugInfo = [
            'exception' => 'RuntimeException',
            'file' => '/path/to/file.php',
            'line' => 42,
            'trace' => ['trace1', 'trace2']
        ];
        
        $response = Response::error('Server error', 500, $debugInfo);
        $content = json_decode($response->getContent(), true);
        
        // Debug info should be in details
        $this->assertArrayHasKey('details', $content['error']);
        $this->assertSame('RuntimeException', $content['error']['details']['exception']);
        $this->assertSame('/path/to/file.php', $content['error']['details']['file']);
        $this->assertSame(42, $content['error']['details']['line']);
    }
}