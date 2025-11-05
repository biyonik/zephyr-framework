<?php

/**
 * CLI Komut Kayıtları
 *
 * Buraya eklediğiniz tüm komut sınıfları 'zephyr' CLI
 * uygulamasına otomatik olarak yüklenecektir.
 */

use App\Commands\TestCommand;
use App\Commands\OptimizeContainerCommand;
use App\Commands\OptimizeRouteCommand;

return [
    // Örnek komutumuzu buraya ekleyelim:
    TestCommand::class,

    // Gelecekte eklenecek komutlar:
    OptimizeContainerCommand::class,
    OptimizeRouteCommand::class,
    // \App\Commands\MakeControllerCommand::class,
];