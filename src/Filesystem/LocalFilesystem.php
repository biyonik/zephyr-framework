<?php

declare(strict_types=1);

namespace Zephyr\Filesystem;

/**
 * 'local' Sürücüsü
 *
 * Sunucunun yerel diskini kullanan dosya sistemi sürücüsü.
 */
class LocalFilesystem implements FilesystemInterface
{
    /**
     * Bu diskin kök dizini (örn: /var/www/storage/app/public)
     */
    protected string $root;

    /**
     * Bu diskin kök URL'i (örn: http://localhost/storage)
     */
    protected ?string $url;

    public function __construct(string $root, ?string $url = null)
    {
        $this->root = rtrim($root, '/');
        $this->url = $url ? rtrim($url, '/') : null;
    }

    /**
     * Verilen yola kök dizini ekler.
     * Örn: 'avatars/1.jpg' -> '/var/www/storage/app/public/avatars/1.jpg'
     */
    protected function applyRoot(string $path): string
    {
        return $this->root . '/' . ltrim($path, '/');
    }

    /**
     * Yoldan önce dizinlerin var olduğundan emin olur (mkdir -p).
     */
    protected function ensureDirectoryExists(string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }
    }

    public function exists(string $path): bool
    {
        return file_exists($this->applyRoot($path));
    }

    public function get(string $path): string|false
    {
        if (!$this->exists($path)) {
            return false;
        }
        return file_get_contents($this->applyRoot($path));
    }

    public function put(string $path, string $contents): bool
    {
        $fullPath = $this->applyRoot($path);
        $this->ensureDirectoryExists($fullPath);

        return file_put_contents($fullPath, $contents) !== false;
    }

    public function putFile(string $path, string $filePath): string|false
    {
        // Dosya yüklemeleri için benzersiz bir isim oluştur
        $hashName = bin2hex(random_bytes(20));
        $extension = pathinfo($filePath, PATHINFO_EXTENSION); // Geçici dosyadan uzantı almayı dene

        // Eğer tmp_name'den uzantı gelmezse (nadiren),
        // yüklenen dosyanın orijinal adından almayı deneyebiliriz
        // (Bu senaryo için UploadedFile sınıfı gerekir, şimdilik basitleştiriyoruz)
        if (empty($extension) && isset($_FILES)) {
            foreach ($_FILES as $file) {
                if ($file['tmp_name'] === $filePath) {
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    break;
                }
            }
        }

        $fullPath = rtrim($path, '/') . '/' . $hashName . '.' . $extension;
        $destination = $this->applyRoot($fullPath);

        $this->ensureDirectoryExists($destination);

        // Güvenlik: Bu bir HTTP yüklemesi mi?
        if (is_uploaded_file($filePath)) {
            if (move_uploaded_file($filePath, $destination)) {
                return $fullPath;
            }
        } else {
            // Veya sunucudaki başka bir dosya mı? (örn: Seeder)
            if (copy($filePath, $destination)) {
                return $fullPath;
            }
        }

        return false;
    }

    public function delete(string $path): bool
    {
        if (!$this->exists($path)) {
            return true; // Zaten yoksa, başarılı say
        }
        return unlink($this->applyRoot($path));
    }

    public function path(string $path): string
    {
        return $this->applyRoot($path);
    }

    public function url(string $path): ?string
    {
        if (!$this->url) {
            // Bu disk 'public' değil, URL'i yok.
            return null;
        }
        return $this->url . '/' . ltrim($path, '/');
    }
}