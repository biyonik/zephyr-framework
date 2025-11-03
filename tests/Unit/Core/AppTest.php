<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Zephyr\Core\App;
use Zephyr\Support\{Config, Env};

/**
 * Application Core Tests
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class AppTest extends TestCase
{
    protected App $app;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Get fresh app instance
        $basePath = dirname(__DIR__, 3);
        $this->app = App::getInstance($basePath);
    }

    public function testSingletonPattern(): void
    {
        $app1 = App::getInstance();
        $app2 = App::getInstance();
        
        $this->assertSame($app1, $app2);
    }

    public function testBasePathResolution(): void
    {
        $basePath = dirname(__DIR__, 3);
        
        $this->assertEquals($basePath, $this->app->basePath());
        $this->assertEquals($basePath . '/config', $this->app->basePath('config'));
    }

    public function testServiceContainer(): void
    {
        // Test binding
        $this->app->bind('test.service', function() {
            return new \stdClass();
        });
        
        $this->assertTrue($this->app->has('test.service'));
        
        // Test resolution
        $service = $this->app->resolve('test.service');
        $this->assertInstanceOf(\stdClass::class, $service);
    }

    public function testSingletonBinding(): void
    {
        $this->app->singleton('test.singleton', function() {
            return new \stdClass();
        });
        
        $service1 = $this->app->resolve('test.singleton');
        $service2 = $this->app->resolve('test.singleton');
        
        $this->assertSame($service1, $service2);
    }

    public function testInstanceBinding(): void
    {
        $instance = new \stdClass();
        $instance->test = 'value';
        
        $this->app->instance('test.instance', $instance);
        
        $resolved = $this->app->resolve('test.instance');
        $this->assertSame($instance, $resolved);
        $this->assertEquals('value', $resolved->test);
    }

    public function testEnvironmentDetection(): void
    {
        Config::set('app.env', 'testing');
        $this->assertEquals('testing', $this->app->environment());
        
        Config::set('app.env', 'production');
        $this->assertTrue($this->app->isProduction());
        
        Config::set('app.debug', true);
        $this->assertTrue($this->app->isDebug());
    }

    public function testVersion(): void
    {
        $this->assertEquals('1.0.0', $this->app->version());
    }
}