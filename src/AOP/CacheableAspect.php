<?php

declare(strict_types=1);

namespace Zephyr\AOP;

use Closure;
use ReflectionMethod;
use Zephyr\Attributes\Cacheable;
use Zephyr\Cache\CacheManager;

/**
 * #[Cacheable] niteliğinin (attribute) mantığını işler.
 */
class CacheableAspect implements AspectInterface
{
    public function __construct(private CacheManager $cache)
    {
    }

    public function process(Closure $next, ReflectionMethod $method, array $args, object $attribute): mixed
    {
        if (!$attribute instanceof Cacheable) {
            return $next(); // Hata durumu, normal devam et
        }

        // 1. Önbellek anahtarı (key) oluştur
        $key = $attribute->key ?? $this->generateKey($method, $args);
        $ttl = $attribute->ttl;

        // 2. Önbelleği kontrol et
        if ($this->cache->has($key)) {
            // Varsa, metodu hiç çalıştırmadan önbellekten döndür
            return $this->cache->get($key);
        }

        // 3. Önbellekte yoksa, asıl metodu çalıştır (Pipeline'da bir sonraki adımı çağır)
        $result = $next();

        // 4. Sonucu önbelleğe kaydet
        $this->cache->set($key, $result, $ttl);

        // 5. Sonucu döndür
        return $result;
    }

    /**
     * Metot adı ve argümanlara göre otomatik bir önbellek anahtarı oluşturur.
     */
    private function generateKey(ReflectionMethod $method, array $args): string
    {
        $className = $method->getDeclaringClass()->getName();
        $methodName = $method->getName();
        // Argümanları serialize ederek anahtarın parçası yap
        $argsKey = md5(serialize($args));

        return "aop:{$className}:{$methodName}:{$argsKey}";
    }
}