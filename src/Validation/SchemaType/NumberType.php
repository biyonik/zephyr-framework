<?php

declare(strict_types=1);

namespace Zephyr\Validation\SchemaType;

use Zephyr\Validation\ValidationResult;

/**
 * Sayısal tipte alan için şema sınıfı
 * @package Framework\Core\Validation
 * @subpackage SchemaType
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
class NumberType extends BaseType
{
    /**
     * Minimum değer
     *
     * @var float|null
     */
    private ?float $min = null;

    /**
     * Maksimum değer
     *
     * @var float|null
     */
    private ?float $max = null;

    /**
     * Tamsayı kontrolü
     *
     * @var bool
     */
    private bool $integer = false;

    /**
     * Minimum değer belirler
     *
     * @param float $value Minimum değer
     * @return $this Mevcut şema tipi
     */
    public function min(float $value): self
    {
        $this->min = $value;
        return $this;
    }

    /**
     * Maksimum değer belirler
     *
     * @param float $value Maksimum değer
     * @return $this Mevcut şema tipi
     */
    public function max(float $value): self
    {
        $this->max = $value;
        return $this;
    }

    /**
     * Tamsayı kontrolü ekler
     *
     * @return $this Mevcut şema tipi
     */
    public function integer(): self
    {
        $this->integer = true;
        return $this;
    }

    /**
     * Sayısal alanı doğrular
     *
     * @param string $field Alan adı
     * @param mixed $value Alan değeri
     * @param ValidationResult $result Doğrulama sonucu
     */
    public function validate(string $field, mixed $value, ValidationResult $result): void
    {
        // Zorunlu alan kontrolü
        if ($this->required && $value === null) {
            $result->addError($field, "{$field} alanı zorunludur");
            return;
        }

        // Null değerler için kontrolü atla
        if ($value === null) {
            return;
        }

        // Sayısal tip kontrolü
        if (!is_numeric($value)) {
            $result->addError($field, "{$field} alanı sayısal bir değer olmalıdır");
            return;
        }

        // Tamsayı kontrolü
        if ($this->integer && !is_int($value)) {
            $result->addError($field, "{$field} alanı tamsayı olmalıdır");
        }

        // Minimum değer kontrolü
        if ($this->min !== null && $value < $this->min) {
            $result->addError($field, "{$field} alanı {$this->min} değerinden küçük olamaz");
        }

        // Maksimum değer kontrolü
        if ($this->max !== null && $value > $this->max) {
            $result->addError($field, "{$field} alanı {$this->max} değerinden büyük olamaz");
        }
    }
}