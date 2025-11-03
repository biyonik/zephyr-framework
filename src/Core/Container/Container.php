<?php

declare(strict_types=1);

namespace Zephyr\Core\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use Zephyr\Exceptions\Container\{BindingResolutionException, CircularDependencyException};

/**
 * Service Container Implementation
 * 
 * Provides dependency injection with auto-wiring capabilities.
 * Supports singleton bindings, factory bindings, and automatic resolution.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
trait Container
{
    /**
     * The container's bindings
     * 
     * @var array<string, array{concrete: string|Closure, shared: bool}>
     */
    protected array $bindings = [];

    /**
     * The container's shared instances (singletons)
     * 
     * @var array<string, object>
     */
    protected array $instances = [];

    /**
     * Stack of currently resolving dependencies (for circular dependency detection)
     * 
     * @var array<string>
     */
    protected array $resolving = [];

    /**
     * Register a binding in the container
     */
    public function bind(string $abstract, string|Closure|null $concrete = null, bool $shared = false): void
    {
        $concrete ??= $abstract;

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];
    }

    /**
     * Register a singleton binding in the container
     */
    public function singleton(string $abstract, string|Closure|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance as singleton
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Resolve a service from the container
     * 
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     */
    public function resolve(string $abstract): mixed
    {
        // Check for circular dependencies
        if (in_array($abstract, $this->resolving, true)) {
            throw new CircularDependencyException(
                "Circular dependency detected: " . implode(' -> ', $this->resolving) . ' -> ' . $abstract
            );
        }

        // Return singleton if already resolved
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $this->resolving[] = $abstract;

        try {
            $concrete = $this->getConcrete($abstract);
            $instance = $this->build($concrete);

            // Store as singleton if marked as shared
            if ($this->isShared($abstract)) {
                $this->instances[$abstract] = $instance;
            }

            return $instance;
        } finally {
            array_pop($this->resolving);
        }
    }

    /**
     * Get the concrete implementation for an abstract
     */
    protected function getConcrete(string $abstract): string|Closure
    {
        if (!isset($this->bindings[$abstract])) {
            return $abstract;
        }

        return $this->bindings[$abstract]['concrete'];
    }

    /**
     * Check if a binding is registered as singleton
     */
    protected function isShared(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) && $this->bindings[$abstract]['shared'];
    }

    /**
     * Build an instance of the given concrete
     * 
     * @throws BindingResolutionException
     */
    protected function build(string|Closure $concrete): mixed
    {
        // If concrete is a closure, execute it
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new BindingResolutionException("Target class [{$concrete}] does not exist.", 0, $e);
        }

        // Check if class is instantiable
        if (!$reflector->isInstantiable()) {
            throw new BindingResolutionException("Target class [{$concrete}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        // If no constructor, instantiate without dependencies
        if (is_null($constructor)) {
            return new $concrete;
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters());

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve constructor dependencies
     * 
     * @param ReflectionParameter[] $parameters
     * @return array
     * @throws BindingResolutionException
     */
    protected function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            // Handle untyped or built-in typed parameters
            if (is_null($type) || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new BindingResolutionException(
                        "Cannot resolve parameter [{$parameter->getName()}] - no type hint or default value"
                    );
                }
                continue;
            }

            // Resolve class dependencies recursively
            $dependencies[] = $this->resolve($type->getName());
        }

        return $dependencies;
    }

    /**
     * Check if a service is bound
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Flush all bindings and instances
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->resolving = [];
    }

    /**
     * Get all bindings (for debugging)
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Call a method with automatic dependency injection
     */
    public function call(callable|array $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            [$class, $method] = $callback;
            
            if (is_string($class)) {
                $class = $this->resolve($class);
            }
            
            $reflector = new ReflectionClass($class);
            $method = $reflector->getMethod($method);
            $dependencies = $this->resolveDependencies($method->getParameters());
            
            return $method->invokeArgs($class, array_merge($dependencies, $parameters));
        }

        return call_user_func_array($callback, $parameters);
    }
}