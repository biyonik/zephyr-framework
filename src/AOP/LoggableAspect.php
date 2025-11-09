<?php

declare(strict_types=1);

namespace Zephyr\AOP;

use Closure;
use ReflectionMethod;
use Zephyr\Attributes\Loggable;
use Zephyr\Logging\LogManager;

/**
 * #[Loggable] niteliğinin (attribute) mantığını işler.
 */
class LoggableAspect implements AspectInterface
{
    public function __construct(private LogManager $log)
    {
    }

    public function process(Closure $next, ReflectionMethod $method, array $args, object $attribute): mixed
    {
        if (!$attribute instanceof Loggable) {
            return $next(); // Hata durumu
        }

        $level = $attribute->level;
        $methodName = $method->getDeclaringClass()->getName() . '::' . $method->getName();
        $message = $attribute->message ?? "Metot çağrılıyor: {$methodName}";

        // 1. Metot başlamadan logla
        $this->log->channel('daily')->{$level}($message . ' [START]', ['args' => $args]);
        $start = microtime(true);

        try {
            // 2. Asıl metodu (veya bir sonraki Aspect'i) çalıştır
            $result = $next();

            $duration = round((microtime(true) - $start) * 1000, 2);

            // 3. Başarıyla biterse logla
            $this->log->channel('daily')->{$level}($message . " [END] ({$duration}ms)");

            return $result;

        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $start) * 1000, 2);

            // 4. Hata verirse logla
            $this->log->channel('daily')->error($message . " [FAIL] ({$duration}ms)", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Hatayı tekrar fırlat
            throw $e;
        }
    }
}