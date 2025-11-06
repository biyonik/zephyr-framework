<?php

declare(strict_types=1);

namespace Zephyr\Validation;

use Zephyr\Validation\Contracts\ValidationSchemaInterface;
use Zephyr\Validation\SchemaType\BaseType;
use Zephyr\Validation\SchemaType\StringType;
use Zephyr\Validation\SchemaType\NumberType;
use Zephyr\Validation\SchemaType\BooleanType;
use Zephyr\Validation\SchemaType\DateType;
use Zephyr\Validation\SchemaType\ArrayType;
use Zephyr\Validation\SchemaType\ObjectType;
use Zephyr\Validation\SchemaType\UuidType;
use Zephyr\Validation\SchemaType\AdvancedStringType;
use Zephyr\Validation\SchemaType\CreditCardType;
use Zephyr\Validation\SchemaType\IbanType;
use Zephyr\Validation\Traits\AdvancedValidationTrait;

/**
 * PHP için Zod benzeri bir şema doğrulama sınıfı.
 *
 * @package Framework\Core\Validation
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
class ValidationSchema implements ValidationSchemaInterface
{
    use AdvancedValidationTrait;

    /**
     * Şema tanımları için özel tip.
     *
     * @var array<string, BaseType>
     */
    private array $schema = [];

    /**
     * Özel doğrulama kuralları.
     *
     * @var array<string, callable>
     */
    private array $customRules = [];

    /**
     * Koşullu doğrulama kuralları
     *
     * @var array<array{field: string, expectedValue: mixed, callback: callable}>
     */
    protected array $conditionalRules = [];

    /**
     * Çapraz alan doğrulama kuralları
     *
     * @var array<callable>
     */
    protected array $crossValidators = [];

    /**
     * {@inheritdoc}
     */
    public static function make(): self
    {
        return new static();
    }

    /**
     * {@inheritdoc}
     */
    public function string(?string $description = null): StringType
    {
        $stringType = new StringType($description);
        return $stringType;
    }

    /**
     * {@inheritdoc}
     */
    public function number(?string $description = null): NumberType
    {
        $numberType = new NumberType($description);
        return $numberType;
    }

    /**
     * {@inheritdoc}
     */
    public function boolean(?string $description = null): BooleanType
    {
        $booleanType = new BooleanType($description);
        return $booleanType;
    }

    /**
     * {@inheritdoc}
     */
    public function date(?string $description = null): DateType
    {
        $dateType = new DateType($description);
        return $dateType;
    }

    /**
     * {@inheritdoc}
     */
    public function object(?string $description = null): ObjectType
    {
        $objectType = new ObjectType($description);
        return $objectType;
    }

    /**
     * {@inheritdoc}
     */
    public function array(?string $description = null): ArrayType
    {
        $arrayType = new ArrayType($description);
        return $arrayType;
    }

    /**
     * UUID alanı ekler.
     *
     * @param string|null $description Alan açıklaması
     * @return UuidType UUID alan tipi
     */
    public function uuid(?string $description = null): UuidType
    {
        $uuidType = new UuidType($description);
        return $uuidType;
    }

    /**
     * Kredi kartı alanı ekler.
     *
     * @param string|null $description Alan açıklaması
     * @return CreditCardType Kredi kartı alan tipi
     */
    public function creditCard(?string $description = null): CreditCardType
    {
        $creditCardType = new CreditCardType($description);
        return $creditCardType;
    }

    /**
     * IBAN alanı ekler.
     *
     * @param string|null $description Alan açıklaması
     * @return IbanType IBAN alan tipi
     */
    public function iban(?string $description = null): IbanType
    {
        $ibanType = new IbanType($description);
        return $ibanType;
    }

    /**
     * Gelişmiş string doğrulama için alan ekler.
     *
     * @param string|null $description Alan açıklaması
     * @return AdvancedStringType Gelişmiş string alan tipi
     */
    public function advancedString(?string $description = null): AdvancedStringType
    {
        $advancedStringType = new AdvancedStringType($description);
        return $advancedStringType;
    }

    /**
     * {@inheritdoc}
     */
    public function addCustomRule(string $name, callable $rule): self
    {
        $this->customRules[$name] = $rule;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $data): ValidationResult
    {
        $result = new ValidationResult($data);
        $validatedData = $data; // Doğrulanmış ve temizlenmiş veriyi burada toplayalım

        foreach ($this->schema as $field => $rules) {
            $value = $data[$field] ?? null;

            // 1. Doğrula (validate metodu $result'ı hatalarla doldurur)
            $rules->validate($field, $value, $result);

            // 2. Dönüştür (Eğer hata yoksa veya henüz hata oluşmamışsa)
            if ($value !== null && !$result->hasError($field)) {
                // BaseType'a eklediğimiz yeni metodu çağır
                $validatedData[$field] = $rules->applyTransformations($value);
            }
        }

        // Gelişmiş doğrulamaları uygula (bunlar hata ekler, dönüşüm yapmaz)
        $this->applyAdvancedValidations($data, $result);

        // 3. ValidationResult'ı güncellenmiş/temizlenmiş veriyle güncelle
        // (Bu, ValidationResult sınıfına bir 'setValidData' metodu eklemeyi gerektirebilir)
        // Veya en başından $result'ı $validatedData ile başlatalım...

        // --- DAHA TEMİZ YAKLAŞIM ---
        $originalData = $data;
        $transformedData = $data;
        $result = new ValidationResult($originalData);

        foreach ($this->schema as $field => $rules) {
            $value = $originalData[$field] ?? null;

            // 1. Değeri önce dönüştür (temizle)
            if ($value !== null) {
                $transformedValue = $rules->applyTransformations($value);
                $transformedData[$field] = $transformedValue;
            } else {
                $transformedValue = null;
            }

            // 2. Temizlenmiş değeri doğrula
            $rules->validate($field, $transformedValue, $result);
        }

        $this->applyAdvancedValidations($transformedData, $result);

        // Hata yoksa, ValidationResult'a temizlenmiş veriyi set et
        if (!$result->hasErrors()) {
            $result->setValidData($transformedData); // <-- Bu metodu eklememiz lazım
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function toRulesArray(): array
    {
        $rules = [];

        foreach ($this->schema as $field => $type) {
            $rules[$field] = $type->toRulesArray();
        }

        return $rules;
    }

    /**
     * Hata mesajlarını diziye dönüştürür.
     *
     * @return array<string, array<string, string>> Hata mesajları
     */
    public function getErrorMessages(): array
    {
        $messages = [];

        foreach ($this->schema as $field => $type) {
            // getErrorMessages metodu varsa çağır, yoksa boş dizi kullan
            if (method_exists($type, 'getErrorMessages')) {
                $fieldMessages = $type->getErrorMessages($field);
                if (is_array($fieldMessages)) {
                    $messages = array_merge($messages, $fieldMessages);
                }
            }
        }

        return $messages;
    }

    /**
     * {@inheritdoc}
     */
    public function shape(array $shape): self
    {
        $this->schema = $shape;
        return $this;
    }

    /**
     * Nesne klonlama desteği.
     * (GÜNCELLENDİ - Rapor #2: Deep Clone Problemi)
     *
     * @return void
     */
    public function __clone()
    {
        // 1. 'schema' dizisindeki BaseType nesnelerini derin kopyala (Bu zaten doğruydu)
        $clonedSchema = array_map(function ($rule) {
            return clone $rule; //
        }, $this->schema);
        $this->schema = $clonedSchema;

        // 2. 'conditionalRules' dizisini derin kopyala (Rapor #2 Çözümü)
        // Klon ve orijinalin aynı diziyi paylaşmasını engellemek için
        // array_map ile diziyi yeniden oluşturuyoruz.
        $this->conditionalRules = array_map(function ($rule) {
            // İçerideki diziyi de yeniden oluşturarak bağımsız olmasını garantile
            return [
                'field' => $rule['field'],
                'expectedValue' => $rule['expectedValue'],
                'callback' => $rule['callback'] // Closure'lar referans olarak kalır, bu normaldir
            ];
        }, $this->conditionalRules); //

        // 3. 'crossValidators' dizisini derin kopyala (Rapor #2 Çözümü)
        $this->crossValidators = array_map(function ($validator) {
            return $validator; // Closure'lar referans olarak kalır, bu normaldir
        }, $this->crossValidators); //
    }

    /**
     * Command/Query validasyon için formatlanmış kurallar.
     *
     * @return array<string, string> Doğrulama kuralları
     */
    public function toCQRSRules(): array
    {
        $rules = [];

        foreach ($this->toRulesArray() as $field => $fieldRules) {
            $rules[$field] = is_array($fieldRules) ? implode('|', $fieldRules) : $fieldRules;
        }

        return $rules;
    }

    /**
     * Şema tanımını diziye dönüştürür.
     *
     * @return array<string, mixed> Şema tanımı
     */
    public function toArray(): array
    {
        $schema = [];

        foreach ($this->schema as $field => $type) {
            $schema[$field] = [
                'type' => get_class($type),
                'rules' => $type->toRulesArray()
            ];
        }

        return $schema;
    }
}