<?php

namespace Tests\Unit\Core;

use Tests\TestCase;
use Zephyr\Core\App;
use Zephyr\Exceptions\Container\CircularDependencyException;

class ContainerTest extends TestCase
{
    public function test_singleton_binding_resolves_same_instance()
    {
        $this->app->singleton('test.singleton', fn() => new \stdClass());
        
        $instance1 = $this->app->resolve('test.singleton');
        $instance2 = $this->app->resolve('test.singleton');

        $this->assertSame($instance1, $instance2);
    }

    public function test_bind_resolves_different_instances()
    {
        $this->app->bind('test.bind', fn() => new \stdClass());
        
        $instance1 = $this->app->resolve('test.bind');
        $instance2 = $this->app->resolve('test.bind');

        $this->assertNotSame($instance1, $instance2);
    }

    public function test_auto_wiring_with_dependencies()
    {
        $this->app->singleton('Dependency', fn() => new \stdClass());
        
        // Container'a kayıtlı olmayan bir sınıfı çöz
        $instance = $this->app->resolve('Tests\Support\ClassWithDependency');

        $this->assertInstanceOf('Tests\Support\ClassWithDependency', $instance);
        $this->assertInstanceOf('stdClass', $instance->dep);
    }
    
    public function test_call_method_injects_dependencies()
    {
        $controller = new \Tests\Support\TestController();
        $this->app->instance('stdClass', (object)['name' => 'Zephyr']);

        // call() metodu hem 'stdClass'ı hem de 'Test' parametresini enjekte etmeli
        $response = $this->app->call([$controller, 'method'], ['param' => 'Test']);

        $this->assertEquals('Zephyr-Test', $response);
    }

    public function test_circular_dependency_throws_exception()
    {
        $this->expectException(CircularDependencyException::class); //

        $this->app->bind('A', fn(App $app) => $app->resolve('B'));
        $this->app->bind('B', fn(App $app) => $app->resolve('A'));
        
        $this->app->resolve('A');
    }
}
