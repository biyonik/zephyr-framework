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
        // base_path() helper'ı src/Support/helpers.php dosyanızdan geliyor [cite: biyonik/zephyr-framework/zephyr-framework-28e2066c3c9e95c1fc6a0dd198e9ea44c4e8a471/src/Support/helpers.php]
        $path = base_path('database/migrations');

        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
        }

        return $path;
    }

    /**
     * Oluşturulacak dosya için şablon (stub) döndürür.
     * (Geliştiricinin artık ham SQL görmemesi için şablonu güncelliyoruz)
     */
    protected function getStub(string $className): string
    {
        return <<<STUB
<?php

declare(strict_types=1);

use Zephyr\Database\Migration;
use Zephyr\Database\Schema\Blueprint; // <-- YENİ

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
        // \$this->schema->create('users', function (Blueprint \$table) {
        //     \$table->id();
        //     \$table->string('name');
        //     \$table->string('email')->unique();
        //     \$table->string('password');
        //     \$table->timestamps();
        // });
    }

    /**
     * Geçişi geri alır.
     */
    public function down(): void
    {
        // Örnek:
        // \$this->schema->dropIfExists('users');
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