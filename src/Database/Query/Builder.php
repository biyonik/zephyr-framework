<?php

declare(strict_types=1);

namespace Zephyr\Database\Query;

use Zephyr\Database\QueryBuilder;
use Zephyr\Database\Model;
use Zephyr\Database\Exception\ModelNotFoundException;

/**
 * Model Query Builder
 *
 * Extends QueryBuilder to add model-specific functionality:
 * - Returns model instances instead of arrays
 * - Supports relationships
 * - Eager loading
 * - Model hydration
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Builder extends QueryBuilder
{
    /**
     * The model being queried
     */
    protected ?Model $model = null;

    /**
     * Relationships to eager load
     */
    protected array $eagerLoad = [];

    /**
     * Set the model being queried
     */
    public function setModel(Model $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Get the model instance
     */
    public function getModel(): ?Model
    {
        return $this->model;
    }

    /**
     * Execute query and get model instances
     *
     * @return array Array of model instances
     */
    public function get(): array
    {
        $results = parent::get();

        if (empty($results)) {
            return [];
        }

        return $this->hydrate($results);
    }

    /**
     * Get first model instance
     *
     * @return Model|null
     */
    public function first(): ?Model
    {
        $result = parent::first();

        if (is_null($result)) {
            return null;
        }

        $models = $this->hydrate([$result]);
        return $models[0] ?? null;
    }

    /**
     * Find model by primary key
     *
     * @param mixed $id Primary key value
     * @param array $columns Columns to select
     * @return Model|null
     */
    public function find(mixed $id, array $columns = ['*']): ?Model
    {
        return $this->select(...$columns)
            ->where($this->model->getKeyName(), '=', $id)
            ->first();
    }

    /**
     * Find model or throw exception
     *
     * @param mixed $id
     * @param array $columns
     * @return Model
     * @throws ModelNotFoundException
     */
    public function findOrFail(mixed $id, array $columns = ['*']): Model
    {
        $model = $this->find($id, $columns);

        if (is_null($model)) {
            throw (new ModelNotFoundException)
                ->setModel(get_class($this->model))
                ->setIds([$id]);
        }

        return $model;
    }

    /**
     * Find multiple models by primary keys
     *
     * @param array $ids
     * @param array $columns
     * @return array
     */
    public function findMany(array $ids, array $columns = ['*']): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->select(...$columns)
            ->whereIn($this->model->getKeyName(), $ids)
            ->get();
    }

    /**
     * Insert and get model instance
     *
     * @param array $values
     * @return Model
     */
    public function create(array $values): Model
    {
        $id = $this->insertGetId($values);

        $model = $this->model->newInstance();
        $model->setAttribute($model->getKeyName(), $id);
        $model->setRawAttributes($values, true);
        $model->exists = true;
        $model->wasRecentlyCreated = true;

        return $model;
    }

    /**
     * Insert and get last insert ID
     *
     * @param array $values
     * @return string
     */
    public function insertGetId(array $values): string
    {
        return $this->insert($values);
    }

    /**
     * Eager load relationships
     *
     * @param array|string $relations
     * @return self
     *
     * @example
     * User::with('posts', 'profile')->get();
     * User::with(['posts' => function($query) {
     *     $query->where('published', true);
     * }])->get();
     */
    public function with(array|string $relations): self
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        foreach ($relations as $name => $constraints) {
            // Simple string relation
            if (is_numeric($name)) {
                $name = $constraints;
                $constraints = null;
            }

            $this->eagerLoad[$name] = $constraints;
        }

        return $this;
    }

    /**
     * Eager load relationships on models
     *
     * @param array $models
     * @return array
     */
    protected function eagerLoadRelations(array $models): array
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            $models = $this->eagerLoadRelation($models, $name, $constraints);
        }

        return $models;
    }

    /**
     * Eager load a single relationship
     *
     * @param array $models
     * @param string $name Relationship name
     * @param callable|null $constraints Query constraints
     * @return array
     */
    protected function eagerLoadRelation(array $models, string $name, ?callable $constraints): array
    {
        if (empty($models)) {
            return $models;
        }

        // Get relation instance from first model
        $relation = $models[0]->$name();

        // Apply constraints if provided
        if (!is_null($constraints)) {
            $constraints($relation);
        }

        // Load relation for all models
        return $relation->eagerLoadAndMatch($models, $name);
    }

    /**
     * Hydrate models from database results
     *
     * @param array $items Raw database rows
     * @return array Array of model instances
     */
    protected function hydrate(array $items): array
    {
        $models = array_map(function ($item) {
            return $this->model->newFromBuilder($item);
        }, $items);

        // Eager load relationships if any
        if (!empty($this->eagerLoad)) {
            $models = $this->eagerLoadRelations($models);
        }

        return $models;
    }

    /**
     * Paginate and return models
     *
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $result = parent::paginate($page, $perPage);

        // Hydrate data
        $result['data'] = $this->hydrate($result['data']);

        return $result;
    }

    /**
     * Get count of models
     */
    public function count(): int
    {
        return parent::count();
    }

    /**
     * Check if any models exist
     */
    public function exists(): bool
    {
        return parent::exists();
    }

    /**
     * Chunk results and hydrate models
     *
     * @param int $size
     * @param callable $callback
     * @return bool
     */
    public function chunk(int $size, callable $callback): bool
    {
        return parent::chunk($size, function ($results) use ($callback) {
            $models = $this->hydrate($results);
            return $callback($models);
        });
    }

    /**
     * Apply query scopes from model
     *
     * @param string $scope Scope method name
     * @param array $parameters Scope parameters
     * @return self
     */
    public function scope(string $scope, array $parameters = []): self
    {
        $method = 'scope' . ucfirst($scope);

        if (method_exists($this->model, $method)) {
            $this->model->$method($this, ...$parameters);
        }

        return $this;
    }

    /**
     * Dynamic scope method calls
     *
     * @example User::active()->get() calls scopeActive()
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Try to call scope
        if (method_exists($this->model, 'scope' . ucfirst($method))) {
            return $this->scope($method, $parameters);
        }

        // Fall back to parent
        return parent::__call($method, $parameters);
    }
}