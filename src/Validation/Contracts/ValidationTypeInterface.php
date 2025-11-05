<?php

declare(strict_types=1);

namespace Zephyr\Validation\Contracts;

use Zephyr\Validation\ValidationResult;

/**
 * Doğrulama tipi için arayüz.
 *
 * @package Framework\Core\Validation
 * @subpackage Contracts
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
interface ValidationTypeInterface
{
    /**
     * Alanı zorunlu kılar.
     *
     * @return static Mevcut şema tipi
     */
    public function required(): self;

    /**
     * Alan için etiket ayarlar.
     *
     * @param string $label Alan etiketi
     * @return static Mevcut şema tipi
     */
    public function setLabel(string $label): self;

    /**
     * Varsayılan değer atar.
     *
     * @param mixed $value Varsayılan değer
     * @return static Mevcut şema tipi
     */
    public function default(mixed $value): self;

    /**
     * Özel hata mesajı ekler.
     *
     * @param string $rule Kural adı
     * @param string $message Hata mesajı
     * @return static Mevcut şema tipi
     */
    public function errorMessage(string $rule, string $message): self;

    /**
     * Alanı doğrular.
     *
     * @param string $field Alan adı
     * @param mixed $value Alan değeri
     * @param ValidationResult $result Doğrulama sonucu
     * @return void
     */
    public function validate(string $field, mixed $value, ValidationResult $result): void;

    /**
     * Doğrulama kurallarını diziye dönüştürür.
     *
     * @return array<string, string|array<string>> Doğrulama kuralları
     */
    public function toRulesArray(): array;

    /**
     * Alan için özel hata mesajlarını döndürür.
     *
     * @param string $field Alan adı
     * @return array<string, string> Kural adı ve mesajı
     */
    public function getErrorMessages(string $field): array;
}