<?php

declare(strict_types=1);

use Zephyr\Database\Seeder;

/**
 * Master Database Seeder
 *
 * Çalıştırmak için: php zephyr db:seed
 *
 * Bu sınıf, diğer tüm seeder'ları çağıran ana giriş noktasıdır.
 */
return new class extends Seeder
{
    /**
     * Tüm veritabanı tohumlarını çalıştırır.
     */
    public function run(): void
    {
        // $this->output?->writeln("DatabaseSeeder çalıştırıldı.");
        
        // Diğer seeder'ları buradan çağırın:
        // $this->call('UserSeeder.php');
        // $this->call('PostSeeder.php');
    }
};