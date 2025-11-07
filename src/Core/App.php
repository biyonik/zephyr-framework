<?php

declare(strict_types=1);

namespace Zephyr\Core;

use Dotenv\Dotenv;
use Zephyr\Core\Container\Container;
use Zephyr\Http\{Request, Response};
use Zephyr\Http\Kernel;
use Zephyr\Support\{Config, Env, Maintenance};
use Zephyr\Cache\{CacheInterface, FileCache};
use Zephyr\Exceptions\Handler as ExceptionHandler;
use Zephyr\Exceptions\Container\CircularDependencyException;
use Zephyr\Exceptions\Container\BindingResolutionException;

/**
 * Application Core Class
 *
 * The heart of the Zephyr Framework. Manages the application lifecycle,
 * service container, configuration, and request handling.
 *
 * @author Ahmet ALTUN
 * @email ahmet.altun60@gmail.com
 * @github https://github.com/biyonik
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
     * Compiled container cache
     * @var array<string, \Closure>
     */
    protected array $compiledMap = [];

    /**
     * Cache loaded status
     */
    protected bool $isCompiled = false;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->registerBaseBindings();
        $this->registerBasePaths();
        $this->loadCompiledContainer();
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
     * Load compiled container cache
     */
    protected function loadCompiledContainer(): void
    {
        $cacheFile = $this->basePath . '/storage/framework/cache/container.php';

        if (file_exists($cacheFile)) {
            $this->compiledMap = require $cacheFile;
            $this->isCompiled = true;
        }
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

        // Register cache (file-based by default)
        $this->singleton(CacheInterface::class, function() {
            return new FileCache($this->basePath . '/storage/framework/cache');
        });
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

        // Make base path available to Config
        Config::set('path.base', $this->basePath);
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
        // YENİ: Önbellek dosyasını kontrol et
        $cacheFile = $this->basePath . '/storage/framework/cache/config.php';

        if (file_exists($cacheFile)) {
            // 1. Önbellekten Yükle (Hızlı)
            $config = require $cacheFile;
            Config::setAll($config); // Config::setAll() metodunu ekleyeceğiz
            $this->instance('config_cached', true);
        } else {
            // 2. Dosyalardan Yükle (Yavaş)
            Config::load($this->basePath . '/config');
            $this->instance('config_cached', false);
        }
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
     * Resolve a service from the container
     *
     * @param string $abstract Class or interface name to resolve
     * @return mixed Resolved instance
     *
     * @throws BindingResolutionException If resolution fails
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

        // Check compiled cache
        if ($this->isCompiled && isset($this->compiledMap[$abstract])) {
            $this->resolving[] = $abstract;

            try {
                $instance = $this->compiledMap[$abstract]($this);

                // Validate type from cache
                if (!$instance instanceof $abstract) {
                    throw new BindingResolutionException(
                        "Compiled container cache returned invalid type for [{$abstract}]"
                    );
                }

                if ($this->isShared($abstract)) {
                    $this->instances[$abstract] = $instance;
                }
            } finally {
                array_pop($this->resolving);
            }

            return $instance;
        }

        // Normal resolution via Container trait
        $this->resolving[] = $abstract;

        try {
            $concrete = $this->getConcrete($abstract);
            $instance = $this->build($concrete);

            if ($this->isShared($abstract)) {
                $this->instances[$abstract] = $instance;
            }

            return $instance;
        } finally {
            array_pop($this->resolving);
        }
    }

    /**
     * Handle an incoming HTTP request
     */
    public function handle(Request $request): Response
    {
        try {
            if (!$this->has(Request::class)) {
                $this->instance(Request::class, $request);
            }

            return $this->getKernel()->handle($request);
        } catch (\Throwable $e) {
            return $this->resolve(ExceptionHandler::class)->handle($e, $request);
        }
    }

    /**
     * Get the HTTP kernel instance
     */
    public function getKernel(): Kernel
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
     * Terminate application
     *
     * Called after response has been sent to client.
     * Performs cleanup tasks without blocking user request.
     *
     * @param Request $request
     * @param Response $response
     */
    public function terminate(Request $request, Response $response): void
    {
        // 1. Terminate all service providers
        foreach ($this->providers as $provider) {
            if (method_exists($provider, 'terminate')) {
                $provider->terminate($request, $response);
            }
        }

        // 2. Run maintenance tasks (if needed)
        $this->runMaintenanceTasks();
    }

    /**
     * Run maintenance tasks probabilistically
     *
     * Uses lottery system to determine if cleanup should run.
     * Also checks for forced cleanup (max age exceeded).
     */
    private function runMaintenanceTasks(): void
    {
        try {
            $config = Config::get('maintenance');

            if (!$config) {
                // Maintenance not configured, skip
                return;
            }

            // Check if forced cleanup is needed (24+ hours without cleanup)
            if (Maintenance::needsForcedCleanup()) {
                Maintenance::log("Forced cleanup triggered (max age exceeded)");
                $this->executeMaintenanceTasks($config['tasks']);
                Maintenance::recordCleanup();
                return;
            }

            // Check minimum interval (don't run too frequently)
            if (!Maintenance::canRunCleanup()) {
                return;
            }

            // Lottery check (probabilistic)
            if (Maintenance::shouldRun()) {
                Maintenance::log("Probabilistic cleanup triggered");
                $this->executeMaintenanceTasks($config['tasks']);
                Maintenance::recordCleanup();
            }

        } catch (\Throwable $e) {
            // Never let maintenance tasks crash the application
            error_log("Maintenance task error: " . $e->getMessage());
        }
    }

    /**
     * Execute maintenance tasks
     *
     * @param array $tasks Array of callables
     */
    private function executeMaintenanceTasks(array $tasks): void
    {
        foreach ($tasks as $task) {
            try {
                if (is_callable($task)) {
                    $result = call_user_func($task);

                    // Log result if task returns statistics
                    if (is_array($result)) {
                        Maintenance::log("Task completed", [
                            'task' => $this->getTaskName($task),
                            'result' => $result
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                // Log but continue with other tasks
                error_log("Task failed: {$this->getTaskName($task)} - {$e->getMessage()}");
            }
        }
    }

    /**
     * Get human-readable task name for logging
     */
    private function getTaskName(mixed $task): string
    {
        if (is_array($task)) {
            return $task[0] . '::' . $task[1];
        }

        if (is_object($task)) {
            return get_class($task);
        }

        return 'unknown';
    }

    /**
     * Prevent cloning (singleton pattern)
     */
    private function __clone() {}

    /**
     * Prevent unserialization (singleton pattern)
     */
    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize singleton');
    }
}