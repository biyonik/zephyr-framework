<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Zephyr\Core\App;
use Zephyr\Support\Config;

abstract class TestCase extends BaseTestCase
{
    protected App $app;

    /**
     * Her testten önce çalışır.
     * Temiz bir App instance'ı kurar.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->app = $this->createApplication();
    }

    /**
     * Her testten sonra çalışır.
     * App singleton'ını ve Config'i temizler.
     */
    protected function tearDown(): void
    {
        // Singleton'ı sıfırla
        $reflection = new \ReflectionClass(App::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        Config::clear(); //

        parent::tearDown();
    }

    /**
     * Testler için App'i bootstrap eder.
     */
    public function createApplication(): App
    {
        $app = App::getInstance(__DIR__ . '/..'); //
        $app->loadEnvironment(); //
        $app->loadConfiguration(); //
        
        // Rapor #3'e göre debug'ı testlerde kapalı tut
        Config::set('app.debug', false); //
        
        $app->registerProviders(); //

        // Testler için rotaları yükle
        $router = $app->router(); //
        require __DIR__ . '/../routes/api.php'; //

        return $app;
    }
}