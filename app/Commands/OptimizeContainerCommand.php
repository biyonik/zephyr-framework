<?php

declare(strict_types=1);

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zephyr\Core\App;
use ReflectionClass;
use ReflectionException;

/**
 * Optimize Container Komutu
 *
 * Uygulamanın servis container'ını analiz eder ve Reflection kullanımını
 * baypas eden, optimize edilmiş bir "derlenmiş" container dosyası oluşturur.
 */
class OptimizeContainerCommand extends Command
{
    protected static $defaultName = 'optimize:container';
    protected App $app;

    /**
     * Komuta App container'ını enjekte et
     */
    public function __construct(App $app)
    {
        parent::__construct();
        $this->app = $app;
    }

    /**
     * Komutun yapılandırması
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Reflection kullanımını baypas etmek için derlenmiş bir container önbellek dosyası oluşturur.')
            ->setHelp('Bu komut, container binding\'lerini tarar ve hızlı yükleme için bir PHP dizisi oluşturur.');
    }

    /**
     * Komutun ana mantığı
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("<comment>Container önbelleği oluşturuluyor...</comment>");

        $compiledMap = [];

        // App'in mevcut binding'lerini al
        $bindings = $this->app->getBindings();

        foreach ($bindings as $abstract => $binding) {
            // Sadece paylaşılan (shared) ve somut (concrete) sınıf olanları derleyebiliriz
            if ($binding['shared'] && is_string($binding['concrete']) && class_exists($binding['concrete'])) {
                
                try {
                    $reflector = new ReflectionClass($binding['concrete']);
                    
                    // Sadece constructor'ı olan veya constructor'ı olmayan
                    // (yani Reflection ile çözümlenmesi gereken) sınıfları ele al
                    if (!$reflector->isInstantiable()) {
                        continue; // Interface veya abstract class'ları atla
                    }

                    $constructor = $reflector->getConstructor();
                    
                    // Bağımlılıkları (parametreleri) string olarak hazırla
                    $deps = [];
                    if ($constructor) {
                        foreach ($constructor->getParameters() as $param) {
                            $type = $param->getType();
                            if ($type && !$type->isBuiltin()) {
                                $deps[] = "\$app->resolve('{$type->getName()}')";
                            }
                            // Not: Bu basit implementasyon şimdilik sadece
                            // sınıf bağımlılıklarını çözer, opsiyonel/varsayılan
                            // parametreleri şimdilik yok sayar.
                        }
                    }

                    $depsString = implode(",\n            ", $deps);
                    
                    // Bu sınıf için optimize edilmiş fabrika (factory) Closure'ını oluştur
                    $compiledMap[$abstract] = "function (\$app) {
        return new \\{$binding['concrete']}(
            {$depsString}
        );
    }";

                } catch (ReflectionException $e) {
                    $output->writeln("<error>Sınıf yansıtılamadı: {$abstract} -> {$e->getMessage()}</error>");
                }
            }
        }

        // Önbellek dosyasını oluştur
        $cachePath = storage_path('framework/cache'); //
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $cacheFile = $cachePath . '/container.php';
        
        // PHP dosyası olarak yazdır
        $content = "<?php\n\n// Zephyr Framework - Derlenmiş Container Önbelleği\n\nreturn [\n";
        foreach ($compiledMap as $abstract => $factory) {
            // Tırnak işaretlerini düzgün ayarla
            $abstractEscaped = addslashes($abstract);
            $content .= "    '{$abstractEscaped}' => {$factory},\n\n";
        }
        $content .= "];\n";

        file_put_contents($cacheFile, $content);

        $output->writeln("<info>Container önbelleği başarıyla oluşturuldu!</info>");
        $output->writeln("Dosya: {$cacheFile}");
        
        return Command::SUCCESS;
    }
}