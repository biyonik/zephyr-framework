<?php

declare(strict_types=1);

namespace Zephyr\Database\Concerns;

use DateTime;
use DateTimeInterface;
use Zephyr\Support\Collection;

/**
 * Has Attributes Trait
 *
 * Model attribute'larını yönetir:
 * - Getter/Setter metotları
 * - Type casting (int, bool, json, date vs.)
 * - Accessor/Mutator desteği
 * - JSON attribute handling
 * - Date attribute handling
 * - Attribute önbellekleme
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
trait HasAttributes
{
    /**
     * Hesaplanmış attribute'lar için önbellek
     */
    protected array $attributeCache = [];

    /**
     * Attribute değerini döndürür
     * 
     * Arama sırası:
     * 1. Önbellek
     * 2. Yüklenmiş ilişkiler
     * 3. Database attribute'ları (cast + mutator ile)
     * 4. İlişki metotları (lazy loading)
     * 5. Sanal accessor'lar
     */
    public function getAttribute(string $key): mixed
    {
        if (!$key) {
            return null;
        }

        // 1. Önbellek kontrolü
        if ($this->hasCachedAttribute($key)) {
            return $this->getCachedAttribute($key);
        }

        // 2. Yüklenmiş ilişkiler
        if ($this->hasLoadedRelation($key)) {
            return $this->getLoadedRelation($key);
        }

        // 3. Database sütunu
        if ($this->hasAttribute($key)) {
            return $this->getAttributeValue($key);
        }

        // 4. İlişki metodu (lazy loading)
        if ($this->hasRelationMethod($key)) {
            return $this->getRelationValue($key);
        }

        // 5. Sanal accessor (DB sütunu olmayan)
        if ($this->hasGetMutator($key)) {
            return $this->getMutatedAttributeValue($key, null);
        }

        return null;
    }

    /**
     * Attribute değerini işlenmiş halde döndürür
     */
    protected function getAttributeValue(string $key): mixed
    {
        $value = $this->attributes[$key];

        // Accessor varsa kullan
        if ($this->hasGetMutator($key)) {
            return $this->getMutatedAttributeValue($key, $value);
        }

        // Cast varsa kullan
        if ($this->hasCast($key)) {
            return $this->getCastedAttributeValue($key, $value);
        }

        return $value;
    }

    /**
     * Mutator ile attribute değerini döndürür ve cache'ler
     */
    protected function getMutatedAttributeValue(string $key, mixed $value): mixed
    {
        $mutatedValue = $this->mutateAttribute($key, $value);
        $this->cacheAttribute($key, $mutatedValue);
        
        return $mutatedValue;
    }

    /**
     * Cast ile attribute değerini döndürür ve cache'ler
     */
    protected function getCastedAttributeValue(string $key, mixed $value): mixed
    {
        $castedValue = $this->castAttribute($key, $value);
        $this->cacheAttribute($key, $castedValue);
        
        return $castedValue;
    }

    /**
     * Attribute değerini set eder
     */
    public function setAttribute(string $key, mixed $value): self
    {
        // Önbelleği temizle
        $this->clearAttributeFromCache($key);

        // Mutator kontrolü
        if ($this->hasSetMutator($key)) {
            return $this->setMutatedAttributeValue($key, $value);
        }

        // Cast uygula
        if ($this->hasCast($key)) {
            $value = $this->castAttributeForStorage($key, $value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Attribute'un DB'de var olup olmadığını kontrol eder
     */
    protected function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Accessor metodu var mı?
     */
    protected function hasGetMutator(string $key): bool
    {
        $method = 'get' . $this->studly($key) . 'Attribute';
        return method_exists($this, $method);
    }

    /**
     * Mutator metodu var mı?
     */
    protected function hasSetMutator(string $key): bool
    {
        $method = 'set' . $this->studly($key) . 'Attribute';
        return method_exists($this, $method);
    }

    /**
     * Accessor kullanarak attribute değerini döndürür
     */
    protected function mutateAttribute(string $key, mixed $value): mixed
    {
        $method = 'get' . $this->studly($key) . 'Attribute';
        return $this->$method($value);
    }

    /**
     * Mutator kullanarak attribute değerini set eder
     */
    protected function setMutatedAttributeValue(string $key, mixed $value): self
    {
        $method = 'set' . $this->studly($key) . 'Attribute';
        $this->$method($value);
        return $this;
    }

    /**
     * Attribute'un cast'i var mı?
     */
    protected function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->casts);
    }

    /**
     * Cast tipini döndürür
     */
    protected function getCastType(string $key): ?string
    {
        return $this->casts[$key] ?? null;
    }

    /**
     * Attribute'u belirtilen tipe cast eder (database → PHP)
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
            'collection' => new Collection($this->fromJson($value)),
            'date' => $this->asDate($value),
            'datetime' => $this->asDateTime($value),
            'timestamp' => $this->asTimestamp($value),
            default => $value,
        };
    }

    /**
     * Attribute'u database için cast eder (PHP → database)
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
            'bool', 'boolean' => (int) $value,
            'array', 'json', 'object', 'collection' => $this->asJson($value),
            'date', 'timestamp', 'datetime' => $this->fromDateTime($value),
            default => $value,
        };
    }

    /**
     * JSON string'ini array'e çevirir
     */
    protected function fromJson(string $value): array
    {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR) ?? [];
        } catch (\JsonException $e) {
            return [];
        }
    }

    /**
     * Değeri JSON string'e çevirir
     */
    protected function asJson(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof Collection) {
            $value = $value->all();
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * Değeri DateTime instance'ına çevirir
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
     * Değeri date string'e çevirir (Y-m-d)
     */
    protected function asDate(mixed $value): ?string
    {
        return $this->asDateTime($value)?->format('Y-m-d');
    }

    /**
     * Değeri timestamp'e çevirir
     */
    protected function asTimestamp(mixed $value): ?int
    {
        return $this->asDateTime($value)?->getTimestamp();
    }

    /**
     * DateTime'ı database için string'e çevirir
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
     * Tüm attribute'ları döndürür
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Tüm attribute'ları bir seferde set eder
     */
    public function setRawAttributes(array $attributes, bool $sync = false): self
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        $this->clearAttributeCache();

        return $this;
    }

    /**
     * Orjinal attribute değerini döndürür
     */
    public function getOriginal(?string $key = null): mixed
    {
        if (is_null($key)) {
            return $this->original;
        }

        return $this->original[$key] ?? null;
    }

    /**
     * Tek bir orjinal attribute döndürür
     */
    public function getOriginalAttribute(string $key): mixed
    {
        return $this->original[$key] ?? null;
    }

    /**
     * Sadece belirtilen attribute'ları döndürür
     */
    public function only(array $attributes): array
    {
        $results = [];

        foreach ($attributes as $attribute) {
            $results[$attribute] = $this->getAttribute($attribute);
        }

        return $results;
    }

    /**
     * Attribute'ları array'e çevirir
     */
    protected function attributesToArray(): array
    {
        $attributes = [];

        foreach (array_keys($this->attributes) as $key) {
            $attributes[$key] = $this->getAttribute($key);
        }

        return $attributes;
    }

    /**
     * snake_case'i StudlyCase'e çevirir
     */
    protected function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }

    /**
     * Attribute önbelleği metotları
     */
    protected function hasCachedAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributeCache);
    }

    protected function getCachedAttribute(string $key): mixed
    {
        return $this->attributeCache[$key];
    }

    protected function cacheAttribute(string $key, mixed $value): void
    {
        $this->attributeCache[$key] = $value;
    }

    protected function clearAttributeFromCache(string $key): void
    {
        unset($this->attributeCache[$key]);
    }

    public function clearAttributeCache(): self
    {
        $this->attributeCache = [];
        return $this;
    }

    /**
     * İlişki helper metotları (HasRelationships için)
     * Bu metotlar trait'te tanımlı olmalı çünkü getAttribute içinde kullanılıyor
     */
    protected function hasLoadedRelation(string $key): bool
    {
        return method_exists($this, 'relationLoaded') && $this->relationLoaded($key);
    }

    protected function getLoadedRelation(string $key): mixed
    {
        return method_exists($this, 'getRelation') ? $this->getRelation($key) : null;
    }

    protected function hasRelationMethod(string $key): bool
    {
        return method_exists($this, $key);
    }

    protected function getRelationValue(string $key): mixed
    {
        if (method_exists($this, 'getRelationshipFromMethod')) {
            return $this->getRelationshipFromMethod($key);
        }

        return null;
    }
}