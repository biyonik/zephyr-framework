<?php

declare(strict_types=1);

namespace Zephyr\Core;

use Dotenv\Dotenv;
use Zephyr\Core\Container\Container;
use Zephyr\Http\{Request, Response};
use Zephyr\Http\Kernel;
use Zephyr\Support\{Config, Env};
use Zephyr\Exceptions\Handler as ExceptionHandler;

/**
 * Application Core Class
 * 
 * The heart of the Zephyr Framework. Manages the application lifecycle,
 * service container, configuration, and request handling.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class App
{
    use Container;

    /**
     * Framework version
     */
    public const VERSION = '1.0.0';

    /**
     * The singleton instance
     */
    protected static ?self $instance = null;

    /**
     * Base path of the application
     */
    protected string $basePath;

    /**
     * Loaded service providers
     * 
     * @var array<class-string, object>
     */
    protected array $providers = [];

    /**
     * Application has been bootstrapped
     */
    protected bool $booted = false;

    /**
     * HTTP Kernel instance
     */
    protected ?Kernel $kernel = null;

    /**
     * Router instance
     */
    protected ?Router $router = null;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->registerBaseBindings();
        $this->registerBasePaths();
    }

    /**
     * Get the singleton instance
     */
    public static function getInstance(string $basePath = ''): self
    {
        if (is_null(self::$instance)) {
            if (empty($basePath)) {
                throw new \RuntimeException('Base path must be provided for initial application creation');
            }
            self::$instance = new self($basePath);
        }

        return self::$instance;
    }

    /**
     * Register base bindings in the container
     */
    protected function registerBaseBindings(): void
    {
        // Register app instance
        $this->instance('app', $this);
        $this->instance(self::class, $this);

        // Register exception handler
        $this->singleton(ExceptionHandler::class);
    }

    /**
     * Register base paths
     */
    protected function registerBasePaths(): void
    {
        $this->instance('path.base', $this->basePath);
        $this->instance('path.config', $this->basePath . '/config');
        $this->instance('path.storage', $this->basePath . '/storage');
        $this->instance('path.public', $this->basePath . '/public');
        $this->instance('path.routes', $this->basePath . '/routes');
    }

    /**
     * Load environment variables
     */
    public function loadEnvironment(): void
    {
        if (file_exists($this->basePath . '/.env')) {
            $dotenv = Dotenv::createImmutable($this->basePath);
            $dotenv->safeLoad();
        }
    }

    /**
     * Load configuration files
     */
    public function loadConfiguration(): void
    {
        Config::load($this->basePath . '/config');
        
        // Set default timezone
        date_default_timezone_set(Config::get('app.timezone', 'UTC'));
        
        // Set error reporting based on debug mode
        if (Config::get('app.debug', false)) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }
    }

    /**
     * Register service providers
     */
    public function registerProviders(): void
    {
        $providers = Config::get('app.providers', []);

        foreach ($providers as $provider) {
            $this->register($provider);
        }

        $this->boot();
    }

    /**
     * Register a service provider
     */
    public function register(string $provider): void
    {
        if (isset($this->providers[$provider])) {
            return;
        }

        $instance = new $provider($this);
        
        if (method_exists($instance, 'register')) {
            $instance->register();
        }

        $this->providers[$provider] = $instance;
    }

    /**
     * Boot the application
     */
    protected function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Boot service providers
        foreach ($this->providers as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot();
            }
        }

        $this->booted = true;
    }

    /**
     * Handle an incoming HTTP request
     */
    public function handle(Request $request): Response
    {
        try {
            // Get HTTP kernel
            $kernel = $this->getKernel();
            
            // Handle the request through kernel
            return $kernel->handle($request);
            
        } catch (\Throwable $e) {
            // Handle exceptions
            $handler = $this->resolve(ExceptionHandler::class);
            return $handler->handle($e, $request);
        }
    }

    /**
     * Terminate the application
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($this->kernel) {
            $this->kernel->terminate($request, $response);
        }

        // Run terminable service providers
        foreach ($this->providers as $provider) {
            if (method_exists($provider, 'terminate')) {
                $provider->terminate();
            }
        }
    }

    /**
     * Get the HTTP kernel instance
     */
    protected function getKernel(): Kernel
    {
        if (is_null($this->kernel)) {
            $this->kernel = $this->resolve(Kernel::class);
        }

        return $this->kernel;
    }

    /**
     * Get the router instance
     */
    public function router(): Router
    {
        if (is_null($this->router)) {
            $this->router = $this->resolve(Router::class);
            $this->singleton(Router::class, fn() => $this->router);
        }

        return $this->router;
    }

    /**
     * Get base path
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Check if application is in debug mode
     */
    public function isDebug(): bool
    {
        return (bool) Config::get('app.debug', false);
    }

    /**
     * Check if application is in production
     */
    public function isProduction(): bool
    {
        return Config::get('app.env') === 'production';
    }

    /**
     * Get application environment
     */
    public function environment(): string
    {
        return Config::get('app.env', 'production');
    }

    /**
     * Get framework version
     */
    public function version(): string
    {
        return self::VERSION;
    }

    /**
     * Prevent cloning (singleton pattern)
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization (singleton pattern)
     */
    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize singleton');
    }
}