<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use PHPUnit\Framework\TestCase;
use Zephyr\Core\App;

/**
 * Type Coercion Tests
 * 
 * Tests that route parameters (strings) are properly coerced
 * to typed controller parameters.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class TypeCoercionTest extends TestCase
{
    protected App $app;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->app = App::getInstance(__DIR__ . '/../../../..');
    }

    public function testIntParameterCoercion(): void
    {
        $controller = new class {
            public function show(int $id): int {
                return $id * 2;
            }
        };
        
        // Route provides string
        $result = $this->app->call([$controller, 'show'], ['id' => '123']);
        
        // ✅ Now works!
        $this->assertSame(246, $result);
        $this->assertIsInt($result);
    }

    public function testFloatParameterCoercion(): void
    {
        $controller = new class {
            public function calculate(float $price): float {
                return $price * 1.20; // +20% tax
            }
        };
        
        $result = $this->app->call([$controller, 'calculate'], ['price' => '99.99']);
        
        $this->assertEqualsWithDelta(119.988, $result, 0.001);
        $this->assertIsFloat($result);
    }

    public function testBoolParameterCoercion(): void
    {
        $controller = new class {
            public function toggle(bool $active): string {
                return $active ? 'ON' : 'OFF';
            }
        };
        
        // Test truthy values
        $this->assertSame('ON', $this->app->call([$controller, 'toggle'], ['active' => '1']));
        $this->assertSame('ON', $this->app->call([$controller, 'toggle'], ['active' => 'true']));
        $this->assertSame('ON', $this->app->call([$controller, 'toggle'], ['active' => 'yes']));
        
        // Test falsy values
        $this->assertSame('OFF', $this->app->call([$controller, 'toggle'], ['active' => '0']));
        $this->assertSame('OFF', $this->app->call([$controller, 'toggle'], ['active' => 'false']));
        $this->assertSame('OFF', $this->app->call([$controller, 'toggle'], ['active' => 'no']));
    }

    public function testArrayParameterCoercion(): void
    {
        $controller = new class {
            public function sum(array $numbers): int {
                return array_sum($numbers);
            }
        };
        
        // If single value comes as string, wrap in array
        $result = $this->app->call([$controller, 'sum'], ['numbers' => '5']);
        $this->assertSame(5, $result);
        
        // If already array, use as-is
        $result = $this->app->call([$controller, 'sum'], ['numbers' => [1, 2, 3]]);
        $this->assertSame(6, $result);
    }

    public function testStringParameterNoConversion(): void
    {
        $controller = new class {
            public function greet(string $name): string {
                return "Hello, {$name}!";
            }
        };
        
        // Strings stay as strings
        $result = $this->app->call([$controller, 'greet'], ['name' => 'Alice']);
        $this->assertSame('Hello, Alice!', $result);
    }

    public function testInvalidIntConversionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot cast non-numeric string");
        
        $controller = new class {
            public function show(int $id): int {
                return $id;
            }
        };
        
        // Non-numeric string should throw
        $this->app->call([$controller, 'show'], ['id' => 'abc']);
    }

    public function testInvalidFloatConversionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $controller = new class {
            public function calculate(float $value): float {
                return $value;
            }
        };
        
        $this->app->call([$controller, 'calculate'], ['value' => 'not-a-number']);
    }

    public function testMixedTypedParameters(): void
    {
        $controller = new class {
            public function process(int $id, string $name, bool $active): array {
                return [
                    'id' => $id,
                    'name' => $name,
                    'active' => $active
                ];
            }
        };
        
        $result = $this->app->call([$controller, 'process'], [
            'id' => '42',
            'name' => 'Test',
            'active' => '1'
        ]);
        
        $this->assertSame(42, $result['id']);
        $this->assertIsInt($result['id']);
        
        $this->assertSame('Test', $result['name']);
        $this->assertIsString($result['name']);
        
        $this->assertTrue($result['active']);
        $this->assertIsBool($result['active']);
    }
}