<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsOne;

/**
 * Has One Relation
 *
 * One-to-one relationship (e.g., User has one Profile).
 * Extends HasMany but implements ReturnsOne interface - returns single model instead of array.
 *
 * Usage:
 * class User extends Model {
 *     public function profile() {
 *         return $this->hasOne(Profile::class);
 *     }
 * }
 *
 * $user->profile; // Get the profile (single model or null)
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class HasOne extends HasMany implements ReturnsOne
{
    /**
     * Get results for relationship
     *
     * Overrides HasMany to return single Model instead of array.
     * Parent's mixed return type allows this covariant return.
     *
     * @return Model|null Single related model or null
     */
    public function getResults(): ?Model
    {
        return $this->query->first();
    }

    /**
     * Match eager loaded results to parent models
     *
     * @param array $models Parent models
     * @param array $results Related models
     * @param string $relation Relationship name
     * @return array
     */
    public function match(array $models, array $results, string $relation): array
    {
        // Build dictionary of results keyed by foreign key
        $dictionary = $this->buildDictionary($results);

        // Match single result to each parent model
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                // Get first (and only) result
                $model->setRelation($relation, $dictionary[$key][0] ?? null);
            } else {
                $model->setRelation($relation, null);
            }
        }

        return $models;
    }

    /**
     * Initialize relation on a set of models
     *
     * @param array $models
     * @param string $relation
     * @return array
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }
}