<?php

declare(strict_types=1);

namespace Zephyr\Validation\Contracts;

use Zephyr\Validation\ValidationResult;
use Zephyr\Validation\SchemaType\StringType;
use Zephyr\Validation\SchemaType\NumberType;
use Zephyr\Validation\SchemaType\BooleanType;
use Zephyr\Validation\SchemaType\DateType;
use Zephyr\Validation\SchemaType\ArrayType;
use Zephyr\Validation\SchemaType\ObjectType;

/**
 * Doğrulama şeması için arayüz.
 *
 * @package Framework\Core\Validation
 * @subpackage Contracts
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
interface ValidationSchemaInterface
{
    /**
     * Şema örneği oluşturur.
     *
     * @return static Yeni şema örneği
     */
    public static function make(): self;

    /**
     * String tipinde bir alan tanımlar.
     *
     * @param string|null $description Alan için açıklama
     * @return StringType String alan için şema tipi
     */
    public function string(?string $description = null): StringType;

    /**
     * Sayısal tipte bir alan tanımlar.
     *
     * @param string|null $description Alan için açıklama
     * @return NumberType Sayısal alan için şema tipi
     */
    public function number(?string $description = null): NumberType;

    /**
     * Boolean tipinde bir alan tanımlar.
     *
     * @param string|null $description Alan için açıklama
     * @return BooleanType Boolean alan için şema tipi
     */
    public function boolean(?string $description = null): BooleanType;

    /**
     * Tarih tipinde bir alan tanımlar.
     *
     * @param string|null $description Alan için açıklama
     * @return DateType Tarih alanı için şema tipi
     */
    public function date(?string $description = null): DateType;

    /**
     * Nesne tipinde bir alan tanımlar.
     *
     * @param string|null $description Alan için açıklama
     * @return ObjectType Nesne alanı için şema tipi
     */
    public function object(?string $description = null): ObjectType;

    /**
     * Dizi tipinde bir alan tanımlar.
     *
     * @param string|null $description Alan için açıklama
     * @return ArrayType Dizi alanı için şema tipi
     */
    public function array(?string $description = null): ArrayType;

    /**
     * Özel bir doğrulama kuralı ekler.
     *
     * @param string $name Kural adı
     * @param callable $rule Doğrulama fonksiyonu
     * @return self Mevcut şema örneği
     */
    public function addCustomRule(string $name, callable $rule): self;

    /**
     * Veriyi şemaya göre doğrular.
     *
     * @param array<string, mixed> $data Doğrulanacak veri
     * @return ValidationResult Doğrulama sonucu
     */
    public function validate(array $data): ValidationResult;

    /**
     * Şemayı standart doğrulama kuralları dizisine dönüştürür.
     *
     * @return array<string, array<string>> Doğrulama kuralları
     */
    public function toRulesArray(): array;

    /**
     * Nesne şeması tanımlar.
     *
     * @param array<string, mixed> $shape Nesne şema tanımları
     * @return self Mevcut şema örneği
     */
    public function shape(array $shape): self;
}