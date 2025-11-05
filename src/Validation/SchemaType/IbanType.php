<?php

declare(strict_types=1);

namespace Zephyr\Validation\SchemaType;

use Zephyr\Validation\Traits\PaymentValidationTrait;
use Zephyr\Validation\ValidationResult;

/**
 * @package Framework\Core\Validation
 * @subpackage SchemaType
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
class IbanType extends BaseType
{
    use PaymentValidationTrait;

    /**
     * Ülke kodu
     *
     * @var string|null
     */
    private ?string $countryCode = null;

    /**
     * Belirli bir ülke kodu seçer
     *
     * @param string $code Ülke kodu
     * @return $this
     */
    public function country(string $code): self
    {
        $this->countryCode = $code;
        return $this;
    }

    /**
     * IBAN alanını doğrular
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

        // IBAN formatı kontrolü
        $isValid = $this->countryCode
            ? $this->isValidIban($value, $this->countryCode)
            : $this->isValidIban($value);

        if (!$isValid) {
            $countryText = $this->countryCode ? " ({$this->countryCode})" : '';
            $result->addError($field, "{$field} alanı geçerli bir IBAN{$countryText} olmalıdır");
        }
    }
}