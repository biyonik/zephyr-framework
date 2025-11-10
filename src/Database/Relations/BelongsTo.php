<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsOne;

/**
 * Inverse One-to-Many İlişki
 *
 * ✅ DÜZELTME: Null pointer sorunları çözüldü!
 * ✅ DÜZELTME: Type safety eklendi!
 * ✅ DÜZELTME: Associate/dissociate metotları iyileştirildi!
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class BelongsTo extends Relation implements ReturnsOne
{
    protected string $foreignKey;
    protected string $ownerKey;

    public function __construct(
        Builder $query,
        Model $parent,
        string $foreignKey,
        string $ownerKey
    ) {
        parent::__construct($query, $parent); // ✅ Parent constructor model check yapar

        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;

        $this->addConstraints();
    }

    public function addConstraints(): void
    {
        $foreignKeyValue = $this->parent->getAttribute($this->foreignKey);

        if (!is_null($foreignKeyValue)) {
            $this->query->where($this->ownerKey, '=', $foreignKeyValue);
        } else {
            // ✅ DÜZELTME: Foreign key null ise empty result döndür
            $this->query->whereRaw('1 = 0');
        }
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = array_map(function ($model) {
            return $model->getAttribute($this->foreignKey);
        }, $models);

        $keys = array_unique(array_filter($keys, fn($key) => !is_null($key)));

        if (empty($keys)) {
            // ✅ DÜZELTME: Key yoksa empty result
            $this->query->whereRaw('1 = 0');
            return;
        }

        $this->query->whereIn($this->ownerKey, $keys);
    }

    /**
     * ✅ DÜZELTME: Null safe matching
     */
    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $foreignKeyValue = $model->getAttribute($this->foreignKey);

            if ($foreignKeyValue !== null && isset($dictionary[$foreignKeyValue])) {
                $model->setRelation($relation, $dictionary[$foreignKeyValue]);
            } else {
                $model->setRelation($relation, null);
            }
        }

        return $models;
    }

    /**
     * ✅ DÜZELTME: Dictionary building with null checks
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $ownerKeyValue = $result->getAttribute($this->ownerKey);
            
            // ✅ DÜZELTME: Null check
            if ($ownerKeyValue !== null) {
                $dictionary[$ownerKeyValue] = $result;
            }
        }

        return $dictionary;
    }

    /**
     * ✅ DÜZELTME: ReturnsOne interface - tek model döndürür
     */
    public function getResults(): ?Model
    {
        return $this->query->firstModel(); // ✅ QueryBuilder'da artık mevcut
    }

    /**
     * ✅ DÜZELTME: Associate metodu güvenli hale getirildi
     */
    public function associate(Model $model): Model
    {
        $ownerKeyValue = $model->getAttribute($this->ownerKey);
        
        if ($ownerKeyValue === null) {
            throw new \RuntimeException(
                "Cannot associate model: target model's {$this->ownerKey} is null"
            );
        }

        $this->parent->setAttribute($this->foreignKey, $ownerKeyValue);

        // ✅ DÜZELTME: Relation name'i dinamik bulma
        $relationName = $this->getRelationName();
        $this->parent->setRelation($relationName, $model);

        return $this->parent;
    }

    /**
     * ✅ DÜZELTME: Dissociate metodu güvenli hale getirildi
     */
    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);
        
        $relationName = $this->getRelationName();
        $this->parent->setRelation($relationName, null);

        return $this->parent;
    }

    /**
     * ✅ DÜZELTME: Relation name'i daha güvenli bulma
     */
    protected function getRelationName(): string
    {
        // Debug backtrace ile relation method adını bul
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        
        foreach ($backtrace as $trace) {
            if (isset($trace['function']) && 
                isset($trace['class']) && 
                $trace['class'] !== static::class &&
                $trace['class'] !== Relation::class &&
                !str_contains($trace['function'], '__')) {
                return $trace['function'];
            }
        }
        
        return 'relation'; // Fallback
    }

    /**
     * ✅ DÜZELTME: Update metodu güvenli hale getirildi
     */
    public function update(array $attributes): int
    {
        if (empty($attributes)) {
            return 0;
        }

        return $this->query->update($attributes);
    }

    /**
     * ✅ YENİ: İlişki var mı kontrol et
     */
    public function exists(): bool
    {
        return $this->query->exists();
    }

    /**
     * ✅ YENİ: İlişkili model'i first or create
     */
    public function firstOrCreate(array $attributes = []): Model
    {
        $model = $this->query->firstModel();
        
        if ($model) {
            return $model;
        }

        // Yeni model oluştur
        $newModel = $this->newModelInstance($attributes);
        $newModel->save();

        // Associate et
        $this->associate($newModel);

        return $newModel;
    }

    /**
     * ✅ YENİ: İlişkili model'i first or new  
     */
    public function firstOrNew(array $attributes = []): Model
    {
        $model = $this->query->firstModel();
        
        if ($model) {
            return $model;
        }

        return $this->newModelInstance($attributes);
    }
}