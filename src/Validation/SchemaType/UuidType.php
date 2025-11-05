<?php

declare(strict_types=1);

namespace Zephyr\Validation\SchemaType;

use Zephyr\Validation\Traits\UuidValidationTrait;
use Zephyr\Validation\ValidationResult;

/**
 * @package Framework\Core\Validation
 * @subpackage SchemaType
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
class UuidType extends BaseType
{
    use UuidValidationTrait;
    /**
     * UUID versiyonu
     *
     * @var int|null
     */
    private ?int $version = null;

    /**
     * Belirli bir UUID versiyonu seçer
     *
     * @param int $version UUID versiyonu
     * @return $this
     */
    public function version(int $version): self
    {
        $this->version = $version;
        return $this;
    }

    /**
     * UUID alanını doğrular
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

        // UUID formatı kontrolü
        $isValid = $this->version
            ? $this->isValidUuid($value, $this->version)
            : $this->isValidUuid($value);

        if (!$isValid) {
            $versionText = $this->version ? " (v{$this->version})" : '';
            $result->addError($field, "{$field} alanı geçerli bir UUID{$versionText} olmalıdır");
        }
    }
}