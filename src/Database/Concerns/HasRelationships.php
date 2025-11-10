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
 * - belongsTo: Inverse one-to-many
 * - hasOne: One-to-one ilişkisi
 * - belongsToMany: Many-to-many ilişkisi
 * - Eager loading
 * - Lazy loading
 * - Relation counting
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
trait HasRelationships
{
    /**
     * Yüklenmiş ilişkiler
     */
    protected array $relations = [];

    /**
     * One-to-many ilişki tanımlar
     */
    public function hasMany(
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null
    ): HasMany {
        $instance = new $related;

        $foreignKey = $foreignKey ?? $this->getForeignKey();
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
     */
    public function belongsTo(
        string $related,
        ?string $foreignKey = null,
        ?string $ownerKey = null
    ): BelongsTo {
        $instance = new $related;

        $foreignKey = $foreignKey ?? $this->guessBelongsToForeignKey($related);
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

        // Pivot tablo adını tahmin et
        $parentName = strtolower(class_basename(static::class));
        $relatedName = strtolower(class_basename($instance));
        $models = [$parentName, $relatedName];
        sort($models);
        $table = $table ?? implode('_', $models);

        // Anahtarları tahmin et
        $foreignPivotKey = $foreignPivotKey ?? $parentName . '_id';
        $relatedPivotKey = $relatedPivotKey ?? $relatedName . '_id';
        $parentKey = $parentKey ?? $this->getKeyName();
        $relatedKey = $relatedKey ?? $instance->getKeyName();

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
     * Metottan ilişkiyi yükler
     */
    protected function getRelationshipFromMethod(string $method): mixed
    {
        $relation = $this->$method();

        // İlişkiyi çalıştır ve cache'le
        $results = $relation->getResults();
        $this->setRelation($method, $results);

        return $results;
    }

    /**
     * Bu model için foreign key adını döndürür
     */
    protected function getForeignKey(): string
    {
        return strtolower(class_basename(static::class)) . '_id';
    }

    /**
     * BelongsTo için foreign key'i tahmin eder
     */
    protected function guessBelongsToForeignKey(string $related): string
    {
        $name = class_basename($related);
        return strtolower($name) . '_id';
    }

    /**
     * İlişki değerini set eder
     */
    public function setRelation(string $relation, mixed $value): self
    {
        $this->relations[$relation] = $value;
        return $this;
    }

    /**
     * Belirli bir ilişkiyi kaldırır
     */
    public function unsetRelation(string $relation): self
    {
        unset($this->relations[$relation]);
        return $this;
    }

    /**
     * Tüm yüklenmiş ilişkileri döndürür
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * İlişki önceden yüklenmiş mi?
     */
    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Belirli bir ilişkiyi döndürür
     */
    public function getRelation(string $key): mixed
    {
        return $this->relations[$key] ?? null;
    }

    /**
     * İlişkileri eager load eder
     * 
     * @param array|string $relations İlişki adları
     * @return self
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

    /**
     * Sadece yüklenmemiş ilişkileri yükler
     * 
     * @param array|string $relations İlişki adları
     * @return self
     */
    public function loadMissing(array|string $relations): self
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        foreach ($relations as $relation) {
            if (!$this->relationLoaded($relation)) {
                $this->load($relation);
            }
        }

        return $this;
    }

    /**
     * İlişki sayısını yükler (relation_count attribute)
     * 
     * @param array|string $relations İlişki adları
     * @return self
     */
    public function loadCount(array|string $relations): self
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        foreach ($relations as $relation) {
            if (!method_exists($this, $relation)) {
                continue;
            }

            $query = $this->$relation();
            $count = $query->count();

            // relation_count olarak attribute'a ekle
            $this->attributes[$relation . '_count'] = $count;
        }

        return $this;
    }

    /**
     * Collection için eager loading helper
     * 
     * Bu metot Collection'dan çağrılır
     */
    public static function loadRelationsForCollection(array $models, array|string $relations): void
    {
        if (empty($models)) {
            return;
        }

        $relations = is_array($relations) ? $relations : func_get_args();
        array_shift($relations); // İlk parametre $models

        // İlk model'i al
        $model = reset($models);

        foreach ($relations as $relation) {
            // İlişki query'sini al
            $query = $model->$relation();

            // Eager constraints ekle
            $query->addEagerConstraints($models);

            // Sonuçları al
            $results = $query->get();

            // Sonuçları eşleştir
            $models = $query->match($models, $results->all(), $relation);
        }
    }
}