<?php

declare(strict_types=1);

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeEventCommand extends Command
{
    protected static $defaultName = 'make:event';

    protected function configure(): void
    {
        $this
            ->setDescription('Yeni bir Olay (Event) sınıfı oluşturur.')
            ->addArgument('name', InputArgument::REQUIRED, 'Event sınıfının adı (örn: UserRegistered)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $this->studly($input->getArgument('name'));
        $filename = "{$name}.php";
        $path = base_path('app/Events');
        $filepath = "{$path}/{$filename}";

        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException("Dizin oluşturulamadı: {$path}");
        }

        if (file_exists($filepath)) {
            $output->writeln("<error>Event zaten mevcut:</error> {$filename}");
            return Command::FAILURE;
        }

        $stub = $this->getStub($name);

        if (file_put_contents($filepath, $stub) === false) {
            $output->writeln("<error>Event dosyası oluşturulamadı:</error> {$filepath}");
            return Command::FAILURE;
        }

        $output->writeln("<info>Event oluşturuldu:</info> app/Events/{$filename}");
        $output->writeln("<comment>Şimdi bunu config/events.php dosyasına kaydedin.</comment>");
        return Command::SUCCESS;
    }

    protected function getStub(string $className): string
    {
        return <<<STUB
<?php

declare(strict_types=1);

namespace App\Events;

/**
 * Event: {$className}
 *
 * Bu olay, bir iş mantığı gerçekleştiğinde tetiklenir.
 * (örn: Bir kullanıcı kaydolduğunda)
 *
 * public \$user; // Örnek: Olayla ilgili veriyi taşıyın
 *
 * public function __construct(/* \App\Models\User \$user */)
 * {
 * // \$this->user = \$user;
 * }
 */
class {$className}
{
    /**
     * Olayla ilgili verileri tutar.
     * Dinleyicilerin (Listeners) bu verilere erişmesi gerekir.
     */
    
    // Örnek:
    // public \App\Models\User \$user;

    public function __construct(/* ...parametreler */)
    {
        // \$this->user = \$user;
        log()->info("Event tetiklendi: {$className}");
    }
}
STUB;
    }

    protected function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }
}