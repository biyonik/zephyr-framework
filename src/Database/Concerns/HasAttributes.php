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
     * @var array<string, mixed>
     */
    protected array $attributeCache = [];

    /**
     * Attribute değerini döndürür
     *
     * Bu metot şu sırada arama yapar:
     * 1. Önbellek
     * 2. Yüklenmiş ilişkiler (relations)
     * 3. Database sütunları (attributes)
     * 4. İlişki metotları (lazy loading)
     * 5. Sanal accessor'lar
     *
     * @param string $key Attribute adı
     * @return mixed
     */
    public function getAttribute(string $key): mixed
    {
        if (!$key) {
            return null;
        }

        // 1. Önbellek kontrolü
        if (array_key_exists($key, $this->attributeCache)) {
            return $this->attributeCache[$key];
        }

        // 2. Yüklenmiş ilişkiler
        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        // 3. Database sütunu
        if (array_key_exists($key, $this->attributes)) {
            $value = $this->attributes[$key];

            // Accessor (Mutator) kontrolü
            if ($this->hasGetMutator($key)) {
                $mutatedValue = $this->mutateAttribute($key, $value);
                $this->attributeCache[$key] = $mutatedValue;
                return $mutatedValue;
            }

            // Cast (Tip dönüşümü) kontrolü
            if ($this->hasCast($key)) {
                $castValue = $this->castAttribute($key, $value);
                $this->attributeCache[$key] = $castValue;
                return $castValue;
            }

            // Ham değeri önbelleğe al ve döndür
            $this->attributeCache[$key] = $value;
            return $value;
        }

        // 4. İlişki metodu (lazy loading)
        if (method_exists($this, $key)) {
            $relationValue = $this->getRelationshipFromMethod($key);
            $this->attributeCache[$key] = $relationValue;
            return $relationValue;
        }

        // 5. Sanal accessor (DB sütunu olmayan)
        if ($this->hasGetMutator($key)) {
            $mutatedValue = $this->mutateAttribute($key, null);
            $this->attributeCache[$key] = $mutatedValue;
            return $mutatedValue;
        }

        return null;
    }

    /**
     * Attribute değerini set eder
     *
     * @param string $key Attribute adı
     * @param mixed $value Değer
     * @return self
     */
    public function setAttribute(string $key, mixed $value): self
    {
        // Önbelleği temizle
        unset($this->attributeCache[$key]);

        // 1. Mutator kontrolü (setUserNameAttribute)
        if ($this->hasSetMutator($key)) {
            return $this->setMutatedAttributeValue($key, $value);
        }

        // 2. Cast uygula
        if ($this->hasCast($key)) {
            $value = $this->castAttributeForStorage($key, $value);
        }

        // 3. Attribute'u set et
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Accessor metodu var mı kontrol eder
     *
     * @example getUserNameAttribute() for 'user_name'
     * @param string $key
     * @return bool
     */
    protected function hasGetMutator(string $key): bool
    {
        $method = 'get' . $this->studly($key) . 'Attribute';
        return method_exists($this, $method);
    }

    /**
     * Mutator metodu var mı kontrol eder
     *
     * @example setUserNameAttribute($value) for 'user_name'
     * @param string $key
     * @return bool
     */
    protected function hasSetMutator(string $key): bool
    {
        $method = 'set' . $this->studly($key) . 'Attribute';
        return method_exists($this, $method);
    }

    /**
     * Accessor kullanarak attribute değerini döndürür
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function mutateAttribute(string $key, mixed $value): mixed
    {
        $method = 'get' . $this->studly($key) . 'Attribute';
        return $this->$method($value);
    }

    /**
     * Mutator kullanarak attribute değerini set eder
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    protected function setMutatedAttributeValue(string $key, mixed $value): self
    {
        $method = 'set' . $this->studly($key) . 'Attribute';
        $this->$method($value);
        return $this;
    }

    /**
     * Attribute'un cast'i var mı kontrol eder
     *
     * @param string $key
     * @return bool
     */
    protected function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->casts);
    }

    /**
     * Cast tipini döndürür
     *
     * @param string $key
     * @return string|null
     */
    protected function getCastType(string $key): ?string
    {
        return $this->casts[$key] ?? null;
    }

    /**
     * Attribute'u belirtilen tipe cast eder (database → PHP)
     *
     * @param string $key
     * @param mixed $value Database'den gelen ham değer
     * @return mixed Cast edilmiş değer
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
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
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
            'bool', 'boolean' => (int) $value, // 0/1 olarak sakla
            'array', 'json', 'object', 'collection' => $this->asJson($value),
            'date', 'timestamp', 'datetime' => $this->fromDateTime($value),
            default => $value,
        };
    }

    /**
     * JSON string'ini array'e çevirir
     *
     * @param string $value
     * @return array
     * @throws \JsonException
     */
    protected function fromJson(string $value): array
    {
        return json_decode($value, true, 512, JSON_THROW_ON_ERROR) ?? [];
    }

    /**
     * Değeri JSON string'e çevirir
     *
     * @param mixed $value
     * @return string
     * @throws \JsonException
     */
    protected function asJson(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * Değeri DateTime instance'ına çevirir
     *
     * @param mixed $value
     * @return DateTime|null
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
     *
     * @param mixed $value
     * @return string|null
     */
    protected function asDate(mixed $value): ?string
    {
        return $this->asDateTime($value)?->format('Y-m-d');
    }

    /**
     * Değeri timestamp'e çevirir
     *
     * @param mixed $value
     * @return int|null
     */
    protected function asTimestamp(mixed $value): ?int
    {
        return $this->asDateTime($value)?->getTimestamp();
    }

    /**
     * DateTime'ı database için string'e çevirir
     *
     * @param mixed $value
     * @return string|null
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
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Tüm attribute'ları bir seferde set eder
     *
     * @param array $attributes
     * @param bool $sync Orjinal'i senkronize et mi?
     * @return self
     */
    public function setRawAttributes(array $attributes, bool $sync = false): self
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        // Tüm önbelleği temizle
        $this->clearAttributeCache();

        return $this;
    }

    /**
     * Orjinal attribute değerini döndürür
     *
     * @param string|null $key
     * @return mixed
     */
    public function getOriginal(?string $key = null): mixed
    {
        if (is_null($key)) {
            return $this->original;
        }

        return $this->original[$key] ?? null;
    }

    /**
     * Sadece belirtilen attribute'ları döndürür
     *
     * @param array $attributes
     * @return array
     */
    public function only(array $attributes): array
    {
        return array_intersect_key($this->attributes, array_flip($attributes));
    }

    /**
     * Attribute'ları array'e çevirir
     *
     * @return array
     */
    protected function attributesToArray(): array
    {
        $attributes = [];

        // Tanımlı tüm attribute'ları al
        foreach (array_keys($this->attributes) as $key) {
            $attributes[$key] = $this->getAttribute($key);
        }

        return $attributes;
    }

    /**
     * snake_case'i StudlyCase'e çevirir
     *
     * @param string $value
     * @return string
     */
    protected function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }

    /**
     * Attribute önbelleğini temizler
     *
     * @return self
     */
    public function clearAttributeCache(): self
    {
        $this->attributeCache = [];
        return $this;
    }
}