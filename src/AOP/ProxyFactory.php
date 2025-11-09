<?php

declare(strict_types=1);

namespace Zephyr\AOP;

use Zephyr\Core\App;
use ReflectionClass;

/**
 * Proxy (Vekil) Fabrikası - FIX
 *
 * AOP (Aspect) uygulanması gereken sınıflar için
 * dinamik olarak Proxy (Vekil) sınıfları oluşturur.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class ProxyFactory
{
    /**
     * Oluşturulan proxy sınıfları cache'lenir (class name => generated code)
     * @var array<string, string>
     */
    private static array $proxyCache = [];

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

        // 2. Dinamik proxy sınıfı oluştur
        return $this->buildProxy($instance, $classReflection, $methodsToProxy);
    }

    /**
     * Dinamik proxy sınıfı oluşturur ve instantiate eder.
     *
     * @param object $instance
     * @param ReflectionClass $classReflection
     * @param array $methodsToProxy
     * @return object
     */
    private function buildProxy(
        object $instance,
        ReflectionClass $classReflection,
        array $methodsToProxy
    ): object {
        $originalClassName = $classReflection->getName();
        $proxyClassName = $this->generateProxyClassName($originalClassName);

        // Proxy sınıfı daha önce oluşturulduysa, direkt kullan
        if (!class_exists($proxyClassName, false)) {
            $this->createProxyClass($proxyClassName, $classReflection, $methodsToProxy);
        }

        // Proxy sınıfından yeni instance oluştur
        return new $proxyClassName($instance, $methodsToProxy);
    }

    /**
     * Benzersiz proxy sınıf adı oluşturur.
     */
    private function generateProxyClassName(string $originalClassName): string
    {
        $hash = substr(md5($originalClassName), 0, 8);
        $safeName = str_replace('\\', '_', $originalClassName);
        return "Zephyr_AOP_Proxy_{$safeName}_{$hash}";
    }

    /**
     * Dinamik olarak proxy sınıfı kodunu oluşturur ve eval ile yükler.
     *
     * ⚠️ eval() kullanımı: Sadece framework'ün kendi oluşturduğu,
     * güvenilir kod için kullanılıyor. Kullanıcı girdisi içermiyor.
     * @throws \ReflectionException
     */
    private function createProxyClass(
        string $proxyClassName,
        ReflectionClass $classReflection,
        array $methodsToProxy
    ): void {
        $originalClassName = $classReflection->getName();

        // Namespace'i ayır
        $namespaceParts = explode('\\', $proxyClassName);
        $shortClassName = array_pop($namespaceParts);
        $namespace = 'Zephyr\\AOP\\Generated';

        $code = <<<PHP
            namespace {$namespace};
            
            /**
             * Otomatik oluşturulmuş Proxy sınıfı
             * Orijinal: {$originalClassName}
             */
            class {$shortClassName} extends \\{$originalClassName}
            {
                private object \$__originalInstance;
                private array \$__proxiedMethods;
            
                public function __construct(object \$originalInstance, array \$proxiedMethods)
                {
                    \$this->__originalInstance = \$originalInstance;
                    \$this->__proxiedMethods = \$proxiedMethods;
                }
            
            PHP;

            // Her proxy'lenecek metot için override oluştur
            foreach ($methodsToProxy as $methodName => $aspects) {
                $method = $classReflection->getMethod($methodName);

                // Metot parametrelerini al
                $params = [];
                $args = [];
                foreach ($method->getParameters() as $param) {
                    $paramStr = '';

                    // Tip kontrolü
                    if ($param->hasType()) {
                        $type = $param->getType();
                        if ($type instanceof \ReflectionNamedType) {
                            $paramStr .= ($type->allowsNull() ? '?' : '') . $type->getName() . ' ';
                        }
                    }

                    $paramStr .= '$' . $param->getName();

                    // Varsayılan değer
                    if ($param->isDefaultValueAvailable()) {
                        $default = var_export($param->getDefaultValue(), true);
                        $paramStr .= " = {$default}";
                    }

                    $params[] = $paramStr;
                    $args[] = '$' . $param->getName();
                }

                $paramsStr = implode(', ', $params);
                $argsStr = implode(', ', $args);

                // Return type
                $returnType = '';
                if ($method->hasReturnType()) {
                    $type = $method->getReturnType();
                    if ($type instanceof \ReflectionNamedType) {
                        $returnType = ': ' . ($type->allowsNull() ? '?' : '') . $type->getName();
                    }
                }

                $code .= <<<PHP
                
                    public function {$methodName}({$paramsStr}){$returnType}
                    {
                        \$args = [{$argsStr}];
                        
                        // Aspect Pipeline'ını kur
                        \$coreLogic = function () use (\$args) {
                            return \$this->__originalInstance->{$methodName}(...\$args);
                        };
                
                        \$methodReflection = new \ReflectionMethod(\$this->__originalInstance, '{$methodName}');
                        \$aspects = \$this->__proxiedMethods['{$methodName}'];
                
                        \$pipeline = array_reduce(
                            \$aspects,
                            function (\$next, \$aspectInfo) use (\$methodReflection, \$args) {
                                return function () use (\$next, \$aspectInfo, \$methodReflection, \$args) {
                                    \$aspect = \$aspectInfo['aspect'];
                                    \$attribute = \$aspectInfo['attribute'];
                                    return \$aspect->process(\$next, \$methodReflection, \$args, \$attribute);
                                };
                            },
                            \$coreLogic
                        );
                
                        return \$pipeline();
                    }
                
                PHP;
            }

            $code .= "}\n";

        // Proxy sınıfını yükle
        eval($code);

        // Namespace'li tam adı class_alias ile kaydet
        class_alias("{$namespace}\\{$shortClassName}", $proxyClassName);
    }
}