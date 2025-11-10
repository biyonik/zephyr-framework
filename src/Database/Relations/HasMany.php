<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsMany;
use Zephyr\Support\Collection;

/**
 * One-to-Many İlişki
 *
 * ✅ DÜZELTME: Null pointer sorunları çözüldü!
 * ✅ DÜZELTME: Type safety eklendi!
 * ✅ DÜZELTME: Error handling iyileştirildi!
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class HasMany extends Relation implements ReturnsMany
{
    protected string $foreignKey;
    protected string $localKey;

    public function __construct(
        Builder $query,
        Model $parent,
        string $foreignKey,
        string $localKey
    ) {
        parent::__construct($query, $parent); // ✅ Parent constructor model check yapar

        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        $this->addConstraints();
    }

    public function addConstraints(): void
    {
        $localValue = $this->parent->getAttribute($this->localKey);

        if (!is_null($localValue)) {
            $this->query->where($this->foreignKey, '=', $localValue);
        } else {
            // ✅ DÜZELTME: Local value null ise empty result döndür
            $this->query->whereRaw('1 = 0');
        }
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = array_map(function ($model) {
            return $model->getAttribute($this->localKey);
        }, $models);

        $keys = array_unique(array_filter($keys, fn($key) => !is_null($key)));

        if (empty($keys)) {
            // ✅ DÜZELTME: Key yoksa empty result
            $this->query->whereRaw('1 = 0');
            return;
        }

        $this->query->whereIn($this->foreignKey, $keys);
    }

    /**
     * ✅ DÜZELTME: Null pointer sorunları çözüldü
     */
    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if ($key !== null && isset($dictionary[$key])) {
                // ✅ DÜZELTME: Güvenli collection oluşturma
                $collection = $this->newCollection($dictionary[$key]);
                $model->setRelation($relation, $collection);
            } else {
                // ✅ DÜZELTME: Empty collection
                $model->setRelation($relation, $this->newCollection([]));
            }
        }

        return $models;
    }

    /**
     * ✅ DÜZELTME: Null safe dictionary building
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $foreignKeyValue = $result->getAttribute($this->foreignKey);

            // ✅ DÜZELTME: Null check
            if ($foreignKeyValue === null) {
                continue;
            }

            if (!isset($dictionary[$foreignKeyValue])) {
                $dictionary[$foreignKeyValue] = [];
            }

            $dictionary[$foreignKeyValue][] = $result;
        }

        return $dictionary;
    }

    /**
     * ✅ DÜZELTME: Güvenli collection döndürme
     */
    public function getResults(): Collection
    {
        return $this->query->getModels(); // ✅ QueryBuilder'da artık mevcut
    }

    /**
     * ✅ DÜZELTME: Create metodu güvenli hale getirildi
     */
    public function create(array $attributes): Model
    {
        // ✅ DÜZELTME: Local key validation
        $localValue = $this->parent->getAttribute($this->localKey);
        if ($localValue === null) {
            throw new \RuntimeException(
                "Cannot create related model: parent model's {$this->localKey} is null"
            );
        }

        $attributes[$this->foreignKey] = $localValue;
        return $this->query->create($attributes);
    }

    /**
     * ✅ DÜZELTME: Save metodu güvenli hale getirildi
     */
    public function save(Model $model): bool
    {
        // ✅ DÜZELTME: Local key validation
        $localValue = $this->parent->getAttribute($this->localKey);
        if ($localValue === null) {
            throw new \RuntimeException(
                "Cannot save related model: parent model's {$this->localKey} is null"
            );
        }

        $model->setAttribute($this->foreignKey, $localValue);

        return $model->save();
    }

    /**
     * ✅ DÜZELTME: SaveMany metodu güvenli hale getirildi
     */
    public function saveMany(array $models): bool
    {
        // ✅ DÜZELTME: Local key validation
        $localValue = $this->parent->getAttribute($this->localKey);
        if ($localValue === null) {
            throw new \RuntimeException(
                "Cannot save related models: parent model's {$this->localKey} is null"
            );
        }

        foreach ($models as $model) {
            if (!$model instanceof Model) {
                throw new \InvalidArgumentException('All items must be Model instances');
            }
            $this->save($model);
        }

        return true;
    }

    /**
     * ✅ YENİ: Relationship'i dissociate et (foreign key'i null yap)
     */
    public function dissociate(): int
    {
        return $this->query->update([$this->foreignKey => null]);
    }

    /**
     * ✅ YENİ: İlişki count'ını döndür
     */
    public function count(): int
    {
        return $this->query->count();
    }

    /**
     * ✅ YENİ: Foreign key adını döndür (Query Builder has() için gerekli)
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * ✅ YENİ: Local key adını döndür (Query Builder has() için gerekli)
     */
    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }
}