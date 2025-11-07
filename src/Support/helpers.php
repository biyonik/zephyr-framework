<?php

declare(strict_types=1);

/**
 * Global Helper Functions
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

use Zephyr\Auth\AuthManager;
use Zephyr\Core\App;
use Zephyr\Database\Connection;
use Zephyr\Database\QueryBuilder;
use Zephyr\Http\Response;
use Zephyr\Logging\LogManager;
use Zephyr\Support\Collection;
use Zephyr\Support\Config;
use Zephyr\Support\Env;
use Zephyr\Http\Request;

if (!function_exists('app')) {
    function app(?string $abstract = null): mixed
    {
        if (is_null($abstract)) {
            return App::getInstance();
        }

        return App::getInstance()->resolve($abstract);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $basePath = app()->basePath();
        
        return $path ? $basePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\') : $basePath;
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('response')) {
    function response(mixed $content = '', int $status = 200, array $headers = []): Response
    {
        return new Response($content, $status, $headers);
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$vars): never
    {
        foreach ($vars as $var) {
            dump($var);
        }
        
        die(1);
    }
}

if (!function_exists('dump')) {
    function dump(mixed ...$vars): void
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
    }
}

if (!function_exists('value')) {
    function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('now')) {
    function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(config('app.timezone', 'UTC')));
    }
}

if (!function_exists('today')) {
    function today(): DateTime
    {
        return (new DateTime('now', new DateTimeZone(config('app.timezone', 'UTC'))))->setTime(0, 0);
    }
}

if (!function_exists('ip_address')) {
    function ip_address(): string
    {
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            return app(Request::class)->ip();
        }
        
        return '127.0.0.1';
    }
}

if (!function_exists('db')) {
    function db(?string $table = null): QueryBuilder|Connection
    {
        $connection = Connection::getInstance();

        if (is_null($table)) {
            return new QueryBuilder($connection);
        }

        return (new QueryBuilder($connection))->from($table);
    }
}

if (!function_exists('class_basename')) {
    function class_basename(string|object $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('collect')) {
    function collect(mixed $value = []): Collection
    {
        return new Collection($value);
    }
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle !== '' && substr($haystack, -strlen($needle)) === $needle;
    }
}

if (!function_exists('array_get')) {
    function array_get(array $array, string $key, mixed $default = null): mixed
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return value($default);
            }

            $array = $array[$segment];
        }

        return $array;
    }
}

if (!function_exists('array_set')) {
    function array_set(array &$array, string $key, mixed $value): array
    {
        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }
}

if (!function_exists('retry')) {
    function retry(int $times, callable $callback, int $sleep = 0)
    {
        $attempts = 0;

        beginning:
        $attempts++;

        try {
            return $callback($attempts);
        } catch (Throwable $e) {
            if ($attempts >= $times) {
                throw $e;
            }

            if ($sleep > 0) {
                usleep($sleep * 1000);
            }

            goto beginning;
        }
    }
}

if (!function_exists('tap')) {
    function tap(mixed $value, ?callable $callback = null): mixed
    {
        if (is_null($callback)) {
            return $value;
        }

        $callback($value);

        return $value;
    }
}

if (!function_exists('with')) {
    function with(mixed $value, ?callable $callback = null): mixed
    {
        return is_null($callback) ? $value : $callback($value);
    }
}

if (!function_exists('rescue')) {
    function rescue(callable $callback, mixed $rescue = null): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            return value($rescue);
        }
    }
}

if (!function_exists('auth')) {
    /**
     * AuthManager'a veya giriş yapmış kullanıcıya hızlı erişim sağlar.
     */
    function auth(): AuthManager
    {
        return app('auth');
    }
}

if (!function_exists('log')) {
    /**
     * Gelişmiş loglama sistemine bir kayıt gönderir.
     *
     * Kullanım:
     * log("Bir bilgi mesajı"); // Varsayılan (info) seviyede loglar
     * log()->error("Bir hata oluştu", ['exception' => $e]); // Hata seviyesinde loglar
     * log()->channel('security')->warning("Güvenlik uyarısı"); // Farklı kanala loglar
     *
     * @param string|null $message Log mesajı (null ise LogManager'ı döndürür)
     * @param array $context Ekstra veri
     * @return LogManager|void
     */
    function log(string $message = null, array $context = [])
    {
        $logger = app('log');

        if (is_null($message)) {
            return $logger;
        }

        $logger->info($message, $context);
    }
}

if (!function_exists('event')) {
    /**
     * Yeni bir olayı (event) tetikler ve dinleyicilerine dağıtır.
     *
     * @param object $event Tetiklenecek olay nesnesi
     * @return void
     */
    function event(object $event): void
    {
        app('event')->dispatch($event);
    }
}