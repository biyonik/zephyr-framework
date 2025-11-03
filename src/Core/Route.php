<?php

declare(strict_types=1);

namespace Zephyr\Core;

use Closure;
use Zephyr\Http\{Request, Response};

/**
 * Route Instance
 * 
 * Represents a single route with its pattern, action, and middleware.
 * Handles pattern compilation and parameter extraction.
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
     * Where constraints
     */
    protected array $wheres = [];

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
     * Compile the route pattern to regex
     */
    protected function compile(): void
    {
        // Reset
        $this->parameterNames = [];
        
        // Check if it's a static route (no parameters)
        if (!str_contains($this->uri, '{')) {
            $this->regex = null;
            return;
        }
        
        $pattern = $this->uri;
        
        // Extract parameter names and replace with regex
        // {id} -> ([^/]+)
        // {slug?} -> ([^/]*)
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\?\}/',
            function ($matches) {
                $this->parameterNames[] = $matches[1];
                $constraint = $this->wheres[$matches[1]] ?? '[^/]*';
                return "({$constraint})";
            },
            $pattern
        );
        
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            function ($matches) {
                $this->parameterNames[] = $matches[1];
                $constraint = $this->wheres[$matches[1]] ?? '[^/]+';
                return "({$constraint})";
            },
            $pattern
        );
        
        $this->regex = '#^' . $pattern . '$#';
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
        
        // Dynamic route check
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
        preg_match($this->regex, $uri, $matches);
        
        // Remove the full match
        array_shift($matches);
        
        // Combine parameter names with values
        $parameters = [];
        foreach ($this->parameterNames as $index => $name) {
            $parameters[$name] = $matches[$index] ?? null;
        }
        
        return $parameters;
    }

    /**
     * Execute the route action
     */
    public function execute(Request $request, array $parameters = []): Response
    {
        $action = $this->action;
        
        // Closure
        if ($action instanceof Closure) {
            $result = app()->call($action, $parameters);
            return $this->prepareResponse($result);
        }
        
        // Controller@method string format
        if (is_string($action)) {
            if (str_contains($action, '@')) {
                [$controller, $method] = explode('@', $action);
            } else {
                throw new \InvalidArgumentException("Invalid route action format: {$action}");
            }
        }
        // [Controller, method] array format
        elseif (is_array($action)) {
            [$controller, $method] = $action;
        }
        else {
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
     */
    public function where(string|array $name, ?string $expression = null): self
    {
        if (is_array($name)) {
            $this->wheres = array_merge($this->wheres, $name);
        } else {
            $this->wheres[$name] = $expression;
        }
        
        // Recompile with new constraints
        $this->compile();
        
        return $this;
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
}