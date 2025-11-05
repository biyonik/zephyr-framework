<?php

declare(strict_types=1);

namespace Zephyr\Database;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Exception\MassAssignmentException;
use Zephyr\Database\Exception\ModelNotFoundException;
use Zephyr\Database\Concerns\HasAttributes;
use Zephyr\Database\Concerns\HasTimestamps;
use Zephyr\Database\Concerns\HasRelationships;

/**
 * Active Record Base Model
 *
 * Provides ORM functionality with:
 * - Mass assignment protection
 * - Attribute casting
 * - Dirty tracking
 * - Automatic timestamps
 * - Relationships
 * - Query builder integration
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
abstract class Model
{
    use HasAttributes;
    use HasTimestamps;
    use HasRelationships;

    /**
     * Database connection instance
     */
    protected ?Connection $connection = null;

    /**
     * Table name (override in child classes)
     */
    protected string $table = '';

    /**
     * Primary key column name
     */
    protected string $primaryKey = 'id';

    /**
     * Primary key type
     */
    protected string $keyType = 'int';

    /**
     * Whether primary key is auto-incrementing
     */
    public bool $incrementing = true;

    /**
     * Indicates if model exists in database
     */
    public bool $exists = false;

    /**
     * Indicates if model was recently created
     */
    public bool $wasRecentlyCreated = false;

    /**
     * Mass assignable attributes (whitelist)
     *
     * @example ['name', 'email', 'password']
     */
    protected array $fillable = [];

    /**
     * Guarded attributes (blacklist)
     *
     * @example ['id', 'is_admin', 'role']
     */
    protected array $guarded = ['*'];

    /**
     * Attributes to hide when converting to array/JSON
     *
     * @example ['password', 'remember_token']
     */
    protected array $hidden = [];

    /**
     * Attributes to always include when converting to array/JSON
     */
    protected array $visible = [];

    /**
     * Attribute type casting
     *
     * @example ['is_admin' => 'bool', 'metadata' => 'json']
     */
    protected array $casts = [];

    /**
     * Attributes that should be treated as dates
     */
    protected array $dates = [];

    /**
     * Default attribute values
     */
    protected array $attributes = [];

    /**
     * Original attribute values from database
     */
    protected array $original = [];

    /**
     * Model constructor
     *
     * @param array $attributes Initial attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->syncOriginal();
        $this->fill($attributes);
    }

    /**
     * Boot model (called once per model class)
     */
    protected function bootIfNotBooted(): void
    {
        // Can be overridden for model initialization
    }

    /**
     * Get table name
     */
    public function getTable(): string
    {
        if (!empty($this->table)) {
            return $this->table;
        }

        // Generate from class name: User -> users
        $className = class_basename(static::class);
        return strtolower($className) . 's';
    }

    /**
     * Get primary key name
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get primary key value
     */
    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Set primary key value
     */
    public function setKey(mixed $value): self
    {
        $this->setAttribute($this->getKeyName(), $value);
        return $this;
    }

    /**
     * Get database connection
     */
    public function getConnection(): Connection
    {
        if (is_null($this->connection)) {
            $this->connection = Connection::getInstance();
        }

        return $this->connection;
    }

    /**
     * Set database connection
     */
    public function setConnection(Connection $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Create a new query builder instance
     */
    public function newQuery(): Builder
    {
        return (new Builder($this->getConnection()))
            ->setModel($this)
            ->from($this->getTable());
    }

    /**
     * Fill model with attributes (mass assignment)
     *
     * @param array $attributes Attributes to fill
     * @return self
     * @throws MassAssignmentException
     */
    public function fill(array $attributes): self
    {
        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Get fillable attributes from array
     *
     * @param array $attributes
     * @return array
     * @throws MassAssignmentException
     */
    protected function fillableFromArray(array $attributes): array
    {
        // Check if totally guarded
        if (count($this->fillable) === 0 && $this->guarded === ['*']) {
            throw new MassAssignmentException(
                'Add fillable property to enable mass assignment on ' . static::class
            );
        }

        // Whitelist mode (fillable)
        if (count($this->fillable) > 0) {
            return array_intersect_key(
                $attributes,
                array_flip($this->fillable)
            );
        }

        // Blacklist mode (guarded)
        return array_diff_key(
            $attributes,
            array_flip($this->guarded)
        );
    }

    /**
     * Check if attribute is fillable
     */
    public function isFillable(string $key): bool
    {
        // Fillable whitelist
        if (in_array($key, $this->fillable, true)) {
            return true;
        }

        // Guarded blacklist
        if ($this->isGuarded($key)) {
            return false;
        }

        // Default behavior
        return empty($this->fillable) && !$this->isGuarded($key);
    }

    /**
     * Check if attribute is guarded
     */
    public function isGuarded(string $key): bool
    {
        return in_array($key, $this->guarded, true) || $this->guarded === ['*'];
    }

    /**
     * Sync original attributes with current
     */
    public function syncOriginal(): self
    {
        $this->original = $this->attributes; //
        
        // YENİ: (Rapor #1 Çözümü)
        // Modelin durumu "orijinal" haline döndüğünde,
        // (örn: save() veya refresh() sonrası)
        // hesaplanmış özellik önbelleğini temizle.
        $this->clearAttributeCache();
        
        return $this;
    }

    /**
     * Sync single original attribute
     */
    public function syncOriginalAttribute(string $key): self
    {
        $this->original[$key] = $this->attributes[$key] ?? null;
        return $this;
    }

    /**
     * Check if model or specific attributes have been modified
     *
     * @param array|string|null $attributes Attributes to check
     * @return bool
     */
    public function isDirty(array|string|null $attributes = null): bool
    {
        return $this->hasChanges(
            $this->getDirty(),
            is_array($attributes) ? $attributes : func_get_args()
        );
    }

    /**
     * Check if model or attributes are clean (not modified)
     */
    public function isClean(array|string|null $attributes = null): bool
    {
        return !$this->isDirty(...func_get_args());
    }

    /**
     * Get dirty attributes (modified attributes)
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original)) {
                $dirty[$key] = $value;
            } elseif ($value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Get changed attributes (after last save)
     */
    public function getChanges(): array
    {
        return $this->getDirty();
    }

    /**
     * Check if specific attributes have changes
     */
    protected function hasChanges(array $changes, array $attributes = []): bool
    {
        // Check all attributes
        if (empty($attributes)) {
            return count($changes) > 0;
        }

        // Check specific attributes
        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $changes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Save model to database
     *
     * @return bool Success status
     */
    public function save(): bool
    {
        // Perform INSERT or UPDATE
        if ($this->exists) {
            $saved = $this->performUpdate();
        } else {
            $saved = $this->performInsert();
        }

        // Sync original if saved
        if ($saved) {
            $this->syncOriginal();
        }

        return $saved;
    }

    /**
     * Perform INSERT operation
     */
    protected function performInsert(): bool
    {
        // Add timestamps if enabled
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // Get attributes for insert
        $attributes = $this->getAttributesForInsert();

        if (empty($attributes)) {
            return true;
        }

        // Insert and get ID
        $id = $this->newQuery()->insertGetId($attributes);

        // Set primary key if auto-incrementing
        if ($this->incrementing) {
            $this->setAttribute($this->getKeyName(), $id);
        }

        // Mark as existing
        $this->exists = true;
        $this->wasRecentlyCreated = true;

        return true;
    }

    /**
     * Perform UPDATE operation
     */
    protected function performUpdate(): bool
    {
        // Check if anything changed
        $dirty = $this->getDirty();

        if (count($dirty) === 0) {
            return true; // Nothing to update
        }

        // Update timestamps if enabled
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
            $dirty = $this->getDirty();
        }

        // Perform update
        $affected = $this->newQuery()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->update($dirty);

        return $affected > 0;
    }

    /**
     * Get attributes for insert
     */
    protected function getAttributesForInsert(): array
    {
        return $this->attributes;
    }

    /**
     * Delete model from database
     *
     * @return bool Success status
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        // Perform delete
        $deleted = $this->newQuery()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->delete();

        if ($deleted > 0) {
            $this->exists = false;
            return true;
        }

        return false;
    }

    /**
     * Refresh model from database
     */
    public function refresh(): self
    {
        if (!$this->exists) {
            return $this;
        }

        $fresh = $this->newQuery()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->first();

        if ($fresh) {
            $this->attributes = $fresh;
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * Replicate model instance
     */
    public function replicate(array $except = []): static
    {
        $attributes = array_diff_key(
            $this->attributes,
            array_flip(array_merge([
                $this->getKeyName(),
            ], $except))
        );

        $instance = new static;
        $instance->fill($attributes);
        $instance->exists = false;

        return $instance;
    }

    /**
     * Convert model to array
     */
    public function toArray(): array
    {
        $attributes = $this->attributesToArray();

        // Apply hidden/visible
        if (!empty($this->visible)) {
            $attributes = array_intersect_key($attributes, array_flip($this->visible));
        }

        if (!empty($this->hidden)) {
            $attributes = array_diff_key($attributes, array_flip($this->hidden));
        }

        return $attributes;
    }

    /**
     * Convert model to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Create new instance from database row
     */
    public function newFromBuilder(array $attributes): static
    {
        $instance = new static;
        $instance->exists = true;
        $instance->attributes = $attributes;
        $instance->syncOriginal();

        return $instance;
    }

    /**
     * Static: Create new model with attributes
     *
     * @param array $attributes
     * @return static
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    /**
     * Static: Find model by primary key
     *
     * @param mixed $id Primary key value
     * @return static|null
     */
    public static function find(mixed $id): ?static
    {
        return static::query()
            ->where((new static)->getKeyName(), '=', $id)
            ->first();
    }

    /**
     * Static: Find model or throw exception
     *
     * @param mixed $id
     * @return static
     * @throws ModelNotFoundException
     */
    public static function findOrFail(mixed $id): static
    {
        $model = static::find($id);

        if (is_null($model)) {
            throw new ModelNotFoundException(
                'Model ' . static::class . ' with ID ' . $id . ' not found'
            );
        }

        return $model;
    }

    /**
     * Static: Get all models
     */
    public static function all(array $columns = ['*']): array
    {
        return static::query()->select(...$columns)->get();
    }

    /**
     * Static: Create query builder
     */
    public static function query(): Builder
    {
        return (new static)->newQuery();
    }

    /**
     * Static method forwarding to query builder
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Instance method forwarding to query builder
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->newQuery()->$method(...$parameters);
    }

    /**
     * Magic getter for attributes
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Magic setter for attributes
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Magic isset check
     */
    public function __isset(string $key): bool
    {
        return !is_null($this->getAttribute($key));
    }

    /**
     * Magic unset
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}