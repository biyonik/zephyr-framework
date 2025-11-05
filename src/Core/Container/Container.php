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
 * * Provides dependency injection with auto-wiring capabilities.
 * Supports singleton bindings, factory bindings, and automatic resolution.
 * * FIXED: Memory leak in circular dependency detection
 * FIXED: Type coercion for builtin types
 * (GÜNCELLENDİ - Rapor #2: Reflection Overhead)
 * * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
trait Container
{
    /**
     * The container's bindings
     * * @var array<string, array{concrete: string|Closure, shared: bool}>
     */
    protected array $bindings = [];

    /**
     * The container's shared instances (singletons)
     * * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * Stack of currently resolving dependencies (for circular dependency detection)
     * * @var array<string>
     */
    protected array $resolving = [];

    /**
     * YENİ: Reflection nesneleri için runtime önbelleği.
     * (Rapor #2 Çözümü)
     *
     * @var array<string, ReflectionClass>
     */
    protected array $reflectionCache = [];

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
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Resolve a service from the container
     * * (Not: Bu metot App.php'de override edildi,
     * bu yüzden bu trait'in ana 'resolve' metodu değil,
     * App.php'deki 'resolve' metodu çalışır.)
     * * @param string $abstract Class or interface name to resolve
     * @return mixed Resolved instance
     * * @throws BindingResolutionException If resolution fails
     * @throws CircularDependencyException If circular dependency detected
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

        // ✅ FIX: Push to stack at the start
        $this->resolving[] = $abstract;

        try {
            // Get concrete implementation
            $concrete = $this->getConcrete($abstract);
            
            // Build instance
            $instance = $this->build($concrete);

            // Store as singleton if marked as shared
            if ($this->isShared($abstract)) {
                $this->instances[$abstract] = $instance;
            }

            return $instance;
            
        } finally {
            // ✅ FIX: Always clean up stack, even if exception thrown
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
     * (GÜNCELLENDİ - Rapor #2: Reflection Overhead)
     * * @throws BindingResolutionException
     */
    protected function build(string|Closure $concrete): mixed
    {
        // If concrete is a closure, execute it
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        // --- YENİ: ÖNBELLEK KONTROLÜ (Rapor #2 Çözümü) ---
        if (isset($this->reflectionCache[$concrete])) {
            $reflector = $this->reflectionCache[$concrete];
        } else {
            // Önbellekte yoksa oluştur ve önbelleğe al
            try {
                $reflector = new ReflectionClass($concrete); //
                $this->reflectionCache[$concrete] = $reflector; // Önbelleğe al
            } catch (ReflectionException $e) {
                throw new BindingResolutionException("Target class [{$concrete}] does not exist.", 0, $e); //
            }
        }
        // --- YENİ KONTROL SONU ---


        // Check if class is instantiable
        if (!$reflector->isInstantiable()) {
            throw new BindingResolutionException("Target class [{$concrete}] is not instantiable."); //
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
     * * @param ReflectionParameter[] $parameters
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
            // *** NOT: Burası App::resolve()'u çağırır ***
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
        $this->reflectionCache = []; // YENİ: Önbelleği de temizle
    }

    /**
     * Get all bindings (for debugging)
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get current resolution stack (for debugging)
     * * @return array<string>
     */
    public function getResolvingStack(): array
    {
        return $this->resolving;
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

            // YENİ: (Rapor #2) build() metodundaki aynı mantığı
            // buraya da uygulayarak ReflectionClass'ı önbelleğe alalım.
            $className = get_class($class);
            if (isset($this->reflectionCache[$className])) {
                $reflector = $this->reflectionCache[$className];
            } else {
                $reflector = new ReflectionClass($class);
                $this->reflectionCache[$className] = $reflector;
            }
            
            $methodReflector = $reflector->getMethod($method);
            $dependencies = $this->resolveMethodDependencies($methodReflector->getParameters(), $parameters);

            return $methodReflector->invokeArgs($class, $dependencies);
        }

        // For closures and functions
        if ($callback instanceof Closure) {
            $reflection = new \ReflectionFunction($callback);
            $dependencies = $this->resolveMethodDependencies($reflection->getParameters(), $parameters);
            return $callback(...$dependencies);
        }

        return call_user_func_array($callback, $parameters);
    }

    /**
     * Resolve method dependencies with support for route parameters
     * * ✅ FIXED: Type coercion for builtin types
     * * @param ReflectionParameter[] $reflectionParams
     * @param array $parameters Route parameters or manual parameters
     * @return array
     */
    protected function resolveMethodDependencies(array $reflectionParams, array $parameters = []): array
    {
        $dependencies = [];

        foreach ($reflectionParams as $param) {
            $name = $param->getName();
            $type = $param->getType();

            // Check if we have a value for this parameter by name
            if (array_key_exists($name, $parameters)) {
                $value = $parameters[$name];

                // ✅ Type coercion for builtin types
                if ($type && $type->isBuiltin() && !is_null($value)) {
                    $value = $this->castToType($value, $type->getName());
                }

                $dependencies[] = $value;
                continue;
            }

            // If no type hint, try to get from parameters or use default
            if (is_null($type) || $type->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    // For backward compatibility, add remaining parameters in order
                    $dependencies[] = array_shift($parameters);
                }
                continue;
            }

            // Resolve class dependencies from container
            $dependencies[] = $this->resolve($type->getName());
        }

        return $dependencies;
    }

    /**
     * Cast value to specified builtin type
     * * Safely converts string values (from route parameters) to expected types.
     * Handles common PHP builtin types with proper validation.
     * * ✅ FIXED: Type name mapping (int vs integer, bool vs boolean)
     * * @param mixed $value Value to cast
     * @param string $type Target type name
     * @return mixed Casted value
     */
    protected function castToType(mixed $value, string $type): mixed
    {
        // ✅ FIX: PHP type name mapping
        // gettype() returns 'integer' but type hint is 'int'
        $typeMap = [
            'int' => 'integer',
            'bool' => 'boolean',
            'float' => 'double',
        ];

        $phpType = $typeMap[$type] ?? $type;

        // If already correct type, return as-is
        if (gettype($value) === $phpType) {
            return $value;
        }

        return match ($type) {
            'int' => $this->castToInt($value),
            'float' => $this->castToFloat($value),
            'bool' => $this->castToBool($value),
            'string' => (string) $value,
            'array' => $this->castToArray($value),
            default => $value
        };
    }

    /**
     * Cast to integer with validation
     * * @throws \InvalidArgumentException If value cannot be converted to int
     */
    protected function castToInt(mixed $value): int
    {
        // Numeric string check
        if (is_string($value)) {
            if (!is_numeric($value)) {
                throw new \InvalidArgumentException(
                    "Cannot cast non-numeric string '{$value}' to int"
                );
            }
            return (int) $value;
        }

        // Direct cast for other types
        return (int) $value;
    }

    /**
     * Cast to float with validation
     * * @throws \InvalidArgumentException If value cannot be converted to float
     */
    protected function castToFloat(mixed $value): float
    {
        if (is_string($value)) {
            if (!is_numeric($value)) {
                throw new \InvalidArgumentException(
                    "Cannot cast non-numeric string '{$value}' to float"
                );
            }
            return (float) $value;
        }

        return (float) $value;
    }

    /**
     * Cast to boolean
     * * Handles common truthy/falsy representations:
     * - "1", "true", "yes", "on" → true
     * - "0", "false", "no", "off" → false
     */
    protected function castToBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower($value);

            if (in_array($lower, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($lower, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        // Use PHP's built-in boolean conversion
        return (bool) $value;
    }

    /**
     * Cast to array
     */
    protected function castToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        // Wrap non-array values
        return [$value];
    }
}