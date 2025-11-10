<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsOne;

/**
 * One-to-One İlişki
 *
 * ✅ DÜZELTME: Null pointer sorunları çözüldü!
 * ✅ DÜZELTME: ReturnsOne interface'i doğru implement edildi!
 * ✅ DÜZELTME: Type safety eklendi!
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class HasOne extends Relation implements ReturnsOne
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

    /**
     * Lazy loading için constraint ekler
     */
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

    /**
     * Eager loading için constraint ekler
     */
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
     * ✅ DÜZELTME: HasOne için tek model döndürür (array değil)
     */
    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if ($key !== null && isset($dictionary[$key])) {
                // ✅ DÜZELTME: HasOne - İlk kaydı al (tek kayıt olmalı)
                $model->setRelation($relation, $dictionary[$key][0] ?? null);
            } else {
                // ✅ DÜZELTME: Null döndür
                $model->setRelation($relation, null);
            }
        }

        return $models;
    }

    /**
     * ✅ DÜZELTME: Dictionary oluştur (HasMany'den aynı ama null safe)
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
     * ✅ DÜZELTME: ReturnsOne interface - tek model döndürür
     */
    public function getResults(): ?Model
    {
        return $this->query->firstModel(); // ✅ QueryBuilder'da artık mevcut
    }

    /**
     * İlgili model oluşturur ve kaydeder
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
     * İlgili modeli kaydeder
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
     * İlişki başlatır (null olarak)
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * ✅ YENİ: Relationship'i dissociate et (foreign key'i null yap)
     */
    public function dissociate(): int
    {
        return $this->query->update([$this->foreignKey => null]);
    }

    /**
     * ✅ YENİ: İlişki var mı kontrol et
     */
    public function exists(): bool
    {
        return $this->query->exists();
    }
}