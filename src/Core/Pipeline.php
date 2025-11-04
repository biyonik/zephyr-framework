<?php

declare(strict_types=1);

namespace Zephyr\Core;

use Closure;
use Zephyr\Http\{Request, Response};
use Zephyr\Http\Middleware\MiddlewareInterface;

/**
 * Middleware Pipeline
 *
 * Implements the "Onion Model" middleware pattern.
 * Passes a request through a series of middleware layers,
 * each of which can inspect/modify the request or response.
 *
 * Execution flow:
 * 1. Request enters pipeline
 * 2. Each middleware's "before" logic runs
 * 3. Controller/destination executes
 * 4. Each middleware's "after" logic runs (in reverse order)
 * 5. Response exits pipeline
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Pipeline
{
    /**
     * The object being passed through the pipeline
     */
    protected Request $passable;

    /**
     * The array of middleware to pipe through
     *
     * @var array<string|MiddlewareInterface>
     */
    protected array $pipes = [];

    /**
     * The method to call on each pipe
     */
    protected string $method = 'handle';

    /**
     * Application container for resolving middleware
     */
    protected App $container;

    /**
     * Constructor
     */
    public function __construct(App $container)
    {
        $this->container = $container;
    }

    /**
     * Set the object being sent through the pipeline
     *
     * @param Request $passable The request to pass through
     * @return self
     */
    public function send(Request $passable): self
    {
        $this->passable = $passable;
        return $this;
    }

    /**
     * Set the array of pipes (middleware)
     *
     * @param array<string|MiddlewareInterface|Closure> $pipes
     * @return self
     */
    public function through(array $pipes): self
    {
        $this->pipes = $pipes;
        return $this;
    }

    /**
     * Set the method to call on the pipes
     *
     * @param string $method Method name (default: 'handle')
     * @return self
     */
    public function via(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    /**
     * Run the pipeline with a final destination callback
     *
     * This is the main execution method. It builds a nested closure
     * chain representing the middleware stack, then executes it.
     *
     * @param Closure $destination Final destination (usually the controller)
     * @return Response
     *
     * @example
     * ```php
     * $response = (new Pipeline($app))
     *     ->send($request)
     *     ->through([AuthMiddleware::class, CorsMiddleware::class])
     *     ->then(function($request) {
     *         return $controller->handle($request);
     *     });
     * ```
     */
    public function then(Closure $destination): Response
    {
        // Build the pipeline: reduce middleware array to nested closures
        $pipeline = array_reduce(
        // Reverse middleware array so they execute in correct order
            array_reverse($this->pipes),
            // Carry function: wraps each middleware
            $this->carry(),
            // Initial value: the destination closure
            $this->prepareDestination($destination)
        );

        // Execute the pipeline
        return $pipeline($this->passable);
    }

    /**
     * Run the pipeline and return the result directly
     *
     * Alias for then() for better readability in some contexts.
     *
     * @param Closure $destination
     * @return Response
     */
    public function thenReturn(Closure $destination): Response
    {
        return $this->then($destination);
    }

    /**
     * Get the final piece of the closure onion
     *
     * Wraps the destination to ensure it always returns a Response.
     *
     * @param Closure $destination
     * @return Closure
     */
    protected function prepareDestination(Closure $destination): Closure
    {
        return function (Request $passable) use ($destination) {
            try {
                $response = $destination($passable);

                // Ensure we always return a Response object
                if (!$response instanceof Response) {
                    throw new \UnexpectedValueException(
                        'Pipeline destination must return a Response instance'
                    );
                }

                return $response;

            } catch (\Throwable $e) {
                // Let exceptions bubble up to be caught by Kernel
                throw $e;
            }
        };
    }

    /**
     * Get a closure that represents a slice of the application onion
     *
     * This is the heart of the pipeline. It returns a function that:
     * 1. Takes the current middleware stack
     * 2. Takes the next layer (middleware or destination)
     * 3. Returns a closure that executes the current middleware
     *    and passes control to the next layer
     *
     * @return Closure
     */
    protected function carry(): Closure
    {
        return function (Closure $stack, mixed $pipe): Closure {
            return function (Request $passable) use ($stack, $pipe): Response {
                try {
                    // Handle different pipe types
                    if ($pipe instanceof Closure) {
                        // Pipe is a closure - execute directly
                        return $pipe($passable, $stack);

                    } elseif ($pipe instanceof MiddlewareInterface) {
                        // Pipe is already an instance - call handle method
                        return $pipe->{$this->method}($passable, $stack);

                    } elseif (is_string($pipe)) {
                        // Pipe is a class name - resolve from container
                        $middleware = $this->container->resolve($pipe);

                        if (!$middleware instanceof MiddlewareInterface) {
                            throw new \InvalidArgumentException(
                                "Middleware [{$pipe}] must implement MiddlewareInterface"
                            );
                        }

                        return $middleware->{$this->method}($passable, $stack);

                    } else {
                        throw new \InvalidArgumentException(
                            'Middleware must be a Closure, MiddlewareInterface, or string class name'
                        );
                    }

                } catch (\Throwable $e) {
                    // Let exceptions bubble up
                    throw $e;
                }
            };
        };
    }

    /**
     * Parse middleware string for parameters
     *
     * Allows middleware to receive parameters like:
     * 'throttle:60,1' → ['throttle', ['60', '1']]
     *
     * @param string $pipe Middleware string
     * @return array{0: string, 1: array<string>} [className, parameters]
     */
    protected function parsePipeString(string $pipe): array
    {
        if (!str_contains($pipe, ':')) {
            return [$pipe, []];
        }

        [$name, $parameters] = explode(':', $pipe, 2);

        return [$name, explode(',', $parameters)];
    }

    /**
     * Get the current pipes
     *
     * @return array<string|MiddlewareInterface|Closure>
     */
    public function getPipes(): array
    {
        return $this->pipes;
    }

    /**
     * Get the passable object
     *
     * @return Request
     */
    public function getPassable(): Request
    {
        return $this->passable;
    }
}