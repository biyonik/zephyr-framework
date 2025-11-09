<?php

declare(strict_types=1);

namespace Zephyr\Filesystem;

/**
 * Dosya Sistemi Sürücüsü Arayüzü (Contract)
 *
 * Tüm dosya sistemi "disk" sürücülerinin (local, s3 vb.)
 * uygulaması gereken metotları tanımlar.
 */
interface FilesystemInterface
{
    /**
     * Dosyanın var olup olmadığını kontrol eder.
     */
    public function exists(string $path): bool;

    /**
     * Dosyanın içeriğini alır.
     */
    public function get(string $path): string|false;

    /**
     * Bir string içeriği dosyaya yazar (veya üzerine yazar).
     */
    public function put(string $path, string $contents): bool;

    /**
     * Bir dosyayı (örn: yüklenen geçici dosya) depolama alanına taşır.
     * Bu, dosya yüklemenin ana metodudur.
     *
     * @param string $path Kaydedilecek hedef yol (örn: 'avatars/user_1.jpg')
     * @param string $filePath Kaynak dosyanın yolu (örn: $_FILES['avatar']['tmp_name'])
     * @return string|false Kaydedilen dosyanın tam yolu veya hata
     */
    public function putFile(string $path, string $filePath): string|false;

    /**
     * Bir dosyayı siler.
     */
    public function delete(string $path): bool;

    /**
     * Bir dosyanın tam sunucu yolunu (server path) alır.
     */
    public function path(string $path): string;

    /**
     * Bir dosyanın web'den erişilebilir URL'ini alır.
     * (Sadece 'public' gibi URL tanımlı diskler için çalışır)
     */
    public function url(string $path): ?string;
}