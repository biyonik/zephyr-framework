<?php

declare(strict_types=1);

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeSeederCommand extends Command
{
    protected static $defaultName = 'make:seeder';

    protected function configure(): void
    {
        $this
            ->setDescription('Yeni bir veritabanı tohumlayıcı (seeder) sınıfı oluşturur.')
            ->addArgument('name', InputArgument::REQUIRED, 'Seeder sınıfının adı (örn: UserSeeder)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        
        // "Seeder" sonekini içermiyorsa ekle
        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }
        
        $className = $this->studly($name);
        $filename = "{$className}.php";

        $path = base_path('database/seeders');
        $filepath = "{$path}/{$filename}";

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        if (file_exists($filepath)) {
            $output->writeln("<error>Seeder zaten mevcut:</error> {$filename}");
            return Command::FAILURE;
        }

        $stub = $this->getStub($className);

        if (file_put_contents($filepath, $stub) === false) {
            $output->writeln("<error>Seeder dosyası oluşturulamadı:</error> {$filepath}");
            return Command::FAILURE;
        }

        $output->writeln("<info>Seeder oluşturuldu:</info> database/seeders/{$filename}");
        return Command::SUCCESS;
    }

    /**
     * Oluşturulacak dosya için şablon (stub) döndürür.
     */
    protected function getStub(string $className): string
    {
        return <<<STUB
<?php

declare(strict_types=1);

use Zephyr\Database\Seeder;
// Kullanacağınız modelleri buraya ekleyin
// use App\Models\User;

/**
 * Seeder: {$className}
 */
return new class extends Seeder
{
    /**
     * Veritabanı tohumlarını çalıştırır.
     */
    public function run(): void
    {
        // Örnek:
        // \App\Models\User::create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        //     'password' => 'password' // Model 'setPasswordAttribute' ile hashleyecektir
        // ]);
        
        \$this->output?->writeln("  <info>✓ Tamamlandı:</info>  {$className}");
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