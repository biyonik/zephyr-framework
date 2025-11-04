<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Zephyr\Http\Response;
use Zephyr\Exceptions\Http\JsonEncodingException;

/**
 * JSON Encoding Error Handling Tests
 * 
 * Tests that JSON encoding failures are properly handled
 * with helpful error messages and context.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class JsonEncodingTest extends TestCase
{
    /**
     * Test that valid data encodes successfully
     */
    public function testValidDataEncodesSuccessfully(): void
    {
        $response = Response::json(['name' => 'John', 'age' => 30]);
        
        $content = json_decode($response->getContent(), true);
        $this->assertSame('John', $content['name']);
        $this->assertSame(30, $content['age']);
    }

    /**
     * Test that recursive data throws JsonEncodingException
     */
    public function testRecursiveDataThrowsException(): void
    {
        $this->expectException(JsonEncodingException::class);
        $this->expectExceptionMessage('JSON encoding failed');
        
        // Create recursive structure
        $data = ['name' => 'Test'];
        $data['self'] = &$data;  // Circular reference
        
        Response::json($data);
    }

    /**
     * Test that resource type throws JsonEncodingException
     */
    public function testResourceTypeThrowsException(): void
    {
        $this->expectException(JsonEncodingException::class);
        
        // Resources cannot be JSON encoded
        $resource = fopen('php://memory', 'r');
        
        try {
            Response::json(['resource' => $resource]);
        } finally {
            fclose($resource);
        }
    }

    /**
     * Test JsonEncodingException contains data context
     */
    public function testExceptionContainsDataContext(): void
    {
        $resource = fopen('php://memory', 'r');
        
        try {
            Response::json(['resource' => $resource]);
            $this->fail('Should have thrown JsonEncodingException');
        } catch (JsonEncodingException $e) {
            // Check exception has data
            $data = $e->getData();
            $this->assertIsArray($data);
            $this->assertArrayHasKey('resource', $data);
            
            // Check error code
            $this->assertNotSame(JSON_ERROR_NONE, $e->getJsonError());
            
            // Check error message
            $this->assertNotEmpty($e->getJsonErrorMessage());
            
            // Check context
            $context = $e->getErrorContext();
            $this->assertArrayHasKey('error', $context);
            $this->assertArrayHasKey('error_code', $context);
            $this->assertArrayHasKey('suggestion', $context);
        } finally {
            fclose($resource);
        }
    }

    /**
     * Test fromLastError() factory method
     */
    public function testFromLastErrorFactoryMethod(): void
    {
        // Trigger JSON error
        $data = ['name' => "\xB1\x31"];  // Invalid UTF-8
        $result = json_encode($data);
        
        if ($result === false) {
            $exception = JsonEncodingException::fromLastError($data);
            
            $this->assertInstanceOf(JsonEncodingException::class, $exception);
            $this->assertStringContainsString('JSON encoding failed', $exception->getMessage());
        }
    }

    /**
     * Test that exception message includes JSON error details
     */
    public function testExceptionMessageIncludesDetails(): void
    {
        $resource = fopen('php://memory', 'r');
        
        try {
            Response::json(['resource' => $resource]);
            $this->fail('Should have thrown exception');
        } catch (JsonEncodingException $e) {
            $message = $e->getMessage();
            
            // ✅ FIX: Check for case-insensitive "json" and "encoding"
            $lowerMessage = strtolower($message);
            $this->assertStringContainsString('json', $lowerMessage);
            $this->assertStringContainsString('encoding', $lowerMessage);
        } finally {
            fclose($resource);
        }
    }

    /**
     * Test getJsonErrorMessage() for different error types
     */
    public function testGetJsonErrorMessageForDifferentErrors(): void
    {
        // Create exception with specific error code
        $e = new JsonEncodingException(
            data: [],
            jsonError: JSON_ERROR_UTF8
        );
        
        $errorMsg = $e->getJsonErrorMessage();
        $this->assertStringContainsString('UTF-8', $errorMsg);
    }

    /**
     * Test getSuggestion() provides helpful advice
     */
    public function testGetSuggestionProvidesHelpfulAdvice(): void
    {
        $e = new JsonEncodingException(
            data: [],
            jsonError: JSON_ERROR_RECURSION
        );
        
        $context = $e->getErrorContext();
        $this->assertArrayHasKey('suggestion', $context);
        $this->assertStringContainsString('circular', strtolower($context['suggestion']));
    }

    /**
     * Test that NAN and INF values are caught
     */
    public function testNanAndInfValuesThrowException(): void
    {
        $this->expectException(JsonEncodingException::class);
        
        Response::json(['value' => NAN]);
    }

    /**
     * Test that closure in data throws exception
     */
    public function testClosureInDataThrowsException(): void
    {
        $this->expectException(JsonEncodingException::class);
        
        Response::json(['callback' => function() { return 'test'; }]);
    }

    /**
     * Test error context includes data type information
     */
    public function testErrorContextIncludesDataType(): void
    {
        $resource = fopen('php://memory', 'r');
        
        try {
            Response::json(['resource' => $resource]);
        } catch (JsonEncodingException $e) {
            $context = $e->getErrorContext();
            
            $this->assertArrayHasKey('data_type', $context);
            $this->assertSame('array', $context['data_type']);
        } finally {
            fclose($resource);
        }
    }

    /**
     * Test that object with circular reference is caught
     */
    public function testObjectWithCircularReferenceThrowsException(): void
    {
        $this->expectException(JsonEncodingException::class);
        
        $obj1 = new \stdClass();
        $obj2 = new \stdClass();
        
        $obj1->ref = $obj2;
        $obj2->ref = $obj1;  // Circular
        
        Response::json(['object' => $obj1]);
    }

    /**
     * Test that deeply nested structures can be encoded
     * (up to default depth limit)
     */
    public function testDeeplyNestedStructuresEncode(): void
    {
        // Create nested structure (reasonable depth)
        $data = ['level' => 1];
        $current = &$data;
        
        for ($i = 2; $i <= 50; $i++) {
            $current['nested'] = ['level' => $i];
            $current = &$current['nested'];
        }
        
        // Should encode successfully (under default depth limit of 512)
        $response = Response::json($data);
        $this->assertNotEmpty($response->getContent());
    }

    /**
     * Test that extremely deeply nested structures throw exception
     */
    public function testExtremelyDeeplyNestedStructuresThrowException(): void
    {
        $this->expectException(JsonEncodingException::class);
        
        // Create structure deeper than JSON depth limit
        $data = ['level' => 1];
        $current = &$data;
        
        for ($i = 2; $i <= 600; $i++) {  // Deeper than default 512
            $current['nested'] = ['level' => $i];
            $current = &$current['nested'];
        }
        
        Response::json($data);
    }

    /**
     * Test HTTP status code for JSON encoding exception
     */
    public function testJsonEncodingExceptionHas500StatusCode(): void
    {
        $e = new JsonEncodingException(data: []);
        
        $this->assertSame(500, $e->getStatusCode());
    }

    /**
     * Test that simple arrays encode without issues
     */
    public function testSimpleArraysEncodeSuccessfully(): void
    {
        $arrays = [
            ['simple' => 'array'],
            ['numbers' => [1, 2, 3]],
            ['mixed' => ['string', 123, true, null]],
            ['unicode' => ['emoji' => '😀', 'text' => 'Türkçe']],
        ];
        
        foreach ($arrays as $array) {
            $response = Response::json($array);
            $decoded = json_decode($response->getContent(), true);
            $this->assertEquals($array, $decoded);
        }
    }

    /**
     * Test that UTF-8 strings encode properly
     */
    public function testUtf8StringsEncodeProper(): void
    {
        $data = [
            'turkish' => 'Şişli İstanbul Türkiye',
            'emoji' => '🚀 🎯 ✅',
            'chinese' => '你好世界',
            'arabic' => 'مرحبا بالعالم',
        ];
        
        $response = Response::json($data);
        $content = $response->getContent();
        
        // Should contain original UTF-8 characters (JSON_UNESCAPED_UNICODE)
        $this->assertStringContainsString('Şişli', $content);
        $this->assertStringContainsString('🚀', $content);
    }
}