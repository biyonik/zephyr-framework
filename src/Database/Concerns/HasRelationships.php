<?php

declare(strict_types=1);

namespace Zephyr\Database\Concerns;

use Zephyr\Database\Relations\HasMany;
use Zephyr\Database\Relations\BelongsTo;
use Zephyr\Database\Relations\HasOne;
use Zephyr\Database\Relations\BelongsToMany; // <-- YENİ

/**
 * Has Relationships Trait
 *
 * Provides relationship methods for models:
 * - hasMany: One-to-many relationship
 * - belongsTo: Inverse of hasMany
 * - hasOne: One-to-one relationship
 * - belongsToMany: Many-to-many relationship // <-- YENİ
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
trait HasRelationships
{
    /**
     * Loaded relationships
     */
    protected array $relations = [];

    /**
     * Define a one-to-many relationship
     *
     * @param string $related Related model class
     * @param string|null $foreignKey Foreign key on related model
     * @param string|null $localKey Local key on this model
     * @return HasMany
     *
     * @example
     * class User extends Model {
     * public function posts() {
     * return $this->hasMany(Post::class);
     * }
     * }
     *
     * $user->posts; // Get all posts
     * $user->posts()->where('published', true)->get();
     */
    public function hasMany(
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null
    ): HasMany {
        $instance = new $related;

        // Default foreign key: user_id
        $foreignKey = $foreignKey ?? $this->getForeignKey();

        // Default local key: id
        $localKey = $localKey ?? $this->getKeyName();

        return new HasMany(
            $instance->newQuery(),
            $this,
            $foreignKey,
            $localKey
        );
    }

    /**
     * Define an inverse one-to-many relationship
     *
     * @param string $related Related model class
     * @param string|null $foreignKey Foreign key on this model
     * @param string|null $ownerKey Owner key on related model
     * @return BelongsTo
     *
     * @example
     * class Post extends Model {
     * public function user() {
     * return $this->belongsTo(User::class);
     * }
     * }
     *
     * $post->user; // Get the user
     */
    public function belongsTo(
        string $related,
        ?string $foreignKey = null,
        ?string $ownerKey = null
    ): BelongsTo {
        $instance = new $related;

        // Default foreign key: user_id (from method name or related model)
        $foreignKey = $foreignKey ?? $this->guessBelongsToForeignKey($related);

        // Default owner key: id
        $ownerKey = $ownerKey ?? $instance->getKeyName();

        return new BelongsTo(
            $instance->newQuery(),
            $this,
            $foreignKey,
            $ownerKey
        );
    }

    /**
     * Define a one-to-one relationship
     *
     * @param string $related Related model class
     * @param string|null $foreignKey Foreign key on related model
     * @param string|null $localKey Local key on this model
     * @return HasOne
     *
     * @example
     * class User extends Model {
     * public function profile() {
     * return $this->hasOne(Profile::class);
     * }
     * }
     *
     * $user->profile; // Get the profile
     */
    public function hasOne(
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null
    ): HasOne {
        $instance = new $related;

        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? $this->getKeyName();

        return new HasOne(
            $instance->newQuery(),
            $this,
            $foreignKey,
            $localKey
        );
    }

    /**
     * Define a many-to-many relationship
     *
     * @param string $related Related model class
     * @param string|null $table Pivot table
     * @param string|null $foreignPivotKey Foreign key of this model on pivot
     * @param string|null $relatedPivotKey Foreign key of related model on pivot
     * @param string|null $parentKey Local key of this model
     * @param string|null $relatedKey Local key of related model
     * @return BelongsToMany
     *
     * @example
     * class User extends Model {
     * public function roles() {
     * return $this->belongsToMany(Role::class);
     * }
     * }
     *
     * $user->roles; // Get all roles
     * $user->roles()->attach(1);
     */
    public function belongsToMany(
        string $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null
    ): BelongsToMany {
        $instance = new $related;

        // 1. Pivot tablo adını tahmin et:
        // (örn: Role ve User -> role_user (alfabetik))
        $parentName = strtolower(class_basename(static::class)); // user
        $relatedName = strtolower(class_basename($instance)); // role
        $models = [$parentName, $relatedName];
        sort($models);
        $table = $table ?? implode('_', $models); // role_user

        // 2. Anahtarları tahmin et
        $foreignPivotKey = $foreignPivotKey ?? $parentName . '_id'; // user_id
        $relatedPivotKey = $relatedPivotKey ?? $relatedName . '_id'; // role_id
        $parentKey = $parentKey ?? $this->getKeyName(); // users.id
        $relatedKey = $relatedKey ?? $instance->getKeyName(); // roles.id

        return new BelongsToMany(
            $instance->newQuery(),
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey
        );
    }

    /**
     * Get relationship value
     *
     * @param string $key Relationship method name
     * @return mixed
     */
    protected function getRelationValue(string $key): mixed
    {
        // Check if already loaded
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        // Load relationship
        if (method_exists($this, $key)) {
            return $this->getRelationshipFromMethod($key);
        }

        return null;
    }

    /**
     * Load relationship from method
     */
    protected function getRelationshipFromMethod(string $method): mixed
    {
        $relation = $this->$method();

        // Execute relation and cache result
        $results = $relation->getResults();
        $this->relations[$method] = $results;

        return $results;
    }

    /**
     * Get foreign key name for this model
     *
     * @return string (e.g., 'user_id' for User model)
     */
    protected function getForeignKey(): string
    {
        return strtolower(class_basename(static::class)) . '_id';
    }

    /**
     * Guess belongs to foreign key from related model
     */
    protected function guessBelongsToForeignKey(string $related): string
    {
        $name = class_basename($related);
        return strtolower($name) . '_id';
    }

    /**
     * Set relationship value
     */
    public function setRelation(string $relation, mixed $value): self
    {
        $this->relations[$relation] = $value;
        return $this;
    }

    /**
     * Get all loaded relationships
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Check if relationship is loaded
     */
    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Eager load relationships
     *
     * @param array|string $relations
     * @return self
     *
     * @example
     * $users = User::with('posts', 'profile')->get();
     */
    public function load(array|string $relations): self
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        foreach ($relations as $relation) {
            if (!$this->relationLoaded($relation)) {
                $this->relations[$relation] = $this->getRelationshipFromMethod($relation);
            }
        }

        return $this;
    }
}