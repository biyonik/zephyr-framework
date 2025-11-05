<?php

declare(strict_types=1);

namespace Zephyr\Validation\SchemaType;

use Zephyr\Validation\Traits\AdvancedStringValidationTrait;
/*
 * @package Framework\Core\Validation
 * @subpackage SchemaType
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
class AdvancedStringType extends StringType
{
    use AdvancedStringValidationTrait;
    /**
     * Türkçe karakter kontrolü
     *
     * @var bool|null
     */
    private ?bool $turkishChars = null;

    /**
     * Domain kontrolü
     *
     * @var bool|null
     */
    private ?bool $domainCheck = null;

    /**
     * Türkçe karakter kontrolünü ayarlar
     *
     * @param bool $allow Türkçe karakterlere izin verilsin mi?
     * @return $this
     */
    public function turkishChars(bool $allow = true): self
    {
        $this->turkishChars = $allow;
        return $this;
    }

    /**
     * Domain kontrolünü ayarlar
     *
     * @param bool $strict Sıkı domain kontrolü
     * @return $this
     */
    public function domain(bool $strict = true): self
    {
        $this->domainCheck = $strict;
        return $this;
    }

    /**
     * Gelişmiş string alanını doğrular
     *
     * @param string $field Alan adı
     * @param mixed $value Alan değeri
     * @param \Zephyr\Validation\ValidationResult $result Doğrulama sonucu
     */
    public function validate(string $field, mixed $value, \Zephyr\Validation\ValidationResult $result): void
    {
        // Önce temel string doğrulamalarını çalıştır
        parent::validate($field, $value, $result);

        // Eğer zaten hata varsa devam etme
        if ($result->hasError($field)) {
            return;
        }

        // Null değerler için kontrolü atla
        if ($value === null) {
            return;
        }

        // Türkçe karakter kontrolü
        if ($this->turkishChars !== null) {
            $hasTurkishChars = $this->hasTurkishChars($value);
            if ($this->turkishChars && !$hasTurkishChars) {
                $result->addError($field, "{$field} alanında Türkçe karakter bulunmalıdır");
            } elseif (!$this->turkishChars && $hasTurkishChars) {
                $result->addError($field, "{$field} alanında Türkçe karakter bulunmamalıdır");
            }
        }

        // Domain kontrolü
        if ($this->domainCheck !== null) {
            $isDomain = $this->isValidDomain($value, $this->domainCheck);
            if (!$isDomain) {
                $result->addError($field, "{$field} alanı geçerli bir alan adı olmalıdır");
            }
        }
    }
}