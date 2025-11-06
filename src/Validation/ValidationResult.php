<?php

declare(strict_types=1);

namespace Zephyr\Validation;

use Zephyr\Validation\Contracts\ValidationResultInterface;

/**
 * Doğrulama sonuçlarını yönetir.
 *
 * @package Framework\Core\Validation
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
class ValidationResult implements ValidationResultInterface
{
    /**
     * Doğrulama hataları.
     *
     * @var array<string, array<string>>
     */
    private array $errors = [];

    /**
     * Doğrulanmış veri.
     *
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Constructor.
     *
     * @param array<string, mixed> $data Doğrulanacak veri
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function getValidData(): array
    {
        return $this->data;
    }

    public function setValidData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * {@inheritdoc}
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * {@inheritdoc}
     */
    public function getFirstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0] ?? null;
        }
        return null;
    }

    /**
     * Hata mesajlarını tek diziye dönüştürür.
     *
     * @return array<string> Tüm hata mesajları
     */
    public function getFlattenedErrors(): array
    {
        $flattened = [];

        foreach ($this->errors as $fieldErrors) {
            foreach ($fieldErrors as $message) {
                $flattened[] = $message;
            }
        }

        return $flattened;
    }

    /**
     * Belirli alanları içeren hataları döndürür.
     *
     * @param array<string> $fields Alan adları
     * @return array<string, array<string>> Belirli alanlar için hatalar
     */
    public function getErrorsForFields(array $fields): array
    {
        $filteredErrors = [];

        foreach ($fields as $field) {
            if (isset($this->errors[$field])) {
                $filteredErrors[$field] = $this->errors[$field];
            }
        }

        return $filteredErrors;
    }
}