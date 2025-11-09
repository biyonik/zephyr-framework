<?php

declare(strict_types=1);

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeActionCommand extends Command
{
    protected static $defaultName = 'make:action';

    protected function configure(): void
    {
        $this
            ->setDescription('Yeni bir "Action" (Eylem) sınıfı oluşturur (Pragmatik CQRS).')
            ->addArgument('name', InputArgument::REQUIRED, 'Action sınıfının adı (örn: Auth/RegisterUserAction)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        // Sınıf adını ve namespace'i ayır
        if (str_contains($name, '/')) {
            $parts = explode('/', $name);
            $className = array_pop($parts);
            $namespace = 'App\\Actions\\' . implode('\\', $parts);
            $path = base_path('app/Actions/' . implode('/', $parts));
        } else {
            $className = $name;
            $namespace = 'App\\Actions';
            $path = base_path('app/Actions');
        }

        // "Action" sonekini içermiyorsa ekle
        if (!str_ends_with($className, 'Action')) {
            $className .= 'Action';
        }

        $filename = "{$className}.php";
        $filepath = "{$path}/{$filename}";

        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            $output->writeln("<error>Dizin oluşturulamadı:</error> {$path}");
            return Command::FAILURE;
        }

        if (file_exists($filepath)) {
            $output->writeln("<error>Action zaten mevcut:</error> {$filepath}");
            return Command::FAILURE;
        }

        $stub = $this->getStub($className, $namespace);

        if (file_put_contents($filepath, $stub) === false) {
            $output->writeln("<error>Action dosyası oluşturulamadı:</error> {$filepath}");
            return Command::FAILURE;
        }

        $output->writeln("<info>Action oluşturuldu:</info> app/Actions/" . str_replace(base_path('app/Actions/'), '', $filepath));
        return Command::SUCCESS;
    }

    protected function getStub(string $className, string $namespace): string
    {
        return <<<STUB
<?php

declare(strict_types=1);

namespace {$namespace};

/**
 * Action: {$className}
 *
 * Bu sınıfın tek bir sorumluluğu vardır:
 * [BU EYLEMİN AÇIKLAMASINI YAZIN]
 *
 * Controller'dan veya bir CLI komutundan çağrılabilir.
 */
class {$className}
{
    /**
     * Bağımlılıkları (DI) buraya enjekte edin.
     */
    public function __construct(
        // private \Zephyr\Logging\LogManager \$log
    ) {
    }

    /**
     * Eylemi çalıştırır.
     *
     * @param array \$data Gerekli veriler (örn: doğrulanmış istek verisi)
     * @return mixed Eylemin sonucu
     */
    public function execute(array \$data): mixed
    {
        // log()->info("{$className} çalıştırıldı", \$data);

        // ...
        // İş mantığınız (business logic) buraya gelecek
        // ...

        return true;
    }
}
STUB;
    }
}