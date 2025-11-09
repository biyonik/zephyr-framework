<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsMany;
use Zephyr\Support\Collection;

/**
 * Has Many Relation
 *
 * One-to-many relationship (e.g., User has many Posts).
 * Implements ReturnsMany interface - always returns an array of models.
 *
 * Usage:
 * class User extends Model {
 *     public function posts() {
 *         return $this->hasMany(Post::class);
 *     }
 * }
 *
 * $user->posts; // Get all posts (array)
 * $user->posts()->where('published', true)->get();
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class HasMany extends Relation implements ReturnsMany
{
    /**
     * Foreign key on related model
     */
    protected string $foreignKey;

    /**
     * Local key on parent model
     */
    protected string $localKey;

    /**
     * Constructor
     *
     * @param Builder $query Related model query
     * @param Model $parent Parent model
     * @param string $foreignKey Foreign key (e.g., 'user_id')
     * @param string $localKey Local key (e.g., 'id')
     */
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
     * Add constraints to query
     *
     * Implementation of RelationContract interface.
     * Adds WHERE clause for single parent model.
     *
     * @return void
     *
     * @example
     * $user->posts() adds: WHERE posts.user_id = 1
     */
    public function addConstraints(): void
    {
        if (!is_null($this->parent->getAttribute($this->localKey))) {
            $this->query->where(
                $this->foreignKey,
                '=',
                $this->parent->getAttribute($this->localKey)
            );
        }
    }

    /**
     * Add eager loading constraints
     *
     * Implementation of RelationContract interface.
     * Adds WHERE IN clause for multiple parent models.
     *
     * @param array<Model> $models Parent models
     * @return void
     *
     * @example
     * User::with('posts') adds: WHERE posts.user_id IN (1, 2, 3, ...)
     */
    public function addEagerConstraints(array $models): void
    {
        // Get all local key values from parent models
        $keys = array_map(function ($model) {
            return $model->getAttribute($this->localKey);
        }, $models);

        // Remove nulls and duplicates
        $keys = array_unique(array_filter($keys));

        if (empty($keys)) {
            // No keys, make query return nothing
            $this->query->where($this->foreignKey, '=', null);
            return;
        }

        // Add WHERE IN constraint
        $this->query->whereIn($this->foreignKey, $keys);
    }

    /**
     * Match eager loaded results to parent models
     *
     * Implementation of RelationContract interface.
     * Groups results by foreign key and assigns arrays to parent models.
     *
     * @param array<Model> $models Parent models
     * @param array<Model> $results Related models
     * @param string $relation Relationship name
     * @return array<Model> Parent models with loaded relationships
     *
     * @example
     * Input:
     *   $models = [User(id=1), User(id=2)]
     *   $results = [Post(user_id=1), Post(user_id=1), Post(user_id=2)]
     *
     * Output:
     *   User(id=1)->posts = [Post, Post]
     *   User(id=2)->posts = [Post]
     */
    public function match(array $models, array $results, string $relation): array
    {
        // Group results by foreign key
        $dictionary = $this->buildDictionary($results);

        // Match results to each parent model
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                // ✅ Collection döndür
                $collection = $this->query->getModel()?->newCollection($dictionary[$key]);
                $model->setRelation($relation, $collection);
            } else {
                // ✅ Boş Collection döndür
                $model->setRelation($relation, $this->query->getModel()?->newCollection([]));
            }
        }

        return $models;
    }

    /**
     * Build dictionary of results keyed by foreign key
     *
     * Groups models by their foreign key value for efficient matching.
     *
     * @param array<Model> $results Related models
     * @return array<int|string, array<Model>> Dictionary: foreign_key => [models]
     *
     * @example
     * Input: [Post(user_id=1), Post(user_id=1), Post(user_id=2)]
     * Output: [
     *   1 => [Post, Post],
     *   2 => [Post]
     * ]
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
     * Get results for relationship
     *
     * @return Collection Array of related models
     */
    public function getResults(): Collection
    {
        return $this->query->get();
    }

    /**
     * Create a new related model
     *
     * Creates and saves a new model with the foreign key automatically set.
     *
     * @param array $attributes Model attributes
     * @return Model Created model instance
     *
     * @example
     * $post = $user->posts()->create([
     *     'title' => 'My Post',
     *     'content' => 'Lorem ipsum...'
     * ]);
     * // Automatically sets: $post->user_id = $user->id
     */
    public function create(array $attributes): Model
    {
        // Add foreign key to attributes
        $attributes[$this->foreignKey] = $this->parent->getAttribute($this->localKey);

        return $this->query->create($attributes);
    }

    /**
     * Save a related model
     *
     * Sets the foreign key and saves the model.
     *
     * @param Model $model Model to save
     * @return bool Success status
     *
     * @example
     * $post = new Post(['title' => 'My Post']);
     * $user->posts()->save($post);
     * // Sets: $post->user_id = $user->id and saves
     */
    public function save(Model $model): bool
    {
        // Set foreign key
        $model->setAttribute(
            $this->foreignKey,
            $this->parent->getAttribute($this->localKey)
        );

        return $model->save();
    }

    /**
     * Save multiple related models
     *
     * @param array<Model> $models Models to save
     * @return bool Success status
     *
     * @example
     * $user->posts()->saveMany([
     *     new Post(['title' => 'Post 1']),
     *     new Post(['title' => 'Post 2'])
     * ]);
     */
    public function saveMany(array $models): bool
    {
        foreach ($models as $model) {
            $this->save($model);
        }

        return true;
    }
}