<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsMany;
use Zephyr\Support\Collection;
use Zephyr\Database\QueryBuilder;

/**
 * Many-to-Many İlişki (Pivot Table ile)
 *
 * ✅ DÜZELTME: Null pointer sorunları çözüldü!
 * ✅ DÜZELTME: Pivot table operations güvenli hale getirildi!
 * ✅ DÜZELTME: Type safety eklendi!
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class BelongsToMany extends Relation implements ReturnsMany
{
    protected string $table;
    protected string $foreignPivotKey;
    protected string $relatedPivotKey;
    protected string $parentKey;
    protected string $relatedKey;
    protected array $pivotColumns = [];
    protected bool $withTimestamps = false;

    public function __construct(
        Builder $query,
        Model $parent,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey
    ) {
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;

        parent::__construct($query, $parent); // ✅ Parent constructor model check yapar

        $this->addConstraints();
    }

    public function addConstraints(): void
    {
        $this->performJoin();

        $parentKeyValue = $this->parent->getAttribute($this->parentKey);
        
        if ($parentKeyValue !== null) {
            $this->query->where(
                $this->table . '.' . $this->foreignPivotKey,
                '=',
                $parentKeyValue
            );
        } else {
            // ✅ DÜZELTME: Parent key null ise empty result
            $this->query->whereRaw('1 = 0');
        }
    }

    public function addEagerConstraints(array $models): void
    {
        $this->performJoin();

        $parentIds = array_map(function ($model) {
            return $model->getAttribute($this->parentKey);
        }, $models);

        $parentIds = array_unique(array_filter($parentIds, fn($id) => !is_null($id)));

        if (empty($parentIds)) {
            // ✅ DÜZELTME: ID yoksa empty result
            $this->query->whereRaw('1 = 0');
            return;
        }

        $this->query->whereIn(
            $this->table . '.' . $this->foreignPivotKey,
            $parentIds
        );
    }

    /**
     * ✅ DÜZELTME: Join işlemi güvenli hale getirildi
     */
    protected function performJoin(): void
    {
        // ✅ DÜZELTME: Related table'ı güvenli al
        $relatedTable = $this->getModelSafely()->getTable();

        $this->query->select($relatedTable . '.*');

        foreach ($this->aliasedPivotColumns() as $pivotSelect) {
            $this->query->addSelect(\Zephyr\Database\Query\Expression::make($pivotSelect));
        }

        $this->query->join(
            $this->table,
            $relatedTable . '.' . $this->relatedKey,
            '=',
            $this->table . '.' . $this->relatedPivotKey
        );
    }

    /**
     * ✅ DÜZELTME: Match işlemi güvenli hale getirildi
     */
    public function match(array $models, array $results, string $relation): array
    {
        $results = $this->hydratePivotRelation($results);
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);

            if ($key !== null && isset($dictionary[$key])) {
                // ✅ DÜZELTME: Güvenli collection oluşturma
                $collection = $this->newCollection($dictionary[$key]);
                $model->setRelation($relation, $collection);
            } else {
                // ✅ DÜZELTME: Empty collection
                $model->setRelation($relation, $this->newCollection([]));
            }
        }

        return $models;
    }

    /**
     * ✅ DÜZELTME: Pivot attributes'ları güvenli hydrate etme
     */
    protected function hydratePivotRelation(array $results): array
    {
        foreach ($results as $model) {
            $pivotAttributes = [];
            $attributes = $model->getAttributes();

            foreach ($attributes as $key => $value) {
                if (str_starts_with($key, 'pivot_')) {
                    $pivotKey = substr($key, 6);
                    $pivotAttributes[$pivotKey] = $value;

                    // ✅ DÜZELTME: Attribute'u güvenli şekilde kaldır
                    unset($model->{$key});
                }
            }

            if (!empty($pivotAttributes)) {
                $model->setRelation('pivot', (object) $pivotAttributes);
            }
        }

        return $results;
    }

    /**
     * ✅ DÜZELTME: Dictionary building with null checks
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $pivotData = $result->getRelation('pivot');
            
            if (!$pivotData || !is_object($pivotData)) {
                continue;
            }

            $key = $pivotData->{$this->foreignPivotKey} ?? null;

            if ($key === null) {
                continue;
            }

            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }

            $dictionary[$key][] = $result;
        }

        return $dictionary;
    }

    /**
     * ✅ DÜZELTME: Güvenli collection döndürme
     */
    public function getResults(): Collection
    {
        $results = $this->query->getModels();

        return $this->newCollection(
            $this->hydratePivotRelation($results->all())
        );
    }

    /**
     * Pivot columns ekle
     */
    public function withPivot(array $columns): self
    {
        $this->pivotColumns = array_merge($this->pivotColumns, $columns);

        // ✅ DÜZELTME: Yeni query oluşturmadan önce model kontrolü
        $this->query = $this->getModelSafely()->newQuery();
        $this->addConstraints();

        return $this;
    }

    /**
     * Timestamps ekle
     */
    public function withTimestamps(): self
    {
        $this->withTimestamps = true;
        return $this->withPivot(['created_at', 'updated_at']);
    }

    protected function aliasedPivotColumns(): array
    {
        $aliased = [
            $this->table . '.' . $this->foreignPivotKey . ' as pivot_' . $this->foreignPivotKey,
            $this->table . '.' . $this->relatedPivotKey . ' as pivot_' . $this->relatedPivotKey,
        ];

        foreach ($this->pivotColumns as $column) {
            $aliased[] = $this->table . '.' . $column . ' as pivot_' . $column;
        }

        return array_unique($aliased);
    }

    /**
     * ✅ DÜZELTME: Attach metodu güvenli hale getirildi
     */
    public function attach(mixed $id, array $attributes = []): void
    {
        // ✅ DÜZELTME: Parent key validation
        $parentKeyValue = $this->parent->getAttribute($this->parentKey);
        if ($parentKeyValue === null) {
            throw new \RuntimeException(
                "Cannot attach: parent model's {$this->parentKey} is null"
            );
        }

        $ids = is_array($id) ? $id : [$id];
        $records = [];
        $now = date('Y-m-d H:i:s');

        foreach ($ids as $relatedId) {
            if ($relatedId === null) {
                continue; // ✅ DÜZELTME: Null ID'leri skip et
            }

            $record = $this->createPivotRecord($relatedId, $attributes, $now);
            
            // Duplicate kontrolü
            if (!$this->pivotRecordExists($relatedId)) {
                $records[] = $record;
            }
        }

        if (!empty($records)) {
            $this->newPivotQuery()->insertMultiple($records);
        }
    }

    /**
     * ✅ DÜZELTME: Detach metodu güvenli hale getirildi
     */
    public function detach(mixed $id = null): int
    {
        $query = $this->newPivotQuery();

        if (!is_null($id)) {
            $ids = is_array($id) ? $id : [$id];
            $ids = array_filter($ids, fn($id) => !is_null($id)); // ✅ DÜZELTME: Null filter
            
            if (empty($ids)) {
                return 0;
            }
            
            $query->whereIn($this->relatedPivotKey, $ids);
        }

        return $query->delete();
    }

    /**
     * ✅ DÜZELTME: Sync metodu güvenli hale getirildi
     */
    public function sync(array $ids): array
    {
        // ✅ DÜZELTME: Parent key validation
        $parentKeyValue = $this->parent->getAttribute($this->parentKey);
        if ($parentKeyValue === null) {
            throw new \RuntimeException(
                "Cannot sync: parent model's {$this->parentKey} is null"
            );
        }

        $current = $this->getCurrentPivotIds();
        $new = $this->formatSyncIds($ids);

        // Önce detach, sonra attach (sıra önemli!)
        $toDetach = array_diff_key($current, $new);
        $toAttach = array_diff_key($new, $current);

        $results = [
            'attached' => [],
            'detached' => [],
            'updated' => [],
        ];

        if (count($toDetach) > 0) {
            $this->detach(array_keys($toDetach));
            $results['detached'] = array_keys($toDetach);
        }

        if (count($toAttach) > 0) {
            foreach ($toAttach as $id => $attributes) {
                $this->attach($id, $attributes);
            }
            $results['attached'] = array_keys($toAttach);
        }

        return $results;
    }

    /**
     * ✅ DÜZELTME: Pivot record existence check güvenli
     */
    protected function pivotRecordExists(mixed $relatedId): bool
    {
        if ($relatedId === null) {
            return false;
        }

        return $this->newPivotQuery()
            ->where($this->relatedPivotKey, '=', $relatedId)
            ->exists();
    }

    /**
     * ✅ DÜZELTME: Pivot record creation güvenli
     */
    protected function createPivotRecord(mixed $relatedId, array $attributes, string $now): array
    {
        $parentKeyValue = $this->parent->getAttribute($this->parentKey);
        
        $record = [
            $this->foreignPivotKey => $parentKeyValue,
            $this->relatedPivotKey => $relatedId,
        ];

        if ($this->withTimestamps) {
            $record['created_at'] = $now;
            $record['updated_at'] = $now;
        }

        return array_merge($record, $attributes);
    }

    /**
     * ✅ DÜZELTME: Current pivot IDs güvenli alma
     */
    protected function getCurrentPivotIds(): array
    {
        $results = $this->newPivotQuery()
            ->select($this->relatedPivotKey)
            ->get();

        $ids = [];
        foreach ($results as $row) {
            $id = $row[$this->relatedPivotKey] ?? null;
            if ($id !== null) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    protected function formatSyncIds(array $ids): array
    {
        $formatted = [];
        foreach ($ids as $key => $value) {
            if (is_numeric($key)) {
                if ($value !== null) { // ✅ DÜZELTME: Null check
                    $formatted[$value] = [];
                }
            } else {
                if ($key !== null) { // ✅ DÜZELTME: Null check
                    $formatted[$key] = $value;
                }
            }
        }
        return $formatted;
    }

    /**
     * ✅ DÜZELTME: Pivot query güvenli oluşturma
     */
    protected function newPivotQuery(): QueryBuilder
    {
        $connection = $this->getModelSafely()->getConnection();
        $query = new QueryBuilder($connection);
        
        $parentKeyValue = $this->parent->getAttribute($this->parentKey);
        if ($parentKeyValue === null) {
            throw new \RuntimeException(
                "Cannot create pivot query: parent model's {$this->parentKey} is null"
            );
        }

        return $query
            ->from($this->table)
            ->where($this->foreignPivotKey, '=', $parentKeyValue);
    }
}