<?php

declare(strict_types=1);

namespace App\Commands;

use Zephyr\Database\Seeder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * db:seed Komutu
 *
 * Veritabanını 'DatabaseSeeder' sınıfı aracılığıyla tohumlar.
 */
class SeedCommand extends Command
{
    protected static $defaultName = 'db:seed';

    protected function configure(): void
    {
        $this
            ->setDescription('Veritabanını örnek verilerle doldurur (seed).')
            ->setHelp('Bu komut, "database/seeders/DatabaseSeeder.php" dosyasını çalıştırır.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $masterSeederFile = 'DatabaseSeeder.php';
        $path = base_path('database/seeders/' . $masterSeederFile);

        if (!file_exists($path)) {
            $output->writeln("<error>Master seeder ({$masterSeederFile}) bulunamadı.</error>");
            $output->writeln("Lütfen 'php zephyr make:seeder DatabaseSeeder' komutu ile oluşturun.");
            return Command::FAILURE;
        }
        
        try {
            $output->writeln("<comment>Veritabanı seed işlemi başlatılıyor...</comment>");
            
            $seeder = require $path;
            if (!$seeder instanceof Seeder) {
                throw new \RuntimeException("{$masterSeederFile} bir Seeder nesnesi döndürmelidir.");
            }

            // Çıktı arayüzünü Seeder'a ata, böylece $this->output kullanabilir
            $seeder->output = $output;
            $seeder->run();

            $output->writeln("<info>Veritabanı seed işlemi başarıyla tamamlandı.</info>");
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $output->writeln("<error>Seed işlemi hatası: " . $e->getMessage() . "</error>");
            $output->writeln($e->getFile() . " @ " . $e->getLine());
            return Command::FAILURE;
        }
    }
}