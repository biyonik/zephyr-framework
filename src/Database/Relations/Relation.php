<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\RelationContract;

/**
 * Base Relation Class
 *
 * Abstract base for all relationship types.
 * Provides common functionality for:
 * - Query building
 * - Eager loading
 * - Method forwarding to query builder
 *
 * Each child class implements either ReturnsMany or ReturnsOne interface
 * to define its specific return type for getResults().
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
abstract class Relation implements RelationContract
{
    /**
     * Parent model instance
     */
    protected Model $parent;

    /**
     * Related model query builder
     */
    protected Builder $query;

    /**
     * Constructor
     */
    public function __construct(Builder $query, Model $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
    }

    /**
     * Add constraints to query based on relationship
     *
     * Implementation provided by child classes (HasMany, HasOne, BelongsTo).
     *
     * @return void
     */
    abstract public function addConstraints(): void;

    /**
     * Add eager loading constraints to query
     *
     * Implementation provided by child classes.
     *
     * @param array<Model> $models Parent models
     * @return void
     */
    abstract public function addEagerConstraints(array $models): void;

    /**
     * Match eager loaded results to parent models
     *
     * Implementation provided by child classes.
     *
     * @param array<Model> $models Parent models
     * @param array<Model> $results Eager loaded results
     * @param string $relation Relationship name
     * @return array<Model>
     */
    abstract public function match(array $models, array $results, string $relation): array;

    /**
     * Eager load and match relationship
     *
     * This is the main method called during eager loading.
     * It coordinates the process of loading relationships for multiple models.
     *
     * @param array<Model> $models Parent models
     * @param string $relation Relationship name
     * @return array<Model> Models with loaded relationships
     */
    public function eagerLoadAndMatch(array $models, string $relation): array
    {
        // Add constraints for eager loading
        $this->addEagerConstraints($models);

        // Get results
        $results = $this->getEager();

        // Match results to parent models
        return $this->match($models, $results, $relation);
    }

    /**
     * Get eager loading results
     *
     * Executes the query and returns all results.
     *
     * @return array<Model> Array of related models
     */
    protected function getEager(): array
    {
        return $this->query->get();
    }

    /**
     * Get the underlying query builder
     *
     * @return Builder Query builder instance
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Get parent model
     *
     * @return Model Parent model instance
     */
    public function getParent(): Model
    {
        return $this->parent;
    }

    /**
     * Forward method calls to query builder
     *
     * This allows chaining query methods on the relationship:
     * $user->posts()->where('published', true)->get()
     *
     * @param string $method Method name
     * @param array $parameters Method parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->query->$method(...$parameters);

        // Return self for chainable methods
        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }
}