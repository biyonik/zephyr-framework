<?php

declare(strict_types=1);

namespace Zephyr\Validation\Contracts;

/**
 * Doğrulama sonucu için arayüz.
 *
 * @package Framework\Core\Validation
 * @subpackage Contracts
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
interface ValidationResultInterface
{
    /**
     * Hata ekler.
     *
     * @param string $field Alan adı
     * @param string $message Hata mesajı
     * @return void
     */
    public function addError(string $field, string $message): void;

    /**
     * Tüm hataları döndürür.
     *
     * @return array<string, array<string>> Hatalar
     */
    public function getErrors(): array;

    /**
     * Belirli bir alanın hatalarını döndürür.
     *
     * @param string $field Alan adı
     * @return array<string> Alan hataları
     */
    public function getFieldErrors(string $field): array;

    /**
     * Doğrulanmış veriyi döndürür.
     *
     * @return array<string, mixed> Doğrulanmış veri
     */
    public function getValidData(): array;

    /**
     * Hata olup olmadığını kontrol eder.
     *
     * @return bool Hata varsa true
     */
    public function hasErrors(): bool;

    /**
     * Belirli bir alanda hata olup olmadığını kontrol eder.
     *
     * @param string $field Alan adı
     * @return bool Alanda hata varsa true
     */
    public function hasError(string $field): bool;

    /**
     * İlk hatayı döndürür.
     *
     * @return string|null İlk hata mesajı
     */
    public function getFirstError(): ?string;
}