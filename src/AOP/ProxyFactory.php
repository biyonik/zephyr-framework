<?php

declare(strict_types=1);

namespace Zephyr\AOP;

use Zephyr\Core\App;
use ReflectionClass;

/**
 * Proxy (Vekil) Fabrikası
 *
 * AOP (Aspect) uygulanması gereken sınıflar için
 * dinamik olarak Proxy (Vekil) sınıfları oluşturur.
 */
class ProxyFactory
{
    public function __construct(
        private App $app,
        private AspectManager $aspectManager
    ) {
    }

    /**
     * Bir nesneyi (instance) alır, AOP Niteliklerine (Attributes) sahipse
     * onu bir Proxy (Vekil) ile sarmalar.
     *
     * @param object $instance Asıl (orijinal) nesne
     * @param ReflectionClass $classReflection Asıl nesnenin yansıması
     * @return object Orijinal nesne veya Proxy'lenmiş nesne
     */
    public function createProxy(object $instance, ReflectionClass $classReflection): object
    {
        // 1. Bu sınıf için hangi metotların sarmalanacağını (wrap) bul
        $methodsToProxy = [];
        foreach ($classReflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isDestructor() || $method->isStatic()) {
                continue;
            }

            // Bu metot için tanımlanmış Aspect'leri (işleyicileri) al
            $aspects = $this->aspectManager->getAspectsForMethod($method);

            if (!empty($aspects)) {
                $methodsToProxy[$method->getName()] = $aspects;
            }
        }

        // Eğer sarmalanacak hiçbir metot yoksa, orijinal nesneyi döndür
        if (empty($methodsToProxy)) {
            return $instance;
        }

        // 2. Orijinal sınıfı miras alan (extends) bir anonim sınıf (Proxy) oluştur
        return new class($instance, $methodsToProxy) extends $instance {
            private object $originalInstance;
            private array $proxiedMethods;

            // Proxy'nin constructor'ı asıl (orijinal) nesneyi alır
            public function __construct(object $originalInstance, array $proxiedMethods)
            {
                $this->originalInstance = $originalInstance;
                $this->proxiedMethods = $proxiedMethods;
            }

            /**
             * Tüm metot çağrılarını yakalayan sihirli (magic) metot.
             *
             * Eğer çağrılan metot ($name) bizim $proxiedMethods listemizdeyse,
             * onu Aspect Pipeline (işlem hattı) üzerinden çalıştırırız.
             * Değilse, doğrudan orijinal nesnenin metodunu çağırırız.
             */
            public function __call(string $name, array $arguments): mixed
            {
                if (!isset($this->proxiedMethods[$name])) {
                    // Bu metot proxy'lenmiyor, doğrudan orijinali çağır
                    return $this->originalInstance->$name(...$arguments);
                }

                // Bu metot proxy'leniyor. Aspect Pipeline'ını kur.

                // 1. Asıl (orijinal) metodu çağıran son katman
                $coreLogic = function () use ($name, $arguments) {
                    return $this->originalInstance->$name(...$arguments);
                };

                // 2. Aspect'leri (Cacheable, Loggable vb.) al
                $aspects = $this->proxiedMethods[$name];
                $methodReflection = new \ReflectionMethod($this->originalInstance, $name);

                // 3. Aspect'leri (dıştan içe doğru) bir boru hattı (Pipeline) gibi kur
                $pipeline = array_reduce(
                    $aspects, // DİKKAT: AspectManager bunları zaten tersine çevirdi
                    function ($next, $aspectInfo) use ($methodReflection, $arguments) {
                        return function () use ($next, $aspectInfo, $methodReflection, $arguments) {
                            /** @var AspectInterface $aspect */
                            $aspect = $aspectInfo['aspect'];
                            $attribute = $aspectInfo['attribute'];

                            // Aspect'in process() metodunu çağır
                            return $aspect->process($next, $methodReflection, $arguments, $attribute);
                        };
                    },
                    $coreLogic // Pipeline'in en içindeki çekirdek
                );

                // 4. Kurulan pipeline'ı (bor hattını) çalıştır
                return $pipeline();
            }
        };
    }
}