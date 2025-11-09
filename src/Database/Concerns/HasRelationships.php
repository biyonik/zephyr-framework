<?php

declare(strict_types=1);

namespace Zephyr\Database\Concerns;

use Zephyr\Database\Relations\HasMany;
use Zephyr\Database\Relations\BelongsTo;
use Zephyr\Database\Relations\HasOne;
use Zephyr\Database\Relations\BelongsToMany;

/**
 * Has Relationships Trait
 *
 * Model ilişkilerini yönetir:
 * - hasMany: One-to-many ilişkisi
 * - belongsTo: hasMany'nin tersi (inverse)
 * - hasOne: One-to-one ilişkisi
 * - belongsToMany: Many-to-many ilişkisi
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
trait HasRelationships
{
    /**
     * Yüklenmiş ilişkiler
     * @var array
     */
    protected array $relations = [];

    /**
     * One-to-many ilişki tanımlar
     *
     * @param string $related İlişkili model sınıfı
     * @param string|null $foreignKey İlişkili modeldeki foreign key
     * @param string|null $localKey Bu modeldeki local key
     * @return HasMany
     *
     * @example
     * class User extends Model {
     *     public function posts() {
     *         return $this->hasMany(Post::class);
     *     }
     * }
     *
     * Kullanım:
     * $user->posts; // Tüm post'ları döndürür
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
     * Inverse one-to-many ilişki tanımlar
     *
     * @param string $related İlişkili model sınıfı
     * @param string|null $foreignKey Bu modeldeki foreign key
     * @param string|null $ownerKey İlişkili modeldeki owner key
     * @return BelongsTo
     *
     * @example
     * class Post extends Model {
     *     public function user() {
     *         return $this->belongsTo(User::class);
     *     }
     * }
     *
     * Kullanım:
     * $post->user; // User modelini döndürür
     */
    public function belongsTo(
        string $related,
        ?string $foreignKey = null,
        ?string $ownerKey = null
    ): BelongsTo {
        $instance = new $related;

        // Default foreign key: user_id (metot adından veya related model'den)
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
     * One-to-one ilişki tanımlar
     *
     * @param string $related İlişkili model sınıfı
     * @param string|null $foreignKey İlişkili modeldeki foreign key
     * @param string|null $localKey Bu modeldeki local key
     * @return HasOne
     *
     * @example
     * class User extends Model {
     *     public function profile() {
     *         return $this->hasOne(Profile::class);
     *     }
     * }
     *
     * Kullanım:
     * $user->profile; // Profile modelini döndürür
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
     * Many-to-many ilişki tanımlar
     *
     * @param string $related İlişkili model sınıfı
     * @param string|null $table Pivot tablo adı
     * @param string|null $foreignPivotKey Bu modelin pivot'taki foreign key'i
     * @param string|null $relatedPivotKey İlişkili modelin pivot'taki foreign key'i
     * @param string|null $parentKey Bu modelin local key'i
     * @param string|null $relatedKey İlişkili modelin local key'i
     * @return BelongsToMany
     *
     * @example
     * class User extends Model {
     *     public function roles() {
     *         return $this->belongsToMany(Role::class);
     *     }
     * }
     *
     * Kullanım:
     * $user->roles; // Tüm rolleri döndürür
     * $user->roles()->attach(1);
     * $user->roles()->detach(1);
     * $user->roles()->sync([1, 2, 3]);
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

        // 1. Pivot tablo adını tahmin et (alfabetik sıralama)
        // Örn: Role ve User -> role_user
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
     * İlişki değerini döndürür
     *
     * @param string $key İlişki metot adı
     * @return mixed
     */
    protected function getRelationValue(string $key): mixed
    {
        // Zaten yüklü mü kontrol et
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        // İlişkiyi yükle
        if (method_exists($this, $key)) {
            return $this->getRelationshipFromMethod($key);
        }

        return null;
    }

    /**
     * Metottan ilişkiyi yükler
     *
     * @param string $method
     * @return mixed
     */
    protected function getRelationshipFromMethod(string $method): mixed
    {
        $relation = $this->$method();

        // İlişkiyi çalıştır ve cache'le
        $results = $relation->getResults();
        $this->relations[$method] = $results;

        return $results;
    }

    /**
     * Bu model için foreign key adını döndürür
     *
     * @return string Örn: User modeli için 'user_id'
     */
    protected function getForeignKey(): string
    {
        return strtolower(class_basename(static::class)) . '_id';
    }

    /**
     * BelongsTo için foreign key'i tahmin eder
     *
     * @param string $related
     * @return string
     */
    protected function guessBelongsToForeignKey(string $related): string
    {
        $name = class_basename($related);
        return strtolower($name) . '_id';
    }

    /**
     * İlişki değerini set eder
     *
     * @param string $relation
     * @param mixed $value
     * @return self
     */
    public function setRelation(string $relation, mixed $value): self
    {
        $this->relations[$relation] = $value;
        return $this;
    }

    /**
     * Tüm yüklenmiş ilişkileri döndürür
     *
     * @return array
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * İlişki önceden döndürülmüş yüklenmiş mi kontrol eder
     *
     * @param string $key
     * @return bool
     */
    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Belirli bir ilişkiyi döndürür
     *
     * @param string $key
     * @return mixed
     */
    public function getRelation(string $key): mixed
    {
        return $this->relations[$key] ?? null;
    }

    /**
     * İlişkileri eager load eder
     *
     * @param array|string $relations
     * @return self
     *
     * @example
     * $user->load('posts', 'profile');
     * $users = User::all();
     * $users->load('posts');
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