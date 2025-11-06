<?php

declare(strict_types=1);

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeMigrationCommand extends Command
{
    protected static $defaultName = 'make:migration';

    protected function configure(): void
    {
        $this
            ->setDescription('Yeni bir veritabanı geçiş dosyası oluşturur.')
            ->addArgument('name', InputArgument::REQUIRED, 'Migration dosyasının adı (örn: create_users_table)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $className = $this->studly($name);
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.php";

        $path = $this->getMigrationPath();
        $filepath = "{$path}/{$filename}";

        // Stub (şablon) içeriğini al
        $stub = $this->getStub($className);

        // Dosyayı oluştur
        if (file_put_contents($filepath, $stub) === false) {
            $output->writeln("<error>Migration dosyası oluşturulamadı: {$filepath}</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Migration oluşturuldu:</info> {$filename}");
        return Command::SUCCESS;
    }

    /**
     * Migration dosyalarının saklanacağı dizini alır ve yoksa oluşturur.
     */
    protected function getMigrationPath(): string
    {
        // base_path() helper'ı src/Support/helpers.php dosyanızdan geliyor
        $path = base_path('database/migrations');

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    /**
     * Oluşturulacak dosya için şablon (stub) döndürür.
     */
    protected function getStub(string $className): string
    {
        return <<<STUB
<?php

declare(strict_types=1);

use Zephyr\Database\Migration;

/**
 * Migration: {$className}
 */
return new class extends Migration
{
    /**
     * Geçişi uygular.
     */
    public function up(): void
    {
        // Örnek:
        // $this->pdo->exec("
        //     CREATE TABLE IF NOT EXISTS users (
        //         id INT AUTO_INCREMENT PRIMARY KEY,
        //         name VARCHAR(255) NOT NULL,
        //         email VARCHAR(255) NOT NULL UNIQUE,
        //         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        //         updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        //     ) ENGINE=InnoDB;
        // ");
    }

    /**
     * Geçişi geri alır.
     */
    public function down(): void
    {
        // Örnek:
        // $this->pdo->exec("DROP TABLE IF EXISTS users;");
    }
};

STUB;
    }

    /**
     * snake_case_string'i StudlyCaseString'e çevirir.
     */
    protected function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }
}