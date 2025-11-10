<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsOne;

class BelongsTo extends Relation implements ReturnsOne
{
    protected string $foreignKey;
    protected string $ownerKey;

    public function __construct(
        Builder $query,
        Model $parent,
        string $foreignKey,
        string $ownerKey
    ) {
        parent::__construct($query, $parent);

        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;

        $this->addConstraints();
    }

    public function addConstraints(): void
    {
        $foreignKeyValue = $this->parent->getAttribute($this->foreignKey);

        if (!is_null($foreignKeyValue)) {
            $this->query->where($this->ownerKey, '=', $foreignKeyValue);
        }
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = array_map(function ($model) {
            return $model->getAttribute($this->foreignKey);
        }, $models);

        $keys = array_unique(array_filter($keys));

        if (empty($keys)) {
            $this->query->where($this->ownerKey, '=', null);
            return;
        }

        $this->query->whereIn($this->ownerKey, $keys);
    }

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $foreignKeyValue = $model->getAttribute($this->foreignKey);

            if (isset($dictionary[$foreignKeyValue])) {
                $model->setRelation($relation, $dictionary[$foreignKeyValue]);
            } else {
                $model->setRelation($relation, null);
            }
        }

        return $models;
    }

    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $ownerKeyValue = $result->getAttribute($this->ownerKey);
            $dictionary[$ownerKeyValue] = $result;
        }

        return $dictionary;
    }

    public function getResults(): ?Model
    {
        return $this->query->first();
    }

    public function associate(Model $model): Model
    {
        $this->parent->setAttribute(
            $this->foreignKey,
            $model->getAttribute($this->ownerKey)
        );

        $this->parent->setRelation(
            $this->getRelationName(),
            $model
        );

        return $this->parent;
    }

    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);
        $this->parent->setRelation($this->getRelationName(), null);

        return $this->parent;
    }

    protected function getRelationName(): string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        return $backtrace[2]['function'] ?? 'relation';
    }

    public function update(array $attributes): int
    {
        return $this->query->update($attributes);
    }
}