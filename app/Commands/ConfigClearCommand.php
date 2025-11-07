<?php

declare(strict_types=1);

namespace App\Commands;

use Zephyr\Core\App;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Config Clear Komutu
 *
 * Oluşturulan yapılandırma önbellek dosyasını siler.
 */
class ConfigClearCommand extends Command
{
    protected static $defaultName = 'config:clear';
    protected App $app;

    public function __construct(App $app)
    {
        parent::__construct();
        $this->app = $app;
    }

    protected function configure(): void
    {
        $this->setDescription('Yapılandırma (config) önbelleğini temizler.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheFile = $this->app->basePath('storage/framework/cache/config.php');

        if (file_exists($cacheFile)) {
            try {
                unlink($cacheFile);
                $output->writeln("<info>Yapılandırma önbelleği başarıyla temizlendi!</info>");
            } catch (\Throwable $e) {
                $output->writeln("<error>Yapılandırma önbelleği silinemedi:</error> {$e->getMessage()}");
                return Command::FAILURE;
            }
        } else {
            $output->writeln("<comment>Yapılandırma önbelleği zaten temiz.</comment>");
        }

        return Command::SUCCESS;
    }
}