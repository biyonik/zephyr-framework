<?php

declare(strict_types=1);

namespace Zephyr\Database;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Temel Seeder Sınıfı
 *
 * Tüm veritabanı tohumlayıcıları bu sınıfı miras alır.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
abstract class Seeder
{
    /**
     * Komut satırı çıktısı için (opsiyonel).
     * db:seed komutu tarafından otomatik olarak atanır.
     */
    public ?OutputInterface $output = null;

    /**
     * Tohumlayıcının çalıştıracağı ana metot.
     */
    abstract public function run(): void;

    /**
     * Başka bir seeder sınıfını (dosya adına göre) çalıştırır.
     * Bu, DatabaseSeeder'dan diğer seeder'ları çağırmak için kullanılır.
     *
     * @param string $seederFile (örn: 'UserSeeder.php')
     * @throws \RuntimeException
     */
    public function call(string $seederFile): void
    {
        $path = base_path('database/seeders/' . $seederFile);

        if (!file_exists($path)) {
            $this->output?->writeln("<error>Seeder dosyası bulunamadı: {$seederFile}</error>");
            throw new \RuntimeException("Seeder dosyası bulunamadı: {$path}");
        }

        // Tıpkı migration'larda olduğu gibi, dosya bir nesne döndürmeli
        $seeder = require $path;

        if (!$seeder instanceof Seeder) {
            $this->output?->writeln("<error>{$seederFile} dosyası geçerli bir Seeder nesnesi döndürmedi.</error>");
            throw new \RuntimeException("Seeder dosyası ({$seederFile}) bir Seeder nesnesi döndürmelidir.");
        }

        // 'output' arayüzünü iç içe çağrılan seeder'a aktar
        $seeder->output = $this->output;

        $this->output?->writeln("<comment>  Çalıştırılıyor:</comment> {$seederFile}");
        $seeder->run();
    }
}