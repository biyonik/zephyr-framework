<?php

declare(strict_types=1);

namespace Zephyr\Database\Concerns;

use DateTime;
use DateTimeInterface;

/**
 * Has Attributes Trait
 *
 * Provides attribute get/set functionality with:
 * - Type casting
 * - Accessor/Mutator methods
 * - JSON attribute handling
 * - Date attribute handling
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
trait HasAttributes
{
    /**
     * Get an attribute value
     *
     * @param string $key Attribute key
     * @return mixed
     */
    public function getAttribute(string $key): mixed
    {
        // 1. Check if attribute exists
        if (!array_key_exists($key, $this->attributes)) {
            return null;
        }

        $value = $this->attributes[$key];

        // 2. Check for accessor method (getUserNameAttribute)
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // 3. Cast attribute value
        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        // 4. Return raw value
        return $value;
    }

    /**
     * Set an attribute value
     *
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     * @return self
     */
    public function setAttribute(string $key, mixed $value): self
    {
        // 1. Check for mutator method (setUserNameAttribute)
        if ($this->hasSetMutator($key)) {
            return $this->setMutatedAttributeValue($key, $value);
        }

        // 2. Cast value before setting
        if ($this->hasCast($key)) {
            $value = $this->castAttributeForStorage($key, $value);
        }

        // 3. Set attribute
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Check if accessor method exists
     *
     * @example getUserNameAttribute() for 'user_name'
     */
    protected function hasGetMutator(string $key): bool
    {
        $method = 'get' . $this->studly($key) . 'Attribute';
        return method_exists($this, $method);
    }

    /**
     * Check if mutator method exists
     *
     * @example setUserNameAttribute($value) for 'user_name'
     */
    protected function hasSetMutator(string $key): bool
    {
        $method = 'set' . $this->studly($key) . 'Attribute';
        return method_exists($this, $method);
    }

    /**
     * Get attribute value using accessor
     */
    protected function mutateAttribute(string $key, mixed $value): mixed
    {
        $method = 'get' . $this->studly($key) . 'Attribute';
        return $this->$method($value);
    }

    /**
     * Set attribute value using mutator
     */
    protected function setMutatedAttributeValue(string $key, mixed $value): self
    {
        $method = 'set' . $this->studly($key) . 'Attribute';
        $this->$method($value);
        return $this;
    }

    /**
     * Check if attribute has cast
     */
    protected function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->casts);
    }

    /**
     * Get cast type for attribute
     */
    protected function getCastType(string $key): ?string
    {
        return $this->casts[$key] ?? null;
    }

    /**
     * Cast attribute to native type
     *
     * @param string $key Attribute key
     * @param mixed $value Raw value from database
     * @return mixed Casted value
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        if (is_null($value)) {
            return null;
        }

        $castType = $this->getCastType($key);

        return match ($castType) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json' => $this->fromJson($value),
            'object' => (object) $this->fromJson($value),
            'collection' => collect($this->fromJson($value)),
            'date' => $this->asDate($value),
            'datetime' => $this->asDateTime($value),
            'timestamp' => $this->asTimestamp($value),
            default => $value,
        };
    }

    /**
     * Cast attribute for storage in database
     *
     * @param string $key Attribute key
     * @param mixed $value Value to store
     * @return mixed Value ready for database
     */
    protected function castAttributeForStorage(string $key, mixed $value): mixed
    {
        if (is_null($value)) {
            return null;
        }

        $castType = $this->getCastType($key);

        return match ($castType) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (int) $value, // Store as 0/1
            'array', 'json', 'object', 'collection' => $this->asJson($value),
            'date' => $this->fromDateTime($value),
            'datetime' => $this->fromDateTime($value),
            'timestamp' => $this->fromDateTime($value),
            default => $value,
        };
    }

    /**
     * Decode JSON string to array
     */
    protected function fromJson(string $value): array
    {
        return json_decode($value, true) ?? [];
    }

    /**
     * Encode value to JSON string
     */
    protected function asJson(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value);
    }

    /**
     * Convert value to DateTime instance
     */
    protected function asDateTime(mixed $value): ?DateTime
    {
        if ($value instanceof DateTimeInterface) {
            return DateTime::createFromInterface($value);
        }

        if (is_numeric($value)) {
            return (new DateTime)->setTimestamp((int) $value);
        }

        if (is_string($value)) {
            return new DateTime($value);
        }

        return null;
    }

    /**
     * Convert value to date string (Y-m-d)
     */
    protected function asDate(mixed $value): ?string
    {
        return $this->asDateTime($value)?->format('Y-m-d');
    }

    /**
     * Convert value to timestamp
     */
    protected function asTimestamp(mixed $value): ?int
    {
        return $this->asDateTime($value)?->getTimestamp();
    }

    /**
     * Convert DateTime to string for storage
     */
    protected function fromDateTime(mixed $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_numeric($value)) {
            return (new DateTime)->setTimestamp((int) $value)->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            return (new DateTime($value))->format('Y-m-d H:i:s');
        }

        return null;
    }

    /**
     * Get all attributes as array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set all attributes at once
     */
    public function setRawAttributes(array $attributes, bool $sync = false): self
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * Get original attribute value
     */
    public function getOriginal(?string $key = null): mixed
    {
        if (is_null($key)) {
            return $this->original;
        }

        return $this->original[$key] ?? null;
    }

    /**
     * Get only specified attributes
     */
    public function only(array $attributes): array
    {
        return array_intersect_key($this->attributes, array_flip($attributes));
    }

    /**
     * Convert attributes to array
     */
    protected function attributesToArray(): array
    {
        $attributes = [];

        foreach ($this->attributes as $key => $value) {
            $attributes[$key] = $this->getAttribute($key);
        }

        return $attributes;
    }

    /**
     * Convert snake_case to StudlyCase
     */
    protected function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }
}