<?php

declare(strict_types=1);

namespace Zephyr\AOP;

use Closure;
use ReflectionMethod;

/**
 * Aspect Arayüzü
 *
 * Bir Nitelik (Attribute) için çalıştırılacak mantığı tanımlar.
 */
interface AspectInterface
{
    /**
     * Nitelik (Attribute) ile işaretlenmiş metodu işle.
     *
     * @param Closure $next Bir sonraki Aspect'i veya asıl metodu çağıran fonksiyon
     * @param ReflectionMethod $method Üzerinde Nitelik bulunan metot
     * @param array $args Metoda geçirilen argümanlar
     * @param object $attribute Nitelik (Attribute) nesnesinin kendisi (örn: Cacheable)
     * @return mixed
     */
    public function process(Closure $next, ReflectionMethod $method, array $args, object $attribute): mixed;
}