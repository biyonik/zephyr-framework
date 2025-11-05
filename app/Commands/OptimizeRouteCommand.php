<?php

declare(strict_types = 1);

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zephyr\Core\App;
use Zephyr\Core\Router; //

/**
 * Optimize Route Komutu
 *
 * Rota tanımlamalarını (sadece Controller-tabanlı olanları)
 * hızlı yükleme için tek bir dosyaya önbelleğe alır.
 */
class OptimizeRouteCommand extends Command
{
    protected static $defaultName = 'optimize:route';
    protected App $app;
    protected Router $router;

    /**
     * Komuta App ve Router'ı enjekte et
     */
    public function __construct(App $app, Router $router)
    {
        parent::__construct();
        $this->app = $app;
        $this->router = $router;
    }

    protected function configure(): void
    {
        $this->setDescription('Rota tanımlamalarını hızlı yükleme için önbelleğe alır (sadece controller rotaları).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("<comment>Rota önbelleği oluşturuluyor...</comment>");

        // Önbellek dosyasının yolunu al
        $cacheFile = $this->app->basePath('storage/framework/cache/routes.php'); //

        // 1. routes/api.php dosyasını yükleyerek tüm rotaları topla
        // (Bunu yapmak için router'a geçici bir yükleyici metot ekleyeceğiz)
        $this->router->loadRoutesFile($this->app->basePath('routes/api.php'));

        // 2. Tüm rotaları al
        $allRoutes = $this->router->getRoutes(); //
        $cacheableRoutes = [];

        // 3. Sadece Controller-tabanlı olanları filtrele
        foreach ($allRoutes as $method => $routes) {
            foreach ($routes as $route) {
                // getAction() bir dizi ise (örn: [Controller, 'method'])
                // bu rota önbelleğe alınabilir demektir.
                if (is_array($route->getAction())) {
                    $cacheableRoutes[$method][] = $route;
                }
            }
        }

        // 4. Önbelleğe alınabilir rotaları serialize et ve dosyaya yaz
        if (!empty($cacheableRoutes)) {
            file_put_contents($cacheFile, serialize($cacheableRoutes));
            $count = count($cacheableRoutes, COUNT_RECURSIVE) - count($cacheableRoutes);
            $output->writeln("<info>{$count} adet controller rotası başarıyla önbelleğe alındı!</info>");
            $output->writeln("Dosya: {$cacheFile}");
        } else {
            // Önbellek dosyasını sil (varsa)
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
            $output->writeln("<info>Önbelleğe alınabilir controller rotası bulunamadı. Önbellek temizlendi.</info>");
        }

        return Command::SUCCESS;
    }
}