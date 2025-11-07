<?php

declare(strict_types=1);

namespace App\Commands;

use Zephyr\Core\App;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Config Cache Komutu
 *
 * Tüm yapılandırma dosyalarını tek bir önbellek dosyasına birleştirir
 * ve yükleme (bootstrap) süresini hızlandırır.
 */
class ConfigCacheCommand extends Command
{
    protected static $defaultName = 'config:cache';
    protected App $app;

    public function __construct(App $app)
    {
        parent::__construct();
        $this->app = $app;
    }

    protected function configure(): void
    {
        $this->setDescription('Tüm yapılandırma dosyalarını tek bir dosyaya önbelleğe alır.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("<comment>Yapılandırma (config) önbelleği oluşturuluyor...</comment>");

        $cacheFile = $this->app->basePath('storage/framework/cache/config.php');

        // config/ dizinindeki tüm ayarları al (henüz cache'lenmemiş haliyle)
        $config = $this->app->resolve('config')->all();

        // Bu diziyi 'var_export' ile PHP kodu olarak dışa aktar
        $content = "<?php\n\n// Zephyr Framework - Derlenmiş Yapılandırma Önbelleği\n\nreturn "
            . var_export($config, true)
            . ";\n";

        // Önbellek dosyasına yaz
        try {
            file_put_contents($cacheFile, $content);
        } catch (\Throwable $e) {
            $output->writeln("<error>Yapılandırma önbelleği yazılamadı:</error> {$e->getMessage()}");
            return Command::FAILURE;
        }

        $output->writeln("<info>Yapılandırma başarıyla önbelleğe alındı!</info>");
        $output->writeln("Dosya: {$cacheFile}");

        return Command::SUCCESS;
    }
}