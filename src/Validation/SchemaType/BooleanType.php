<?php

declare(strict_types=1);

namespace Zephyr\Validation\SchemaType;

use Zephyr\Validation\ValidationResult;

/**
 * Boolean tipte alan için şema sınıfı
 *
 * @package Framework\Core\Validation
 * @subpackage SchemaType
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
class BooleanType extends BaseType
{
    /**
     * Boolean alanı doğrular
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

        // Boolean tip kontrolü
        if (!is_bool($value)) {
            $result->addError($field, "{$field} alanı boolean tipinde olmalıdır");
        }
    }
}