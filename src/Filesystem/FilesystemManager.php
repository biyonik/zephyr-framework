<?php

declare(strict_types=1);

namespace Zephyr\Filesystem;

use Zephyr\Core\App;

/**
 * Dosya Sistemi Yöneticisi (Storage Facade)
 *
 * config/filesystems.php dosyasını okur ve farklı "disk" sürücülerini
 * yönetir. 'storage()' helper'ı bu sınıfa erişir.
 *
 * @mixin FilesystemInterface
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class FilesystemManager
{
    protected App $app;
    protected array $config;

    /**
     * Oluşturulan ve önbelleğe alınan diskler (sürücüler).
     * @var array<string, FilesystemInterface>
     */
    protected array $disks = [];

    /**
     * Varsayılan diskin adı (örn: 'local').
     */
    protected string $defaultDisk;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->config = $app->resolve('config')->get('filesystems');
        $this->defaultDisk = $this->config['default'] ?? 'local';
    }

    /**
     * Belirtilen (veya varsayılan) diski döndürür.
     *
     * @param string|null $name Disk adı (örn: 'local', 'public')
     * @return FilesystemInterface
     * @throws \InvalidArgumentException
     */
    public function disk(string $name = null): FilesystemInterface
    {
        $name = $name ?? $this->defaultDisk;

        // 1. Önbellekten kontrol et
        if (isset($this->disks[$name])) {
            return $this->disks[$name];
        }

        // 2. Konfigürasyonu al
        $config = $this->config['disks'][$name] ?? null;
        if (is_null($config)) {
            throw new \InvalidArgumentException("Dosya sistemi diski [{$name}] bulunamadı.");
        }

        // 3. Sürücüyü oluştur
        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';
        if (!method_exists($this, $driverMethod)) {
            throw new \InvalidArgumentException("Dosya sistemi sürücüsü [{$config['driver']}] desteklenmiyor.");
        }

        // 4. Sürücüyü oluştur ve önbelleğe al
        return $this->disks[$name] = $this->$driverMethod($config);
    }

    /**
     * 'local' sürücüsünü (LocalFilesystem) oluşturur.
     */
    protected function createLocalDriver(array $config): FilesystemInterface
    {
        // Hem 'local' hem de 'public' diskleri bu sürücüyü kullanır
        return new LocalFilesystem(
            $config['root'],
            $config['url'] ?? null
        );
    }

    /**
     * Varsayılan diski alır.
     */
    public function getDefaultDriver(): FilesystemInterface
    {
        return $this->disk($this->defaultDisk);
    }

    /**
     * Çağrıları (put, get, exists vb.) varsayılan diske yönlendirir.
     * Bu, storage()->exists('file.txt') gibi kullanımları sağlar.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->getDefaultDriver()->{$method}(...$parameters);
    }
}