<?php

declare(strict_types=1);

namespace Zephyr\Validation\SchemaType;

use Zephyr\Validation\Contracts\ValidationTypeInterface;
use Zephyr\Validation\ValidationResult;

/**
 * Temel şema tipi için abstract sınıf.
 *
 * @package Framework\Core\Validation
 * @subpackage SchemaType
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
abstract class BaseType implements ValidationTypeInterface
{
    /**
     * Alan açıklaması.
     *
     * @var string|null
     */
    protected ?string $description = null;

    /**
     * Alan etiketi.
     *
     * @var string|null
     */
    protected ?string $label = null;

    /**
     * Zorunlu alan kontrolü.
     *
     * @var bool
     */
    protected bool $required = false;

    /**
     * Varsayılan değer.
     *
     * @var mixed
     */
    protected mixed $defaultValue = null;

    /**
     * Özel hata mesajları.
     *
     * @var array<string, string>
     */
    protected array $errorMessages = [];

    /**
     * Kurulum yapıcı metot.
     *
     * @param string|null $description Alan açıklaması
     */
    public function __construct(?string $description = null)
    {
        $this->description = $description;
    }

    /**
     * {@inheritdoc}
     */
    public function required(): self
    {
        $this->required = true;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function default(mixed $value): self
    {
        $this->defaultValue = $value;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function errorMessage(string $rule, string $message): self
    {
        $this->errorMessages[$rule] = $message;
        return $this;
    }

    /**
     * Belirli bir kural için hata mesajını döndürür.
     *
     * @param string $rule Kural adı
     * @param array<string, mixed> $params Hata mesajı parametreleri
     * @return string Hata mesajı
     */
    protected function getErrorMessage(string $rule, array $params = []): string
    {
        $message = $this->errorMessages[$rule] ?? $this->getDefaultErrorMessage($rule);

        // Parametreleri mesaja ekle
        foreach ($params as $key => $value) {
            $message = str_replace(':' . $key, (string) $value, $message);
        }

        return $message;
    }

    /**
     * Alan için tanımlanmış özel hata mesajlarını alır.
     *
     * @param string $field Alan adı (örn: 'email')
     * @return array<string, string> (örn: ['email.required' => 'Hata mesajı'])
     */
    public function getErrorMessages(string $field): array
    {
        $formattedMessages = [];
        // $this->errorMessages, 'errorMessage()' metoduyla doldurulur
        foreach ($this->errorMessages as $rule => $message) {
            $formattedKey = $field . '.' . $rule;
            $formattedMessages[$formattedKey] = $message;
        }
        return $formattedMessages;
    }

    /**
     * Kural için varsayılan hata mesajını döndürür.
     *
     * @param string $rule Kural adı
     * @return string Varsayılan hata mesajı
     */
    protected function getDefaultErrorMessage(string $rule): string
    {
        $messages = [
            'required' => ':field alanı zorunludur',
            'type' => ':field alanı geçerli tipte değil',
            // Alt sınıflar kendi varsayılan mesajlarını ekleyebilir
        ];

        return $messages[$rule] ?? ':field alanı geçersiz';
    }

    /**
     * {@inheritdoc}
     */
    abstract public function validate(string $field, mixed $value, ValidationResult $result): void;

    /**
     * {@inheritdoc}
     */
    public function toRulesArray(): array
    {
        $rules = [];

        if ($this->required) {
            $rules[] = 'required';
        }

        // Alt sınıflar kendi kurallarını ekleyebilir

        return $rules;
    }

    /**
     * Alan adı veya etiketini döndürür.
     *
     * @param string $field Alan adı
     * @return string Alan etiketi
     */
    protected function getFieldLabel(string $field): string
    {
        return $this->label ?? $field;
    }
}