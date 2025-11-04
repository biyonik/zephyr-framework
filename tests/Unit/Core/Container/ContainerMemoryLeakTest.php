<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Container;

use PHPUnit\Framework\TestCase;
use Zephyr\Core\App;
use Zephyr\Exceptions\Container\{BindingResolutionException, CircularDependencyException};

/**
 * Container Memory Leak Tests
 * 
 * Tests that the resolution stack is properly cleaned up
 * even when exceptions are thrown during resolution.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class ContainerMemoryLeakTest extends TestCase
{
    protected App $app;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->app = App::getInstance(__DIR__ . '/../../../..');
    }

    /**
     * Test that resolution stack is cleaned up after successful resolution
     */
    public function testResolutionStackCleanedAfterSuccess(): void
    {
        $this->app->bind('test.service', fn() => new \stdClass());
        
        // Stack should be empty before
        $this->assertEmpty($this->app->getResolvingStack());
        
        // Resolve
        $this->app->resolve('test.service');
        
        // ✅ Stack should be empty after successful resolution
        $this->assertEmpty($this->app->getResolvingStack());
    }

    /**
     * Test that resolution stack is cleaned up even when exception thrown
     */
    public function testResolutionStackCleanedAfterException(): void
    {
        // Bind something that will fail
        $this->app->bind('test.failing', fn() => throw new \RuntimeException('Test failure'));
        
        try {
            $this->app->resolve('test.failing');
            $this->fail('Should have thrown exception');
        } catch (\RuntimeException $e) {
            // Expected
        }
        
        // ✅ Stack should be empty even after exception
        $this->assertEmpty($this->app->getResolvingStack());
    }

    /**
     * Test that resolution stack is cleaned up with nested resolution failures
     */
    public function testNestedResolutionStackCleanup(): void
    {
        // ServiceA depends on ServiceB which depends on failing ServiceC
        $this->app->bind('service.c', fn() => throw new \RuntimeException('C fails'));
        $this->app->bind('service.b', function($app) {
            return new class($app->resolve('service.c')) {};
        });
        $this->app->bind('service.a', function($app) {
            return new class($app->resolve('service.b')) {};
        });
        
        try {
            $this->app->resolve('service.a');
            $this->fail('Should have thrown exception');
        } catch (\RuntimeException $e) {
            // Expected
        }
        
        // ✅ Stack should be completely empty
        $this->assertEmpty($this->app->getResolvingStack());
    }

    /**
     * Test that circular dependency is still detected correctly
     */
    public function testCircularDependencyDetection(): void
    {
        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage('Circular dependency detected');
        
        // ServiceA → ServiceB → ServiceA (circular)
        $this->app->bind('service.a', function($app) {
            return new class($app->resolve('service.b')) {};
        });
        $this->app->bind('service.b', function($app) {
            return new class($app->resolve('service.a')) {};
        });
        
        $this->app->resolve('service.a');
    }

    /**
     * Test that stack is cleaned up after circular dependency exception
     */
    public function testStackCleanedAfterCircularDependency(): void
    {
        // ServiceA → ServiceB → ServiceA (circular)
        $this->app->bind('service.a', function($app) {
            return new class($app->resolve('service.b')) {};
        });
        $this->app->bind('service.b', function($app) {
            return new class($app->resolve('service.a')) {};
        });
        
        try {
            $this->app->resolve('service.a');
            $this->fail('Should have thrown CircularDependencyException');
        } catch (CircularDependencyException $e) {
            // Expected
        }
        
        // ✅ Stack should be clean even after circular dependency
        $this->assertEmpty($this->app->getResolvingStack());
    }

    /**
     * Test that multiple failed resolutions don't leak
     */
    public function testMultipleFailedResolutionsNoLeak(): void
    {
        $this->app->bind('failing.service', fn() => throw new \RuntimeException('Fail'));
        
        // Try resolving multiple times
        for ($i = 0; $i < 10; $i++) {
            try {
                $this->app->resolve('failing.service');
            } catch (\RuntimeException $e) {
                // Expected
            }
            
            // Stack should be empty after each attempt
            $this->assertEmpty(
                $this->app->getResolvingStack(),
                "Stack not empty after attempt {$i}"
            );
        }
    }

    /**
     * Test that stack correctly tracks nested resolutions
     */
    public function testStackTracksNestedResolutions(): void
    {
        // Capture stack at different depths
        $stackSnapshots = [];
        
        $this->app->bind('level.3', function($app) use (&$stackSnapshots) {
            $stackSnapshots['level3'] = $app->getResolvingStack();
            return new \stdClass();
        });
        
        $this->app->bind('level.2', function($app) use (&$stackSnapshots) {
            $stackSnapshots['level2'] = $app->getResolvingStack();
            $app->resolve('level.3');
            return new \stdClass();
        });
        
        $this->app->bind('level.1', function($app) use (&$stackSnapshots) {
            $stackSnapshots['level1'] = $app->getResolvingStack();
            $app->resolve('level.2');
            return new \stdClass();
        });
        
        $this->app->resolve('level.1');
        
        // Verify stack depths
        $this->assertCount(1, $stackSnapshots['level1']);
        $this->assertCount(2, $stackSnapshots['level2']);
        $this->assertCount(3, $stackSnapshots['level3']);
        
        // Verify stack contents
        $this->assertSame(['level.1'], $stackSnapshots['level1']);
        $this->assertSame(['level.1', 'level.2'], $stackSnapshots['level2']);
        $this->assertSame(['level.1', 'level.2', 'level.3'], $stackSnapshots['level3']);
        
        // Final stack should be empty
        $this->assertEmpty($this->app->getResolvingStack());
    }

    /**
     * Test that BindingResolutionException doesn't leak stack
     */
    public function testBindingResolutionExceptionNoLeak(): void
    {
        // Try to resolve non-existent service
        try {
            $this->app->resolve('NonExistentService');
            $this->fail('Should have thrown BindingResolutionException');
        } catch (BindingResolutionException $e) {
            // Expected
        }
        
        // Stack should be empty
        $this->assertEmpty($this->app->getResolvingStack());
    }

    /**
     * Test concurrent-like resolution attempts (simulated)
     */
    public function testSimulatedConcurrentResolutions(): void
    {
        $this->app->bind('shared.service', fn() => new \stdClass());
        
        // Simulate multiple "concurrent" resolution attempts
        // (In reality, PHP is single-threaded, but this tests isolation)
        for ($i = 0; $i < 5; $i++) {
            $this->app->resolve('shared.service');
            $this->assertEmpty($this->app->getResolvingStack());
        }
    }

    /**
     * Test that flush() properly clears resolution stack
     */
    public function testFlushClearsResolutionStack(): void
    {
        // This shouldn't happen in normal code, but test edge case
        // where flush() is called during resolution
        
        $this->app->bind('test.service', function($app) {
            // Simulate some state in stack
            $stack = $app->getResolvingStack();
            $this->assertNotEmpty($stack);
            
            // Flush during resolution (edge case)
            $app->flush();
            
            // Stack should be empty after flush
            $this->assertEmpty($app->getResolvingStack());
            
            return new \stdClass();
        });
        
        try {
            $this->app->resolve('test.service');
        } catch (\Throwable $e) {
            // May throw due to flush, that's ok
        }
        
        // Final state should be clean
        $this->assertEmpty($this->app->getResolvingStack());
    }

    /**
     * Memory leak detection test (practical)
     */
    public function testNoMemoryLeakInPractice(): void
    {
        $initialMemory = memory_get_usage();
        
        // Perform many failed resolutions
        for ($i = 0; $i < 1000; $i++) {
            try {
                $this->app->resolve('NonExistent' . $i);
            } catch (BindingResolutionException $e) {
                // Expected
            }
        }
        
        // Force garbage collection
        gc_collect_cycles();
        
        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;
        
        // Memory increase should be minimal (< 100KB for 1000 attempts)
        // If stack is leaking, this would be much higher
        $this->assertLessThan(100 * 1024, $memoryIncrease, 
            "Memory leak detected: {$memoryIncrease} bytes increased"
        );
    }
}