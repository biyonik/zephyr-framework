<?php

declare(strict_types=1);

namespace Zephyr\Logging;

use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Formatter\LineFormatter;
use Zephyr\Core\App;
use Zephyr\Logging\Processors\RequestProcessor;

/**
 * Gelişmiş Log Yöneticisi (PSR-3 Uyumlu)
 *
 * config/logging.php dosyasını okur, Monolog sürücülerini (kanallarını)
 * yönetir ve varsayılan loglama arayüzünü sağlar.
 *
 * @mixin Logger
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class LogManager implements LoggerInterface
{
    protected App $app;
    protected array $config;

    /**
     * Oluşturulan ve önbelleğe alınan kanallar (Logger nesneleri).
     * @var array<string, LoggerInterface>
     */
    protected array $channels = [];

    /**
     * Varsayılan kanalın adı (örn: 'daily').
     */
    protected string $defaultChannel;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->config = $this->app->resolve('config')->get('logging');
        $this->defaultChannel = $this->config['default'] ?? 'single';
    }

    /**
     * Belirtilen kanalı döndürür veya oluşturur.
     *
     * @param string|null $name Kanal adı (null ise varsayılan)
     * @return LoggerInterface
     * @throws \Exception
     */
    public function channel(string $name = null): LoggerInterface
    {
        $name = $name ?? $this->defaultChannel;

        // 1. Önbellekten kontrol et
        if (isset($this->channels[$name])) {
            return $this->channels[$name];
        }

        // 2. Konfigürasyonu al
        $config = $this->config['channels'][$name] ?? null;
        if (is_null($config)) {
            throw new \InvalidArgumentException("Log kanalı [{$name}] bulunamadı.");
        }

        // 3. Sürücüyü (Driver) oluştur
        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';
        if (!method_exists($this, $driverMethod)) {
            throw new \InvalidArgumentException("Log sürücüsü [{$config['driver']}] desteklenmiyor.");
        }

        // 4. Logger'ı oluştur ve önbelleğe al
        $this->channels[$name] = $this->$driverMethod($name, $config);
        return $this->channels[$name];
    }

    /**
     * Varsayılan kanalı (veya 'stack'i) döndürür.
     */
    protected function getDefaultDriver(): LoggerInterface
    {
        return $this->channel($this->defaultChannel);
    }

    /**
     * 'single' (tek dosya) sürücüsünü oluşturur.
     */
    protected function createSingleDriver(string $name, array $config): Logger
    {
        $handler = new StreamHandler(
            $config['path'],
            $this->level($config),
            true,
            $config['permission'] ?? null,
            $config['locking'] ?? false
        );

        $handler->setFormatter($this->getDefaultFormatter());

        $logger =  new Logger($name, [$handler]);

        $this->pushGlobalProcessors($logger);

        return $logger;
    }

    /**
     * 'daily' (günlük dosya) sürücüsünü oluşturur.
     */
    protected function createDailyDriver(string $name, array $config): Logger
    {
        $handler = new RotatingFileHandler(
            $config['path'],
            $config['days'] ?? 14,
            $this->level($config),
            true,
            $config['permission'] ?? null,
            $config['locking'] ?? false
        );

        $handler->setFormatter($this->getDefaultFormatter());


        $logger =  new Logger($name, [$handler]);

        $this->pushGlobalProcessors($logger);

        return $logger;
    }

    /**
     * 'stderr' sürücüsünü oluşturur (CLI ve Docker için).
     */
    protected function createStderrDriver(string $name, array $config): Logger
    {
        $handler = new StreamHandler(
            'php://stderr',
            $this->level($config)
        );

        $handler->setFormatter($this->getDefaultFormatter());

        $logger =  new Logger($name, [$handler]);

        return $logger;
    }

    /**
     * 'stack' (yığın) sürücüsünü oluşturur.
     * Bu sürücü, logları birden fazla alt kanala yönlendirir.
     */
    protected function createStackDriver(string $name, array $config): Logger
    {
        $handlers = [];
        $channels = $config['channels'] ?? [];

        foreach ($channels as $channelName) {
            // Stack'in parçası olan kanalları 'channel' metoduyla çöz
            // (Bu, onları da önbelleğe alır)
            $handlers = array_merge($handlers, $this->channel($channelName)->getHandlers());
        }

        $logger =  new Logger($name, $handlers);

        $this->pushGlobalProcessors($logger);

        return $logger;
    }

    /**
     * Varsayılan log formatlayıcısını (formatter) alır.
     */
    protected function getDefaultFormatter(): LineFormatter
    {
        // [Tarih] kanal.SEVİYE: Mesaj {context}
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s', // Tarih formatı
            true, // Boş satırlara izin ver
            true  // Satır sonlarını escape et (güvenlik)
        );

        // JSON'ın güzel görünmesini sağla
        $formatter->includeStacktraces(true);

        return $formatter;
    }

    protected function pushGlobalProcessors(Logger $logger): void
    {
        // RequestProcessor'ı LogServiceProvider'da kaydetmiştik
        $logger->pushProcessor($this->app->resolve(RequestProcessor::class));
    }

    /**
     * Konfigürasyondaki 'level' string'ini Monolog'un seviyesine çevirir.
     */
    protected function level(array $config): Level
    {
        $level = $config['level'] ?? 'debug';
        return Logger::toMonologLevel($level);
    }

    /*
    |--------------------------------------------------------------------------
    | PSR-3 LoggerInterface Metotları
    |--------------------------------------------------------------------------
    |
    | Bu metotlar, LogManager'ın doğrudan LoggerInterface olarak
    | kullanılabilmesini sağlar (DI için).
    | Tüm çağrıları varsayılan sürücüye yönlendirir.
    |
    */

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->getDefaultDriver()->emergency($message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->getDefaultDriver()->alert($message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->getDefaultDriver()->critical($message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->getDefaultDriver()->error($message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->getDefaultDriver()->warning($message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->getDefaultDriver()->notice($message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->getDefaultDriver()->info($message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->getDefaultDriver()->debug($message, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->getDefaultDriver()->log($level, $message, $context);
    }

    /**
     * Çağrıları (örn: Log::info()) varsayılan sürücüye (Monolog\Logger) yönlendirir.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->getDefaultDriver()->{$method}(...$parameters);
    }
}