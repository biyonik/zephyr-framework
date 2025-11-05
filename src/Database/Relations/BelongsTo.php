<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsOne;

/**
 * Belongs To Relation
 *
 * Inverse of one-to-many (e.g., Post belongs to User).
 * Implements ReturnsOne interface - returns single model or null.
 *
 * Usage:
 * class Post extends Model {
 *     public function user() {
 *         return $this->belongsTo(User::class);
 *     }
 * }
 *
 * $post->user; // Get the user (single model or null)
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class BelongsTo extends Relation implements ReturnsOne
{
    /**
     * Foreign key on this model (e.g., 'user_id')
     */
    protected string $foreignKey;

    /**
     * Owner key on related model (e.g., 'id')
     */
    protected string $ownerKey;

    /**
     * Constructor
     *
     * @param Builder $query Related model query
     * @param Model $parent Parent (child) model
     * @param string $foreignKey Foreign key on parent
     * @param string $ownerKey Primary key on related model
     */
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

    /**
     * Add constraints to query
     *
     * Implementation of RelationContract interface.
     * Adds WHERE clause for the parent model.
     *
     * @return void
     *
     * @example
     * $post->user() adds: WHERE users.id = $post->user_id
     */
    public function addConstraints(): void
    {
        $foreignKeyValue = $this->parent->getAttribute($this->foreignKey);

        if (!is_null($foreignKeyValue)) {
            $this->query->where($this->ownerKey, '=', $foreignKeyValue);
        }
    }

    /**
     * Add eager loading constraints
     *
     * Implementation of RelationContract interface.
     * Adds WHERE IN clause for multiple child models.
     *
     * @param array<Model> $models Child models
     * @return void
     *
     * @example
     * Post::with('user') adds: WHERE users.id IN (1, 2, 3, ...)
     * (where 1, 2, 3 are the user_id values from posts)
     */
    public function addEagerConstraints(array $models): void
    {
        // Get all foreign key values from child models
        $keys = array_map(function ($model) {
            return $model->getAttribute($this->foreignKey);
        }, $models);

        // Remove nulls and duplicates
        $keys = array_unique(array_filter($keys));

        if (empty($keys)) {
            // No keys, return nothing
            $this->query->where($this->ownerKey, '=', null);
            return;
        }

        // Add WHERE IN constraint
        $this->query->whereIn($this->ownerKey, $keys);
    }

    /**
     * Match eager loaded results to child models
     *
     * Implementation of RelationContract interface.
     * Matches parent models to child models based on foreign key.
     *
     * @param array<Model> $models Child models
     * @param array<Model> $results Parent models
     * @param string $relation Relationship name
     * @return array<Model> Child models with loaded parent relationships
     *
     * @example
     * Input:
     *   $models = [Post(user_id=1), Post(user_id=2), Post(user_id=1)]
     *   $results = [User(id=1), User(id=2)]
     *
     * Output:
     *   Post(user_id=1)->user = User(id=1)
     *   Post(user_id=2)->user = User(id=2)
     *   Post(user_id=1)->user = User(id=1)
     */
    public function match(array $models, array $results, string $relation): array
    {
        // Build dictionary of results keyed by owner key
        $dictionary = $this->buildDictionary($results);

        // Match results to each child model
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

    /**
     * Build dictionary of results keyed by owner key
     *
     * Creates a lookup dictionary for efficient matching.
     *
     * @param array<Model> $results Parent models
     * @return array<int|string, Model> Dictionary: owner_key => Model
     *
     * @example
     * Input: [User(id=1), User(id=2), User(id=3)]
     * Output: [
     *   1 => User(id=1),
     *   2 => User(id=2),
     *   3 => User(id=3)
     * ]
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $ownerKeyValue = $result->getAttribute($this->ownerKey);
            $dictionary[$ownerKeyValue] = $result;
        }

        return $dictionary;
    }

    /**
     * Get results for relationship
     *
     * Implementation of ReturnsOne interface.
     * Returns single parent model or null if not found.
     *
     * @return Model|null Related parent model or null
     *
     * @example
     * $user = $post->user()->getResults();
     * // Returns: User model or null
     */
    public function getResults(): ?Model
    {
        return $this->query->first();
    }

    /**
     * Associate a related model
     *
     * Sets the foreign key to the related model's primary key.
     * Useful for establishing relationships before saving.
     *
     * @param Model $model Related model to associate
     * @return Model Parent (child) model with updated foreign key
     *
     * @example
     * $post = new Post(['title' => 'My Post']);
     * $post->user()->associate($user);
     * // Sets: $post->user_id = $user->id
     * $post->save();
     */
    public function associate(Model $model): Model
    {
        // Set foreign key to related model's primary key
        $this->parent->setAttribute(
            $this->foreignKey,
            $model->getAttribute($this->ownerKey)
        );

        // Cache the related model
        $this->parent->setRelation(
            $this->getRelationName(),
            $model
        );

        return $this->parent;
    }

    /**
     * Dissociate the related model
     *
     * Clears the foreign key, removing the relationship.
     *
     * @return Model Parent (child) model with cleared foreign key
     *
     * @example
     * $post->user()->dissociate();
     * // Sets: $post->user_id = null
     * $post->save();
     */
    public function dissociate(): Model
    {
        // Clear foreign key
        $this->parent->setAttribute($this->foreignKey, null);

        // Clear cached relation
        $this->parent->setRelation($this->getRelationName(), null);

        return $this->parent;
    }

    /**
     * Get relationship name (for caching)
     *
     * Attempts to extract the relationship method name from backtrace.
     * Used for caching related models.
     *
     * @return string Relationship method name
     */
    protected function getRelationName(): string
    {
        // Try to extract from backtrace
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        return $backtrace[2]['function'] ?? 'relation';
    }

    /**
     * Update parent model with relationship
     *
     * Updates the related parent model directly.
     *
     * @param array $attributes Attributes to update
     * @return int Number of affected rows
     *
     * @example
     * $post->user()->update(['name' => 'Updated Name']);
     * // Updates the user that the post belongs to
     */
    public function update(array $attributes): int
    {
        return $this->query->update($attributes);
    }
}