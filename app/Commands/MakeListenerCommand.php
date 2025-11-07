<?php

declare(strict_types=1);

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeListenerCommand extends Command
{
    protected static $defaultName = 'make:listener';

    protected function configure(): void
    {
        $this
            ->setDescription('Yeni bir Olay Dinleyici (Listener) sınıfı oluşturur.')
            ->addArgument('name', InputArgument::REQUIRED, 'Listener sınıfının adı (örn: SendWelcomeEmail)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $this->studly($input->getArgument('name'));
        $filename = "{$name}.php";
        $path = base_path('app/Listeners');
        $filepath = "{$path}/{$filename}";

        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException("Dizin oluşturulamadı: {$path}");
        }

        if (file_exists($filepath)) {
            $output->writeln("<error>Listener zaten mevcut:</error> {$filename}");
            return Command::FAILURE;
        }

        $stub = $this->getStub($name);

        if (file_put_contents($filepath, $stub) === false) {
            $output->writeln("<error>Listener dosyası oluşturulamadı:</error> {$filepath}");
            return Command::FAILURE;
        }

        $output->writeln("<info>Listener oluşturuldu:</info> app/Listeners/{$filename}");
        $output->writeln("<comment>Şimdi bunu config/events.php dosyasına kaydedin.</comment>");
        return Command::SUCCESS;
    }

    protected function getStub(string $className): string
    {
        return <<<STUB
<?php

declare(strict_types=1);

namespace App\Listeners;

// Örnek:
// use App\Events\UserRegistered;

/**
 * Listener: {$className}
 *
 * Bu sınıf, tetiklenen bir olayı (event) dinler ve
 * bir iş mantığı (örn: e-posta gönderme) yürütür.
 */
class {$className}
{
    /**
     * Listener'ın constructor'ı.
     * Gerekli servisleri (örn: MailService) DI ile alabilirsiniz.
     */
    public function __construct()
    {
        //
    }

    /**
     * Olay (event) gerçekleştiğinde bu metot çalışır.
     *
     * @param object \$event Olay nesnesi
     */
    public function handle(object \$event): void
    {
        // Olayın tipini kontrol edebilirsiniz:
        // if (\$event instanceof \App\Events\UserRegistered) {
        //     \$user = \$event->user;
        //     // E-posta gönderme mantığı...
        // }
        
        log()->info("Event [".get_class(\$event)."] handled by: {$className}");
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