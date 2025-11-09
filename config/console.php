<?php

/**
 * CLI Komut Kayıtları
 *
 * Buraya eklediğiniz tüm komut sınıfları 'zephyr' CLI
 * uygulamasına otomatik olarak yüklenecektir.
 */

use App\Commands\ConfigCacheCommand;
use App\Commands\ConfigClearCommand;
use App\Commands\MakeActionCommand;
use App\Commands\MakeEventCommand;
use App\Commands\MakeListenerCommand;
use App\Commands\MakeSeederCommand;
use App\Commands\StorageLinkCommand;
use App\Commands\TestCommand;
use App\Commands\OptimizeContainerCommand;
use App\Commands\OptimizeRouteCommand;
use App\Commands\MakeMigrationCommand;
use App\Commands\MigrateCommand;
use App\Commands\MakeControllerCommand;
use App\Commands\MakeModelCommand;
use App\Commands\MigrateRollbackCommand;

return [
    // Örnek komutumuzu buraya ekleyelim:
    TestCommand::class,

    // Gelecekte eklenecek komutlar:
    OptimizeContainerCommand::class,
    OptimizeRouteCommand::class,
    // \App\Commands\MakeControllerCommand::class,
    // Veritabanı Geçiş Komutları
    MakeMigrationCommand::class,
    MigrateCommand::class,
    MigrateRollbackCommand::class,

    MakeControllerCommand::class,
    MakeModelCommand::class,

    MakeEventCommand::class,
    MakeListenerCommand::class,

    ConfigCacheCommand::class,
    ConfigClearCommand::class,

    MakeSeederCommand::class,

    MakeActionCommand::class,
    StorageLinkCommand::class
];