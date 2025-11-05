<?php

declare(strict_types=1);

namespace Zephyr\Validation\SchemaType;

use Zephyr\Validation\ValidationResult;

/**
 * Tarih tipinde alan için şema sınıfı
 *
 * @package Framework\Core\Validation
 * @subpackage SchemaType
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
class DateType extends BaseType
{
    /**
     * En erken tarih
     *
     * @var string|null
     */
    private ?string $minDate = null;

    /**
     * En geç tarih
     *
     * @var string|null
     */
    private ?string $maxDate = null;

    /**
     * Tarih formatı
     *
     * @var string
     */
    private string $format = 'Y-m-d';

    /**
     * Minimum tarih belirler
     *
     * @param string $date Minimum tarih
     * @return $this Mevcut şema tipi
     */
    public function min(string $date): self
    {
        $this->minDate = $date;
        return $this;
    }

    /**
     * Maksimum tarih belirler
     *
     * @param string $date Maksimum tarih
     * @return $this Mevcut şema tipi
     */
    public function max(string $date): self
    {
        $this->maxDate = $date;
        return $this;
    }

    /**
     * Tarih formatını ayarlar
     *
     * @param string $format Tarih formatı
     * @return $this Mevcut şema tipi
     */
    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Tarih alanını doğrular
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

        // Tarih formatı kontrolü
        $parsedDate = \DateTime::createFromFormat($this->format, $value);
        if (!$parsedDate || $parsedDate->format($this->format) !== $value) {
            $result->addError($field, "{$field} alanı geçerli bir tarih olmalıdır. Format: {$this->format}");
            return;
        }

        // Minimum tarih kontrolü
        if ($this->minDate !== null) {
            $minDate = \DateTime::createFromFormat($this->format, $this->minDate);
            if ($parsedDate < $minDate) {
                $result->addError($field, "{$field} alanı {$this->minDate} tarihinden önce olamaz");
            }
        }

        // Maksimum tarih kontrolü
        if ($this->maxDate !== null) {
            $maxDate = \DateTime::createFromFormat($this->format, $this->maxDate);
            if ($parsedDate > $maxDate) {
                $result->addError($field, "{$field} alanı {$this->maxDate} tarihinden sonra olamaz");
            }
        }
    }
}