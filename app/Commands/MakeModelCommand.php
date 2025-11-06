<?php

declare(strict_types=1);

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeModelCommand extends Command
{
    protected static $defaultName = 'make:model';

    protected function configure(): void
    {
        $this
            ->setDescription('Yeni bir Model sınıfı oluşturur.')
            ->addArgument('name', InputArgument::REQUIRED, 'Model sınıfının adı (örn: User)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        
        // Sınıf adını (örn: "User") al
        $className = $this->studly(rtrim($name, 's')); // "Users" ise "User" yap

        $filename = "{$className}.php";
        $path = base_path('app/Models');
        $filepath = "{$path}/{$filename}";

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        if (file_exists($filepath)) {
            $output->writeln("<error>Model zaten mevcut:</error> {$filename}");
            return Command::FAILURE;
        }

        // Model adına göre tablo adını tahmin et (User -> users)
        $tableName = strtolower($className) . 's';

        // Stub (şablon) içeriğini al
        $stub = $this->getStub($className, $tableName);

        // Dosyayı oluştur
        if (file_put_contents($filepath, $stub) === false) {
            $output->writeln("<error>Model dosyası oluşturulamadı:</error> {$filepath}");
            return Command::FAILURE;
        }

        $output->writeln("<info>Model oluşturuldu:</info> app/Models/{$filename}");
        return Command::SUCCESS;
    }

    /**
     * Oluşturulacak dosya için şablon (stub) döndürür.
     */
    protected function getStub(string $className, string $tableName): string
    {
        return <<<STUB
<?php

declare(strict_types=1);

namespace App\Models;

use Zephyr\Database\Model;

class {$className} extends Model
{
    /**
     * Modele ait tablo adı.
     *
     * @var string
     */
    protected string \$table = '{$tableName}';

    /**
     * Toplu atama (mass assignment) ile doldurulabilir alanlar.
     *
     * @var array
     */
    protected array \$fillable = [
        // 'name', 'email', 'password'
    ];

    /**
     * JSON'a dönüştürülürken gizlenecek alanlar.
     *
     * @var array
     */
    protected array \$hidden = [
        'password',
    ];

    /**
     * Otomatik tip dönüşümü yapılacak alanlar.
     *
     * @var array
     */
    protected array \$casts = [
        // 'is_admin' => 'bool',
        // 'options' => 'json'
    ];
}
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