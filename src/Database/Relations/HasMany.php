<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsMany;
use Zephyr\Support\Collection;

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
        parent::__construct($query, $parent);

        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        $this->addConstraints();
    }

    public function addConstraints(): void
    {
        $localValue = $this->parent->getAttribute($this->localKey);

        if (!is_null($localValue)) {
            $this->query->where($this->foreignKey, '=', $localValue);
        }
    }

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

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                $collection = $this->query->getModel()?->newCollection($dictionary[$key]);
                $model->setRelation($relation, $collection);
            } else {
                $model->setRelation($relation, $this->query->getModel()?->newCollection([]));
            }
        }

        return $models;
    }

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

    public function getResults(): Collection
    {
        return $this->query->get();
    }

    public function create(array $attributes): Model
    {
        $attributes[$this->foreignKey] = $this->parent->getAttribute($this->localKey);
        return $this->query->create($attributes);
    }

    public function save(Model $model): bool
    {
        $model->setAttribute(
            $this->foreignKey,
            $this->parent->getAttribute($this->localKey)
        );

        return $model->save();
    }

    public function saveMany(array $models): bool
    {
        foreach ($models as $model) {
            $this->save($model);
        }

        return true;
    }
}