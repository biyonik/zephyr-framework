<?php

declare(strict_types=1);

namespace Zephyr\Validation\SchemaType;

use Zephyr\Validation\ValidationResult;

/**
 * Dizi tipinde alan için şema sınıfı
 *
 * @package Framework\Core\Validation
 * @subpackage SchemaType
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
class ArrayType extends BaseType
{
    /**
     * Minimum eleman sayısı
     *
     * @var int|null
     */
    private ?int $minLength = null;

    /**
     * Maksimum eleman sayısı
     *
     * @var int|null
     */
    private ?int $maxLength = null;

    /**
     * Dizi elemanları için şema
     *
     * @var BaseType|null
     */
    private ?BaseType $elementSchema = null;

    /**
     * Minimum eleman sayısı belirler
     *
     * @param int $length Minimum eleman sayısı
     * @return $this Mevcut şema tipi
     */
    public function min(int $length): self
    {
        $this->minLength = $length;
        return $this;
    }

    /**
     * Maksimum eleman sayısı belirler
     *
     * @param int $length Maksimum eleman sayısı
     * @return $this Mevcut şema tipi
     */
    public function max(int $length): self
    {
        $this->maxLength = $length;
        return $this;
    }

    /**
     * Dizi elemanları için şema tanımlar
     *
     * @param BaseType $schema Eleman şeması
     * @return $this Mevcut şema tipi
     */
    public function elements(BaseType $schema): self
    {
        $this->elementSchema = $schema;
        return $this;
    }

    /**
     * Dizi alanını doğrular
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

        // Dizi tip kontrolü
        if (!is_array($value)) {
            $result->addError($field, "{$field} alanı dizi tipinde olmalıdır");
            return;
        }

        // Minimum eleman sayısı kontrolü
        if ($this->minLength !== null && count($value) < $this->minLength) {
            $result->addError($field, "{$field} alanında en az {$this->minLength} eleman olmalıdır");
        }

        // Maksimum eleman sayısı kontrolü
        if ($this->maxLength !== null && count($value) > $this->maxLength) {
            $result->addError($field, "{$field} alanında en fazla {$this->maxLength} eleman olmalıdır");
        }

        // Eleman şeması varsa her bir eleman için doğrulama
        if ($this->elementSchema !== null) {
            foreach ($value as $index => $element) {
                $this->elementSchema->validate("{$field}[{$index}]", $element, $result);
            }
        }
    }
}