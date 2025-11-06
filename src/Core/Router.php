<?php

declare(strict_types=1);

namespace Zephyr\Core;

use Closure;
use Zephyr\Http\{Request, Response};
use Zephyr\Exceptions\Http\{NotFoundException, MethodNotAllowedException};
use Zephyr\Http\Kernel;

/**
 * HTTP Router
 * * Handles route registration, pattern matching, and request dispatching.
 * Supports dynamic parameters, middleware, and route groups.
 * * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Router
{
    /**
     * Registered routes grouped by method
     * * @var array<string, array<Route>>
     */
    protected array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
        'HEAD' => [],
        'OPTIONS' => [],
    ];

    /**
     * Current route group attributes
     */
    protected array $groupStack = [];

    /**
     * Route name mappings
     * * @var array<string, Route>
     */
    protected array $namedRoutes = [];

    /**
     * YENİ: Hangi rotaların zaten eklendiğini
     * hızlıca kontrol etmek için bir arama tablosu (lookup table).
     * @var array<string, bool>
     */
    protected array $routeLookup = [];

    /**
     * YENİ: Application container
     */
    protected App $app;

    /**
     * Constructor güncellendi
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Register a GET route
     */
    public function get(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }

    /**
     * Register a POST route
     */
    public function post(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    /**
     * Register a PUT route
     */
    public function put(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    /**
     * Register a PATCH route
     */
    public function patch(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    /**
     * Register an OPTIONS route
     */
    public function options(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute(['OPTIONS'], $uri, $action);
    }

    /**
     * Register a route for multiple methods
     */
    public function match(array $methods, string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute(array_map('strtoupper', $methods), $uri, $action);
    }

    /**
     * Register a route for all methods
     */
    public function any(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute(array_keys($this->routes), $uri, $action);
    }

    /**
     * Register a route group
     */
    public function group(array $attributes, Closure $routes): void
    {
        $this->groupStack[] = $attributes;

        $routes($this);

        array_pop($this->groupStack);
    }

    /**
     * Add a route to the collection
     * *** GÜNCELLENDİ (Rapor #1: Route Caching Logic Error) ***
     */
    protected function addRoute(array $methods, string $uri, Closure|array|string $action): Route
    {
        // Apply group prefix if exists
        $uri = $this->applyGroupPrefix($uri); //

        // Create route instance
        $route = new Route($methods, $uri, $action); //

        // Apply group middleware
        if ($middleware = $this->getGroupMiddleware()) {
            $route->middleware($middleware);
        }

        // Apply group namespace
        if ($namespace = $this->getGroupNamespace()) {
            $route->namespace($namespace);
        }

        // YENİ: Rota tipini belirle (Controller, Closure, vb.)
        $actionType = match (true) {
            is_array($action) => 'controller', // [Controller::class, 'method']
            $action instanceof Closure => 'closure',
            is_string($action) => 'controller_string', // 'Controller@method'
            default => 'unknown'
        };

        // Register route for each method
        foreach ($methods as $method) {

            // *** YENİ GÜVENLİ ANAHTAR (Rapor #1 Çözümü) ***
            $lookupKey = $method . '::' . $uri . '::' . $actionType;

            if (isset($this->routeLookup[$lookupKey])) {
                // Bu rota (muhtemelen önbellekten veya
                // api.php'de çift kayıttan) zaten eklendi.
                continue;
            }
            // *** YENİ KONTROL SONU ***

            $this->routes[$method][] = $route;
            $this->routeLookup[$lookupKey] = true; // Rotayı eklendi olarak işaretle
        }

        return $route;
    }

    /**
     * Apply group prefix to URI
     */
    protected function applyGroupPrefix(string $uri): string
    {
        $prefix = $this->getGroupAttribute('prefix'); //

        if ($prefix) { //
            $uri = rtrim($prefix, '/') . '/' . ltrim($uri, '/'); //
        }

        return $uri; //
    }

    /**
     * Get group middleware
     */
    protected function getGroupMiddleware(): array
    {
        return $this->getGroupAttribute('middleware', []); //
    }

    /**
     * Get group namespace
     */
    protected function getGroupNamespace(): ?string
    {
        return $this->getGroupAttribute('namespace'); //
    }

    /**
     * Get group attribute
     */
    protected function getGroupAttribute(string $key, mixed $default = null): mixed
    {
        if (empty($this->groupStack)) { //
            return $default; //
        }

        $groups = array_reverse($this->groupStack); //

        foreach ($groups as $group) { //
            if (isset($group[$key])) { //
                return $group[$key]; //
            }
        }

        return $default; //
    }

    /**
     * YENİ: Önbelleğe alınmış rotaları doğrudan ayarlar.
     * *** GÜNCELLENDİ (Rapor #1: Route Caching Logic Error) ***
     */
    public function setCachedRoutes(array $routes): void
    {
        $this->routes = $routes;

        // Arama tablosunu (lookup table) yeniden oluştur
        $this->routeLookup = [];
        foreach ($this->routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {

                // Önbelleğe SADECE Controller (array) rotalarını aldığımızı biliyoruz.
                //
                $action = $route->getAction(); //

                // Eylem tipini belirle (cache'ten gelenler her zaman array olmalı)
                $actionType = is_array($action) ? 'controller' : 'unknown_cached_type';

                foreach ($route->getMethods() as $routeMethod) { //

                    // *** YENİ GÜVENLİ ANAHTAR (Rapor #1 Çözümü) ***
                    $lookupKey = $routeMethod . '::' . $route->getUri() . '::' . $actionType;

                    $this->routeLookup[$lookupKey] = true; //
                }
            }
        }
    }

    /**
     * YENİ: Verilen bir rota dosyasını yükler.
     * Bu metot, OptimizeRouteCommand ve public/index.php tarafından kullanılır.
     */
    public function loadRoutesFile(string $filePath): void
    {
        // Rota dosyasının $router değişkenine erişebilmesini sağla
        $router = $this;
        require $filePath;
    }

    /**
     * Dispatch metodu "Nested Pipeline" için yeniden yazıldı.
     *
     * @throws NotFoundException
     * @throws MethodNotAllowedException
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $uri = $request->uri();

        // 1. Eşleşen rotayı bul
        $route = $this->findRoute($method, $uri);

        // 2. 404 veya 405 (Metot Yasak) hatası ver (Mevcut kod)
        if (!$route) {
            if ($this->hasRouteForOtherMethods($uri, $method)) {
                throw new MethodNotAllowedException(
                    "Method {$method} not allowed for {$uri}"
                );
            }
            throw new NotFoundException("Route not found: {$uri}");
        }

        // 3. Rota parametrelerini ayıkla ve Request'e ekle
        $parameters = $route->extractParameters($uri);
        $request->setRouteParams($parameters);

        // 4. --- YENİ MİMARİ: İÇ İÇE PİPELİNE ---

        // 4a. Bu rotaya özel middleware'leri al
        $routeMiddleware = $this->gatherRouteMiddleware($route);

        // 4b. Yeni, iç içe bir Pipeline oluştur
        $response = (new Pipeline($this->app))
            ->send($request)
            ->through($routeMiddleware) // <-- SADECE rotaya özel middleware'ler
            ->then(function (Request $request) use ($route, $parameters) {
                // 4c. Bu pipeline'ın HEDEFİ, Controller'ı çalıştırmaktır.
                return $route->execute($request, $parameters);
            });

        // --- DEĞİŞİKLİK SONU ---

        // (Eski $route->execute() çağrısı kaldırıldı, pipeline'ın 'then' bloğuna taşındı)

        // 5. Yanıtı hazırla
        $response->setRequest($request);
        return $response;
    }

    /**
     * YENİ METOT: Rota middleware'lerini toplar ve alias'ları çözer.
     *
     * @param Route $route
     * @return array
     * @throws \Exception
     */
    protected function gatherRouteMiddleware(Route $route): array
    {
        $middlewareNames = $route->getMiddleware(); // örn: ['auth']

        if (empty($middlewareNames)) {
            return [];
        }

        // Kernel'dan alias (takma ad) listesini al
        // Kernel'in container'a bağlı olduğunu biliyoruz (Adım 1'de sağladık)
        $kernel = $this->app->resolve(Kernel::class);
        $aliases = $kernel->getRouteMiddlewareAliases(); // Kernel'a bu metodu ekleyeceğiz

        $middlewareClasses = [];
        foreach ($middlewareNames as $name) {
            if (isset($aliases[$name])) {
                // Alias'ı çöz: 'auth' -> \Zephyr\Http\Middleware\AuthMiddleware::class
                $middlewareClasses[] = $aliases[$name];
            } elseif (class_exists($name)) {
                // Doğrudan sınıf adı verilmiş olabilir
                $middlewareClasses[] = $name;
            } else {
                // Bulunamadıysa hata fırlat
                throw new \Exception("Middleware [{$name}] not defined in Kernel.");
            }
        }

        return $middlewareClasses;
    }

    /**
     * Find matching route
     */
    protected function findRoute(string $method, string $uri): ?Route
    {
        $routes = $this->routes[$method] ?? []; //

        foreach ($routes as $route) { //
            if ($route->matches($uri)) { //
                return $route; //
            }
        }

        return null; //
    }

    /**
     * Check if route exists for other methods
     */
    protected function hasRouteForOtherMethods(string $uri, string $excludeMethod): bool
    {
        foreach ($this->routes as $method => $routes) { //
            if ($method === $excludeMethod) { //
                continue; //
            }

            foreach ($routes as $route) { //
                if ($route->matches($uri)) { //
                    return true; //
                }
            }
        }

        return false; //
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes; //
    }

    /**
     * Get route by name
     */
    public function getByName(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null; //
    }

    /**
     * Register a named route
     */
    public function name(string $name, Route $route): void
    {
        $this->namedRoutes[$name] = $route; //
    }

    /**
     * Generate URL for named route
     */
    public function url(string $name, array $parameters = []): string
    {
        $route = $this->getByName($name); //

        if (!$route) { //
            throw new \InvalidArgumentException("Route [{$name}] not defined."); //
        }

        return $route->url($parameters); //
    }
}
