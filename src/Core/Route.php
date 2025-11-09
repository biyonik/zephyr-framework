<?php

declare(strict_types=1);

namespace Zephyr\Core;

use Closure;
use Zephyr\Http\{Request, Response};

/**
 * Route Instance
 * 
 * Represents a single route with its pattern, action, and middleware.
 * Handles pattern compilation and parameter extraction with constraint validation.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Route
{
    /**
     * HTTP methods for this route
     */
    protected array $methods;

    /**
     * URI pattern
     */
    protected string $uri;

    /**
     * Route action (Closure, Controller@method, or [Controller, method])
     */
    protected Closure|array|string $action;

    /**
     * Route middleware
     */
    protected array $middleware = [];

    /**
     * Route namespace
     */
    protected ?string $namespace = null;

    /**
     * Compiled regex pattern
     */
    protected ?string $regex = null;

    /**
     * Parameter names from the pattern
     */
    protected array $parameterNames = [];

    /**
     * Route name
     */
    protected ?string $name = null;

    /**
     * Where constraints for parameters
     * 
     * @var array<string, string>
     */
    protected array $wheres = [];

    /**
     * Default constraint patterns for parameter types
     */
    protected const DEFAULT_CONSTRAINTS = [
        'id' => '[0-9]+',           // Numeric IDs
        'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
        'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*',  // URL-friendly slugs
        'hash' => '[a-zA-Z0-9]+',   // Alphanumeric hash
    ];

    /**
     * Constructor
     */
    public function __construct(array $methods, string $uri, Closure|array|string $action)
    {
        $this->methods = $methods;
        $this->uri = $uri;
        $this->action = $action;
        $this->compile();
    }

    /**
     * Compile the route pattern to regex with constraint validation
     * 
     * Transforms route patterns like:
     * - /users/{id} → ^/users/([^/]+)$
     * - /users/{id} with where('id', '[0-9]+') → ^/users/([0-9]+)$
     * - /posts/{slug?} → ^/posts/?([^/]*)$
     * 
     * Constraint priority:
     * 1. Explicit where() constraints
     * 2. Default constraints for known parameter names (id, uuid, etc.)
     * 3. Generic constraint ([^/]+ for required, [^/]* for optional)
     */
    protected function compile(): void
    {
        // Reset compilation state
        $this->parameterNames = [];
        
        // Check if it's a static route (no parameters)
        if (!str_contains($this->uri, '{')) {
            $this->regex = null;
            return;
        }
        
        $pattern = $this->uri;
        
        // ✅ FIX 1: Handle optional parameters with constraints
        // Pattern: {slug?} → /?constraint (slash is optional too)
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\?\}/',
            function ($matches) {
                $paramName = $matches[1];
                $this->parameterNames[] = $paramName;
                
                // Get constraint: explicit > default > generic
                $constraint = $this->getConstraintForParameter($paramName, true);
                
                // Make the preceding slash optional too
                return "/?({$constraint})";
            },
            $pattern
        );
        
        // ✅ FIX 2: Handle required parameters with constraints
        // Pattern: {id} → constraint from $this->wheres or default
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            function ($matches) {
                $paramName = $matches[1];
                $this->parameterNames[] = $paramName;
                
                // Get constraint: explicit > default > generic
                $constraint = $this->getConstraintForParameter($paramName, false);
                
                return "({$constraint})";
            },
            $pattern
        );
        
        // ✅ FIX 3: Build full regex with anchors
        $this->regex = '#^' . $pattern . '$#';
    }

    /**
     * Get constraint pattern for a parameter
     * 
     * Priority:
     * 1. Explicit where() constraint
     * 2. Default constraint for known parameter names
     * 3. Generic constraint
     * 
     * @param string $paramName Parameter name
     * @param bool $optional Whether parameter is optional
     * @return string Regex constraint pattern
     */
    protected function getConstraintForParameter(string $paramName, bool $optional): string
    {
        // Priority 1: Explicit where() constraint
        if (isset($this->wheres[$paramName])) {
            $constraint = $this->wheres[$paramName];
            
            // For optional parameters, make constraint optional too
            return $optional ? "{$constraint}*" : $constraint;
        }
        
        // Priority 2: Default constraint for known parameter names
        if (isset(self::DEFAULT_CONSTRAINTS[$paramName])) {
            $constraint = self::DEFAULT_CONSTRAINTS[$paramName];
            
            // For optional parameters, make constraint optional
            return $optional ? "(?:{$constraint})?" : $constraint;
        }
        
        // Priority 3: Generic constraint
        return $optional ? '[^/]*' : '[^/]+';
    }

    /**
     * Check if route matches the given URI
     */
    public function matches(string $uri): bool
    {
        // Normalize URI
        $uri = rtrim($uri, '/') ?: '/';
        $routeUri = rtrim($this->uri, '/') ?: '/';
        
        // Static route check (fast path)
        if ($this->regex === null) {
            return $uri === $routeUri;
        }
        
        // ✅ Dynamic route check with constraint validation
        return (bool) preg_match($this->regex, $uri);
    }

    /**
     * Extract parameters from URI
     */
    public function extractParameters(string $uri): array
    {
        // No parameters in this route
        if (empty($this->parameterNames)) {
            return [];
        }
        
        // Extract values using regex
        if (!preg_match($this->regex, $uri, $matches)) {
            return [];
        }
        
        // Remove the full match
        array_shift($matches);
        
        // Combine parameter names with values
        $parameters = [];
        foreach ($this->parameterNames as $index => $name) {
            $value = $matches[$index] ?? null;
            
            // ✅ Convert empty string to null for optional parameters
            $parameters[$name] = ($value === '' || $value === null) ? null : $value;
        }
        
        return $parameters;
    }

    /**
     * Execute the route action
     */
    public function execute(Request $request, array $parameters = []): Response
    {
        // Inject route parameters into request
        $request->setRouteParams($parameters);
        
        $action = $this->action;
        
        // Handle Closure action
        if ($action instanceof Closure) {
            $result = app()->call($action, $parameters);
            return $this->prepareResponse($result);
        }
        
        // Parse controller action
        if (is_string($action)) {
            if (str_contains($action, '@')) {
                [$controller, $method] = explode('@', $action);
            } else {
                throw new \InvalidArgumentException("Invalid route action format: {$action}");
            }
        } elseif (is_array($action)) {
            [$controller, $method] = $action;
        } else {
            throw new \InvalidArgumentException("Invalid route action type");
        }
        
        // Add namespace if needed
        if ($this->namespace && !str_starts_with($controller, '\\')) {
            $controller = $this->namespace . '\\' . $controller;
        }
        
        // Resolve controller from container
        $instance = app()->resolve($controller);
        
        // Call controller method with dependency injection
        $result = app()->call([$instance, $method], $parameters);
        
        return $this->prepareResponse($result);
    }

    /**
     * Prepare response from action result
     */
    protected function prepareResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        
        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }
        
        if (is_string($result)) {
            return new Response($result);
        }
        
        if (is_null($result)) {
            return Response::noContent();
        }
        
        return new Response((string) $result);
    }

    /**
     * Add middleware to the route
     */
    public function middleware(string|array $middleware): self
    {
        if (is_string($middleware)) {
            $middleware = [$middleware];
        }
        
        $this->middleware = array_merge($this->middleware, $middleware);
        
        return $this;
    }

    /**
     * Set route namespace
     */
    public function namespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * Set route name
     */
    public function name(string $name): self
    {
        $this->name = $name;
        
        // Register in router
        app()->router()->name($name, $this);
        
        return $this;
    }

    /**
     * Add where constraint
     * 
     * @param string|array $name Parameter name or array of [name => constraint]
     * @param string|null $expression Regex constraint expression
     * @return self
     * 
     * @example Single constraint
     * ```php
     * $route->where('id', '[0-9]+');
     * ```
     * 
     * @example Multiple constraints
     * ```php
     * $route->where([
     *     'id' => '[0-9]+',
     *     'slug' => '[a-z0-9-]+'
     * ]);
     * ```
     */
    public function where(string|array $name, ?string $expression = null): self
    {
        if (is_array($name)) {
            foreach ($name as $key => $value) {
                $this->validateAndSetConstraint($key, $value);
            }
        } else {
            $this->validateAndSetConstraint($name, $expression);
        }

        $this->compile();
        return $this;
    }

    protected function validateAndSetConstraint(string $paramName, string $pattern): void
    {
        // 1. Regex geçerliliğini kontrol et
        if (@preg_match("/{$pattern}/", '') === false) {
            throw new \InvalidArgumentException(
                "Invalid regex pattern for parameter [{$paramName}]: {$pattern}"
            );
        }

        // 2. Tehlikeli pattern'leri engelle (ReDoS protection)
        if ($this->isDangerousPattern($pattern)) {
            throw new \InvalidArgumentException(
                "Potentially dangerous regex pattern for parameter [{$paramName}]: {$pattern}"
            );
        }

        $this->wheres[$paramName] = $pattern;
    }

    protected function isDangerousPattern(string $pattern): bool
    {
        // Catastrophic backtracking riski olan pattern'ler
        $dangerousPatterns = [
            '/\(\?R\)/',           // Recursive pattern
            '/\(\?\d+\)/',         // Recursive subpattern
            '/\(\?P>/',            // Named recursion
            '/\(\?\#.*?\)/',       // Comment (genelde harmless ama güvenlik için)
        ];

        foreach ($dangerousPatterns as $dangerous) {
            if (preg_match($dangerous, $pattern)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Add numeric constraint (shorthand for where with [0-9]+)
     * 
     * @param string|array $parameters Parameter name(s)
     * @return self
     * 
     * @example
     * ```php
     * $route->whereNumber('id');
     * $route->whereNumber(['id', 'page']);
     * ```
     */
    public function whereNumber(string|array $parameters): self
    {
        $parameters = (array) $parameters;
        
        foreach ($parameters as $parameter) {
            $this->where($parameter, '[0-9]+');
        }
        
        return $this;
    }

    /**
     * Add alpha constraint (shorthand for where with [a-zA-Z]+)
     * 
     * @param string|array $parameters Parameter name(s)
     * @return self
     */
    public function whereAlpha(string|array $parameters): self
    {
        $parameters = (array) $parameters;
        
        foreach ($parameters as $parameter) {
            $this->where($parameter, '[a-zA-Z]+');
        }
        
        return $this;
    }

    /**
     * Add alphanumeric constraint (shorthand for where with [a-zA-Z0-9]+)
     * 
     * @param string|array $parameters Parameter name(s)
     * @return self
     */
    public function whereAlphaNumeric(string|array $parameters): self
    {
        $parameters = (array) $parameters;
        
        foreach ($parameters as $parameter) {
            $this->where($parameter, '[a-zA-Z0-9]+');
        }
        
        return $this;
    }

    /**
     * Add UUID constraint
     * 
     * @param string|array $parameters Parameter name(s)
     * @return self
     */
    public function whereUuid(string|array $parameters): self
    {
        $parameters = (array) $parameters;
        
        foreach ($parameters as $parameter) {
            $this->where($parameter, '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
        }
        
        return $this;
    }

    /**
     * Add 'in' constraint (value must be in list)
     * 
     * @param string $parameter Parameter name
     * @param array $values Allowed values
     * @return self
     * 
     * @example
     * ```php
     * $route->whereIn('status', ['active', 'inactive', 'pending']);
     * ```
     */
    public function whereIn(string $parameter, array $values): self
    {
        // Escape values and join with |
        $escaped = array_map(fn($v) => preg_quote((string) $v, '#'), $values);
        $pattern = implode('|', $escaped);
        
        return $this->where($parameter, "(?:{$pattern})");
    }

    /**
     * Generate URL for this route
     */
    public function url(array $parameters = []): string
    {
        $url = $this->uri;
        
        foreach ($parameters as $key => $value) {
            $url = str_replace(['{' . $key . '}', '{' . $key . '?}'], $value, $url);
        }
        
        // Remove any remaining optional parameters
        $url = preg_replace('/\{[^}]+\?\}/', '', $url);
        
        return $url;
    }

    /**
     * Get route methods
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Get route URI
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get route action
     */
    public function getAction(): Closure|array|string
    {
        return $this->action;
    }

    /**
     * Get route middleware
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Get route name
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get route constraints
     * 
     * @return array<string, string>
     */
    public function getWheres(): array
    {
        return $this->wheres;
    }

    /**
     * Get compiled regex pattern
     */
    public function getRegex(): ?string
    {
        return $this->regex;
    }
}