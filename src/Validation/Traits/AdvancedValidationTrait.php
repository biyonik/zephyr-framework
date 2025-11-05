<?php

namespace Zephyr\Validation\Traits;

use Zephyr\Validation\ValidationResult;
use Zephyr\Validation\ValidationSchema;

/**
 * Gelişmiş Doğrulama Teknikleri için Trait
 *
 * @package Framework\Core\Validation
 * @subpackage Traits
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
trait AdvancedValidationTrait
{
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
     * Koşullu doğrulama kuralı ekler
     *
     * @param string $field Kontrol edilecek alan
     * @param mixed $expectedValue Beklenen değer
     * @param callable $callback Doğrulama geri çağırım fonksiyonu
     * @return AdvancedValidationTrait|ValidationSchema
     */
    public function when(string $field, mixed $expectedValue, callable $callback): self
    {
        $this->conditionalRules[] = [
            'field' => $field,
            'expectedValue' => $expectedValue,
            'callback' => $callback
        ];
        return $this;
    }

    /**
     * Çapraz alan doğrulama kuralı ekler
     *
     * @param callable $validator Çapraz alan doğrulama fonksiyonu
     * @return AdvancedValidationTrait|ValidationSchema
     */
    public function crossValidate(callable $validator): self
    {
        $this->crossValidators[] = $validator;
        return $this;
    }

    /**
     * Koşullu ve çapraz alan doğrulamalarını uygular
     *
     * @param array<string, mixed> $data Doğrulanacak veri
     * @param ValidationResult $result Doğrulama sonucu
     * @return void
     */
    protected function applyAdvancedValidations(array $data, ValidationResult $result): void
    {
        $this->applyConditionalValidations($data, $result);
        $this->applyCrossFieldValidations($data, $result);
    }

    /**
     * Koşullu doğrulamaları uygular
     *
     * @param array<string, mixed> $data Doğrulanacak veri
     * @param ValidationResult $result Doğrulama sonucu
     * @return void
     */
    private function applyConditionalValidations(array $data, ValidationResult $result): void
    {
        // Koşullu doğrulamalar
        foreach ($this->conditionalRules as $rule) {
            $field = $rule['field'];
            $expectedValue = $rule['expectedValue'];
            $callback = $rule['callback'];

            // Alan değeri beklenen değerle eşleşiyorsa
            if (isset($data[$field]) && $data[$field] === $expectedValue) {
                // Callback fonksiyonunu çağır
                $schema = clone $this;
                $callbackResult = $callback($schema);

                // Her bir alan için doğrulama
                if ($callbackResult instanceof self && property_exists($this, 'schema')) {
                    foreach ($this->schema as $schemaField => $schemaRule) {
                        $value = $data[$schemaField] ?? null;
                        $schemaRule->validate($schemaField, $value, $result);
                    }
                }
            }
        }
    }

    /**
     * Çapraz alan doğrulamalarını uygular
     *
     * @param array<string, mixed> $data Doğrulanacak veri
     * @param ValidationResult $result Doğrulama sonucu
     * @return void
     */
    private function applyCrossFieldValidations(array $data, ValidationResult $result): void
    {
        // Çapraz alan doğrulamaları
        foreach ($this->crossValidators as $validator) {
            try {
                // Çapraz alan doğrulama fonksiyonunu çağır
                $validator($data);
            } catch (\Throwable $e) {
                // Doğrulama hatası durumunda
                $result->addError('_cross_validation', $e->getMessage());
            }
        }
    }
}