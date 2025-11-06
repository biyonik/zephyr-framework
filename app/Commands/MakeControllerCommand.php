<?php

declare(strict_types=1);

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeControllerCommand extends Command
{
    protected static $defaultName = 'make:controller';

    protected function configure(): void
    {
        $this
            ->setDescription('Yeni bir controller sınıfı oluşturur.')
            ->addArgument('name', InputArgument::REQUIRED, 'Controller sınıfının adı (örn: UserController)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        
        // "Controller" sonekini içermiyorsa ekle
        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }
        
        $className = $name;
        $filename = "{$className}.php";

        $path = base_path('app/Controllers');
        $filepath = "{$path}/{$filename}";

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        if (file_exists($filepath)) {
            $output->writeln("<error>Controller zaten mevcut:</error> {$filename}");
            return Command::FAILURE;
        }

        // Stub (şablon) içeriğini al
        $stub = $this->getStub($className);

        // Dosyayı oluştur
        if (file_put_contents($filepath, $stub) === false) {
            $output->writeln("<error>Controller dosyası oluşturulamadı:</error> {$filepath}");
            return Command::FAILURE;
        }

        $output->writeln("<info>Controller oluşturuldu:</info> app/Controllers/{$filename}");
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

namespace App\Controllers;

use Zephyr\Http\{Request, Response};

class {$className}
{
    /**
     * Kaynak listesini görüntüler.
     */
    public function index(Request $request): Response
    {
        return Response::success([
            'message' => '{$className}@index'
        ]);
    }

    /**
     * Yeni bir kaynak oluşturur (POST).
     */
    public function store(Request $request): Response
    {
        $data = $request->all();
        
        return Response::success(
            data: [
                'message' => 'Kaynak oluşturuldu',
                'received' => \$data,
            ],
            status: 201
        );
    }

    /**
     * Belirli bir kaynağı görüntüler (GET).
     */
    public function show(Request $request, string \$id): Response
    {
        return Response::success([
            'message' => '{$className}@show',
            'id' => \$id
        ]);
    }

    /**
     * Belirli bir kaynağı günceller (PUT/PATCH).
     */
    public function update(Request $request, string \$id): Response
    {
        $data = $request->all();

        return Response::success([
            'message' => 'Kaynak güncellendi',
            'id' => \$id,
            'received' => \$data
        ]);
    }

    /**
     * Belirli bir kaynağı siler (DELETE).
     */
    public function destroy(Request $request, string \$id): Response
    {
        return Response::success([
            'message' => 'Kaynak silindi',
            'id' => \$id
        ], status: 200); // Veya 204 No Content
    }
}
STUB;
    }
}