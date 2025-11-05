<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations\Contracts;

use Zephyr\Database\Model;

/**
 * Relation Contract
 *
 * Base interface for all relationship types.
 * Defines common behavior that all relations must implement.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
interface RelationContract
{
    /**
     * Add constraints to query based on relationship
     *
     * This is called when loading a relationship on a single model.
     * Example: $user->posts() adds WHERE posts.user_id = 1
     *
     * @return void
     */
    public function addConstraints(): void;

    /**
     * Add eager loading constraints to query
     *
     * This is called when loading relationships on multiple models.
     * Example: User::with('posts') adds WHERE posts.user_id IN (1,2,3,...)
     *
     * @param array<Model> $models Parent models
     * @return void
     */
    public function addEagerConstraints(array $models): void;

    /**
     * Match eager loaded results to parent models
     *
     * Takes the results from eager loading and assigns them to the correct parent models.
     *
     * @param array<Model> $models Parent models
     * @param array<Model> $results Eager loaded results
     * @param string $relation Relationship name
     * @return array<Model> Models with matched relationships
     */
    public function match(array $models, array $results, string $relation): array;
}