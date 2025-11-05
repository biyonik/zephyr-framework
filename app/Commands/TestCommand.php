<?php

declare(strict_types=1);

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Zephyr\Core\App; //

/**
 * Örnek Test Komutu
 *
 * CLI entegrasyonunun çalışıp çalışmadığını test eder.
 */
class TestCommand extends Command
{
    /**
     * Komutun adını (zephyr test:hello) ve açıklamasını tanımla.
     */
    protected function configure(): void
    {
        $this
            ->setName('test:hello')
            ->setDescription('Basit bir test komutu çalıştırır.')
            ->addArgument('name', InputArgument::OPTIONAL, 'Kime merhaba denecek?', 'Dünya');
    }

    /**
     * Komut çalıştığında yürütülecek mantık.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Argümanı al
        $name = $input->getArgument('name');

        // Renkli çıktılar
        $output->writeln("<info>Merhaba, {$name}!</info>");
        
        // Framework'e erişebildiğimizi kanıtlayalım:
        // app() helper'ını (helpers.php) veya DI ile App'i alabiliriz.
        $version = app()->version(); //
        $env = app()->environment(); //

        $output->writeln("Zephyr Framework v<comment>{$version}</comment> çalışıyor.");
        $output->writeln("Mevcut Ortam (Environment): <comment>{$env}</comment>");

        // Başarılı komut durumu
        return Command::SUCCESS;
    }
}