<?php

declare(strict_types=1);

namespace Zephyr\Validation\SchemaType;

use Zephyr\Validation\ValidationResult;

/**
 * Nesne tipinde alan için şema sınıfı
 * @package Framework\Core\Validation
 * @subpackage SchemaType
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
class ObjectType extends BaseType
{
    /**
     * Nesne şema tanımları
     *
     * @var array
     */
    private array $shape = [];

    /**
     * Nesne şeması tanımlar
     *
     * @param array $shape Nesne şema tanımları
     * @return $this Mevcut şema tipi
     */
    public function shape(array $shape): self
    {
        $this->shape = $shape;
        return $this;
    }

    /**
     * Nesne alanını doğrular
     *
     * @param string $field Alan adı
     * @param mixed $value Alan değeri
     * @param ValidationResult $result Doğrulama sonuç
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

        // Nesne tip kontrolü
        if (!is_array($value)) {
            $result->addError($field, "{$field} alanı nesne (dizi) tipinde olmalıdır");
            return;
        }

        // Her bir alt alan için doğrulama
        foreach ($this->shape as $subField => $subSchema) {
            $subValue = $value[$subField] ?? null;
            $subSchema->validate("{$field}.{$subField}", $subValue, $result);
        }
    }
}