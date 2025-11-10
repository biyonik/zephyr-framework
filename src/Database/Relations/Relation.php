<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\RelationContract;

/**
 * Base Relation Class
 *
 * ✅ DÜZELTME: Null pointer güvenliği eklendi!
 * ✅ DÜZELTME: Model existence validation!
 * ✅ DÜZELTME: Type safety improvements!
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
abstract class Relation implements RelationContract
{
    protected Model $parent;
    protected Builder $query;

    public function __construct(Builder $query, Model $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
        
        // ✅ DÜZELTME: Query'de model set edildiğinden emin ol
        if (!$this->query->getModel()) {
            throw new \RuntimeException(
                'Query builder must have a model set for relations. Use setModel() first.'
            );
        }
    }

    abstract public function addConstraints(): void;
    abstract public function addEagerConstraints(array $models): void;
    abstract public function match(array $models, array $results, string $relation): array;

    public function eagerLoadAndMatch(array $models, string $relation): array
    {
        $this->addEagerConstraints($models);
        $results = $this->getEager();
        return $this->match($models, $results, $relation);
    }

    protected function getEager(): array
    {
        return $this->query->getModels()->all(); // Collection'dan array'e çevir
    }

    public function getQuery(): Builder
    {
        return $this->query;
    }

    public function getParent(): Model
    {
        return $this->parent;
    }

    /**
     * ✅ YENİ: Model'i güvenli şekilde al
     */
    protected function getModelSafely(): Model
    {
        $model = $this->query->getModel();
        
        if (!$model) {
            throw new \RuntimeException(
                'Model not set on relation query. Cannot proceed with relation operations.'
            );
        }
        
        return $model;
    }

    /**
     * ✅ YENİ: Güvenli Collection oluştur
     */
    protected function newCollection(array $models = []): \Zephyr\Support\Collection
    {
        return $this->getModelSafely()->newCollection($models);
    }

    /**
     * ✅ YENİ: Güvenli model instance oluştur
     */
    protected function newModelInstance(array $attributes = []): Model
    {
        return $this->getModelSafely()->newInstance($attributes);
    }

    /**
     * ✅ YENİ: Array'den model oluştur
     */
    protected function newFromBuilder(array $attributes): Model
    {
        return $this->getModelSafely()->newFromBuilder($attributes);
    }

    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->query->$method(...$parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }
}