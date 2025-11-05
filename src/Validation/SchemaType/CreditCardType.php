<?php

declare(strict_types=1);

namespace Zephyr\Validation\SchemaType;

use Zephyr\Validation\Traits\PaymentValidationTrait;
use Zephyr\Validation\ValidationResult;

/*
 * @package Framework\Core\Validation
 * @subpackage SchemaType
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
class CreditCardType extends BaseType
{
    use PaymentValidationTrait;

    /**
     * Desteklenen kart tipleri
     *
     * @var string|null
     */
    private ?string $cardType = null;

    /**
     * Belirli bir kart tipi seçer
     *
     * @param string $type Kart tipi (visa, mastercard, vb.)
     * @return $this
     */
    public function type(string $type): self
    {
        $this->cardType = $type;
        return $this;
    }

    /**
     * Kredi kartı alanını doğrular
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

        // Kredi kartı formatı kontrolü
        $isValid = $this->cardType
            ? $this->isValidCreditCard($value, $this->cardType)
            : $this->isValidCreditCard($value);

        if (!$isValid) {
            $typeText = $this->cardType ? " ({$this->cardType})" : '';
            $result->addError($field, "{$field} alanı geçerli bir kredi kartı numarası{$typeText} olmalıdır");
        }
    }
}