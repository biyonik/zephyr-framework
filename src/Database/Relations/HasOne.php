<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsOne;
use Zephyr\Support\Collection;

/**
 * One-to-One İlişki
 *
 * HasMany'den miras almak yerine doğrudan Relation'dan miras alır.
 * Bu sayede interface uyumsuzluğu çözülür.
 *
 * HasMany: Collection döndürür
 * HasOne: ?Model döndürür
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
        parent::__construct($query, $parent);

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

        $keys = array_unique(array_filter($keys));

        if (empty($keys)) {
            $this->query->where($this->foreignKey, '=', null);
            return;
        }

        $this->query->whereIn($this->foreignKey, $keys);
    }

    /**
     * Eager loading sonuçlarını eşleştirir
     * 
     * HasOne için tek model döndürür, HasMany gibi array değil.
     */
    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                // HasOne: İlk kaydı al (tek kayıt olmalı)
                $model->setRelation($relation, $dictionary[$key][0] ?? null);
            } else {
                $model->setRelation($relation, null);
            }
        }

        return $models;
    }

    /**
     * Dictionary oluşturur (HasMany'den kopyalandı)
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $foreignKeyValue = $result->getAttribute($this->foreignKey);

            if (!isset($dictionary[$foreignKeyValue])) {
                $dictionary[$foreignKeyValue] = [];
            }

            $dictionary[$foreignKeyValue][] = $result;
        }

        return $dictionary;
    }

    /**
     * İlişki sonuçlarını döndürür (tek model)
     * 
     * ✅ ReturnsOne interface'ini implement ediyor
     */
    public function getResults(): ?Model
    {
        return $this->query->firstModel(); // Tek kayıt döndür
    }

    /**
     * İlgili model oluşturur ve kaydeder
     */
    public function create(array $attributes): Model
    {
        $attributes[$this->foreignKey] = $this->parent->getAttribute($this->localKey);
        return $this->query->create($attributes);
    }

    /**
     * İlgili modeli kaydeder
     */
    public function save(Model $model): bool
    {
        $model->setAttribute(
            $this->foreignKey,
            $this->parent->getAttribute($this->localKey)
        );

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
}