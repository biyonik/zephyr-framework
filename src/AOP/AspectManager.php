<?php

declare(strict_types=1);

namespace Zephyr\AOP;

use Zephyr\Core\App;
use Zephyr\Attributes\Cacheable;
use Zephyr\Attributes\Loggable;
use Zephyr\Attributes\Transactional;
use ReflectionClass;
use ReflectionMethod;

/**
 * Aspect Yöneticisi
 *
 * Nitelikleri (Attributes) ilgili Aspect sınıflarına (mantığı işleyen) eşler.
 * ProxyFactory tarafından kullanılır.
 */
class AspectManager
{
    /**
     * Hangi Nitelik (Attribute) için hangi Aspect (İşleyici) sınıfının
     * çalıştırılacağını tanımlayan eşleme.
     *
     * @var array<class-string, class-string>
     */
    protected array $map = [
        Cacheable::class => CacheableAspect::class,
        Loggable::class => LoggableAspect::class,
        Transactional::class => TransactionalAspect::class,
    ];

    /**
     * Çözümlenmiş Aspect nesneleri için önbellek.
     * @var array<class-string, AspectInterface>
     */
    private array $resolvedAspects = [];

    public function __construct(private App $app)
    {
    }

    /**
     * Bir sınıf ve metot için geçerli olan tüm Aspect'leri
     * ve Nitelikleri (Attributes) bulur.
     *
     * @return array [AspectInterface, AttributeObject][]
     */
    public function getAspectsForMethod(ReflectionMethod $method): array
    {
        $aspects = [];
        $attributes = $method->getAttributes();

        foreach ($attributes as $attribute) {
            $attributeName = $attribute->getName();

            // Eğer bu Nitelik (Attribute) bizim haritamızda (map) kayıtlıysa
            if (isset($this->map[$attributeName])) {
                $aspectClass = $this->map[$attributeName];

                // Aspect sınıfını (işleyiciyi) DI Konteynerinden çöz
                $aspectInstance = $this->resolveAspect($aspectClass);

                $aspects[] = [
                    'aspect' => $aspectInstance,
                    'attribute' => $attribute->newInstance(),
                ];
            }
        }

        // ÖNEMLİ: Aspect'leri tersine çevir.
        // Bu sayede ilk Nitelik (Attribute) en dış katman (wrapper) olur.
        // #[Cacheable] -> Cache (Dış Katman)
        // #[Loggable]  -> Log (İç Katman)
        // public function ... -> Asıl Metot
        return array_reverse($aspects);
    }

    /**
     * Bir sınıfın (veya metotlarının) proxy'lenmeye (AOP) ihtiyacı olup olmadığını kontrol eder.
     * Bu, her 'resolve' işleminde ağır Reflection'ı önlemek için hızlı bir kontroldür.
     */
    public function needsProxy(ReflectionClass $class): bool
    {
        // Önce sınıfın kendisinde kayıtlı bir nitelik var mı? (Şu an desteklemiyoruz)

        // Sonra public metotlarda kayıtlı bir nitelik var mı?
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isDestructor()) {
                continue;
            }

            $attributes = $method->getAttributes();
            foreach ($attributes as $attribute) {
                if (isset($this->map[$attribute->getName()])) {
                    // Eşleşen bir Aspect bulduk, bu sınıf Proxy'lenmeli.
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Aspect (İşleyici) sınıfını DI Konteynerinden çözer ve önbelleğe alır.
     */
    private function resolveAspect(string $aspectClass): AspectInterface
    {
        if (!isset($this->resolvedAspects[$aspectClass])) {
            $this->resolvedAspects[$aspectClass] = $this->app->resolve($aspectClass);
        }
        return $this->resolvedAspects[$aspectClass];
    }
}