<?php

declare(strict_types=1);

namespace Zephyr\AOP;

use Closure;
use ReflectionMethod;
use Zephyr\Attributes\Transactional;
use Zephyr\Database\Connection;

/**
 * #[Transactional] niteliğinin (attribute) mantığını işler.
 */
class TransactionalAspect implements AspectInterface
{
    public function __construct(private Connection $db)
    {
    }

    public function process(Closure $next, ReflectionMethod $method, array $args, object $attribute): mixed
    {
        if (!$attribute instanceof Transactional) {
            return $next(); // Hata
        }

        try {
            // 1. Transaction'ı başlat
            $this->db->beginTransaction();

            // 2. Asıl metodu (veya bir sonraki Aspect'i) çalıştır
            $result = $next();

            // 3. Başarılıysa COMMIT
            $this->db->commit();

            return $result;

        } catch (\Throwable $e) {
            // 4. Hata olursa ROLLBACK
            $this->db->rollback();

            // Hatayı tekrar fırlat
            throw $e;
        }
    }
}