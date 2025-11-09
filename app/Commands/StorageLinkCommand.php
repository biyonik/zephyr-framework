<?php

declare(strict_types=1);

namespace App\Commands;

use Zephyr\Core\App;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Storage Link Komutu
 *
 * 'public/storage' dizininden 'storage/app/public' dizinine
 * sembolik bir bağ (symlink) oluşturur.
 */
class StorageLinkCommand extends Command
{
    protected static $defaultName = 'storage:link';
    protected App $app;

    public function __construct(App $app)
    {
        parent::__construct();
        $this->app = $app;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Erişilebilir dosyalar için "public/storage" sembolik bağını oluşturur.')
            ->setHelp('Bu komut, "storage/app/public" dizinini "public/storage" dizinine bağlar.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = $this->app->basePath('storage/app/public');
        $link = $this->app->basePath('public/storage');

        if (file_exists($link) || is_link($link)) {
            $output->writeln("<error>Sembolik bağ zaten mevcut:</error> public/storage");
            return Command::FAILURE;
        }

        if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $target));
        }

        try {
            // 'symlink' fonksiyonunu kullan
            symlink($target, $link);
        } catch (\Throwable $e) {
            $output->writeln("<error>Sembolik bağ oluşturulamadı:</error> {$e->getMessage()}");
            $output->writeln("Lütfen komutu yönetici olarak çalıştırmayı deneyin (Windows) veya manuel olarak oluşturun.");
            return Command::FAILURE;
        }

        $output->writeln("<info>Sembolik bağ başarıyla oluşturuldu!</info>");
        $output->writeln("{$target} <comment>-></comment> {$link}");

        return Command::SUCCESS;
    }
}