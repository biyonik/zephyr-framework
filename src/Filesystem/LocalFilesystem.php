<?php

declare(strict_types=1);

namespace Zephyr\Filesystem;


/**
 * 'local' Sürücüsü - FIXED (Secure File Upload)
 *
 * MIME type validation, file size limits ve güvenli dosya adlandırma.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
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

    /**
     * İzin verilen MIME type'lar (null = tümü izinli)
     */
    protected ?array $allowedMimeTypes = null;

    /**
     * Maximum dosya boyutu (bytes, 0 = sınırsız)
     */
    protected int $maxFileSize = 0;

    public function __construct(
        string $root,
        ?string $url = null,
        ?array $allowedMimeTypes = null,
        int $maxFileSize = 0
    ) {
        $this->root = rtrim($root, '/');
        $this->url = $url ? rtrim($url, '/') : null;
        $this->allowedMimeTypes = $allowedMimeTypes;
        $this->maxFileSize = $maxFileSize;
    }

    /**
     * Verilen yola kök dizini ekler.
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
        // 1. GÜVENLIK: Dosya var mı?
        if (!file_exists($filePath) || !is_file($filePath)) {
            throw new \InvalidArgumentException("Dosya bulunamadı: {$filePath}");
        }

        // 2. GÜVENLIK: Dosya boyutu kontrolü
        $fileSize = filesize($filePath);
        if ($this->maxFileSize > 0 && $fileSize > $this->maxFileSize) {
            throw new \InvalidArgumentException(
                "Dosya çok büyük. Maximum: " . $this->formatBytes($this->maxFileSize) .
                ", Gelen: " . $this->formatBytes($fileSize)
            );
        }

        // 3. GÜVENLIK: MIME type kontrolü
        $mimeType = $this->detectMimeType($filePath);

        if ($this->allowedMimeTypes !== null && !in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw new \InvalidArgumentException(
                "Dosya tipi izin verilmiyor. Gelen: {$mimeType}, İzin verilenler: " .
                implode(', ', $this->allowedMimeTypes)
            );
        }

        // 4. GÜVENLIK: Tehlikeli dosya uzantılarını engelle
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'pht', 'phar', 'exe', 'sh', 'bat'];
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, $dangerousExtensions, true)) {
            throw new \InvalidArgumentException(
                "Güvenlik nedeniyle '{$extension}' uzantılı dosyalar yüklenemez."
            );
        }

        // 5. Güvenli dosya adı oluştur
        $extension = $this->getExtensionFromMimeType($mimeType) ?? $extension;
        $hashName = $this->generateSecureFilename($extension);

        $fullPath = rtrim($path, '/') . '/' . $hashName;
        $destination = $this->applyRoot($fullPath);

        $this->ensureDirectoryExists($destination);

        // 6. Dosyayı güvenli şekilde kopyala
        $success = false;

        // HTTP upload mı?
        if (is_uploaded_file($filePath)) {
            $success = move_uploaded_file($filePath, $destination);
        } else {
            // Sunucudaki başka bir dosya (örn: Seeder)
            $success = copy($filePath, $destination);
        }

        if (!$success) {
            throw new \RuntimeException("Dosya taşınamadı: {$destination}");
        }

        // 7. Dosya izinlerini ayarla (0644 = owner read/write, others read)
        chmod($destination, 0644);

        return $fullPath;
    }

    public function delete(string $path): bool
    {
        if (!$this->exists($path)) {
            return true;
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
            return null;
        }
        return $this->url . '/' . ltrim($path, '/');
    }

    /**
     * Dosyanın gerçek MIME type'ını tespit eder
     *
     * Uzantıya değil, dosya içeriğine bakar (magic bytes).
     *
     * @param string $filePath
     * @return string
     */
    protected function detectMimeType(string $filePath): string
    {
        // finfo (fileinfo extension) kullan
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);

            if ($mimeType !== false) {
                return $mimeType;
            }
        }

        // Fallback: mime_content_type
        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($filePath);
            if ($mimeType !== false) {
                return $mimeType;
            }
        }

        // Son çare: Uzantıdan tahmin et (güvenilir değil!)
        return $this->guessMimeTypeFromExtension($filePath);
    }

    /**
     * MIME type'a göre doğru uzantıyı döndürür
     */
    protected function getExtensionFromMimeType(string $mimeType): ?string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/json' => 'json',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];

        return $map[$mimeType] ?? null;
    }

    /**
     * Uzantıdan MIME type tahmin et (fallback)
     */
    protected function guessMimeTypeFromExtension(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'txt' => 'text/plain',
        ];

        return $map[$extension] ?? 'application/octet-stream';
    }

    /**
     * Güvenli ve benzersiz dosya adı oluşturur
     *
     * Format: timestamp_randomhash.extension
     * @throws RandomException
     */
    protected function generateSecureFilename(string $extension): string
    {
        $timestamp = time();
        $randomHash = bin2hex(random_bytes(16));

        return "{$timestamp}_{$randomHash}.{$extension}";
    }

    /**
     * Byte'ı okunabilir formata çevirir
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));

        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}