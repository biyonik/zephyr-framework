<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsMany;
use Zephyr\Support\Collection;

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

        parent::__construct($query, $parent);

        $this->addConstraints();
    }

    /**
     * Lazy loading için sorguya kısıtları ekler.
     */
    public function addConstraints(): void
    {
        $this->performJoin();

        $this->query->where(
            $this->table . '.' . $this->foreignPivotKey,
            '=',
            $this->parent->getAttribute($this->parentKey)
        );
    }

    /**
     * Eager loading için sorguya kısıtları ekler.
     */
    public function addEagerConstraints(array $models): void
    {
        $this->performJoin();

        $parentIds = array_map(function ($model) {
            return $model->getAttribute($this->parentKey);
        }, $models);

        $this->query->whereIn(
            $this->table . '.' . $this->foreignPivotKey,
            array_unique(array_filter($parentIds))
        );
    }

    /**
     * İlişkili sorgu için JOIN ve SELECT oluşturur.
     */
    protected function performJoin(): void
    {
        $relatedTable = $this->query->getModel()->getTable();

        // Temel SELECT: 'roles.*'
        $this->query->select($relatedTable . '.*');

        // ✅ Pivot sütunlarını SELECT'e ekle
        $pivotSelects = $this->aliasedPivotColumns();
        foreach ($pivotSelects as $pivotSelect) {
            $this->query->addSelect(\Zephyr\Database\Query\Expression::make($pivotSelect));
        }

        // JOIN
        $this->query->join(
            $this->table,
            $relatedTable . '.' . $this->relatedKey,
            '=',
            $this->table . '.' . $this->relatedPivotKey
        );
    }

    /**
     * ✅ FIX: Eager loading sonrası sonuçları üst modellerle eşleştirir.
     * Pivot attributes'leri modellere inject eder.
     */
    public function match(array $models, array $results, string $relation): array
    {
        // ✅ Önce pivot attributes'leri extract et
        $results = $this->hydratePivotRelation($results);

        // Sonuçları pivot key'e göre grupla
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);

            if (isset($dictionary[$key])) {
                // ✅ Collection döndür
                $collection = $this->query->getModel()->newCollection($dictionary[$key]);
                $model->setRelation($relation, $collection);
            } else {
                $model->setRelation($relation, $this->query->getModel()->newCollection([]));
            }
        }

        return $models;
    }

    /**
     * ✅ YENİ: Pivot attributes'leri ayıklar ve modellere 'pivot' property'si olarak ekler.
     */
    protected function hydratePivotRelation(array $results): array
    {
        foreach ($results as $model) {
            $pivotAttributes = [];

            // Model'deki tüm attribute'ları kontrol et
            $attributes = $model->getAttributes();

            foreach ($attributes as $key => $value) {
                // 'pivot_' ile başlayan attribute'ları bul
                if (str_starts_with($key, 'pivot_')) {
                    $pivotKey = substr($key, 6); // 'pivot_' prefix'ini kaldır
                    $pivotAttributes[$pivotKey] = $value;

                    // Model attribute'undan kaldır (temizlik)
                    unset($model->$key);
                }
            }

            // ✅ Pivot attributes'leri 'pivot' property'sine set et
            if (!empty($pivotAttributes)) {
                $model->setRelation('pivot', (object) $pivotAttributes);
            }
        }

        return $results;
    }

    /**
     * ✅ FIX: Dictionary'de pivot key'e göre grupla.
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            // ✅ Pivot'tan foreign key'i al
            $pivotData = $result->getRelation('pivot');
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
     * ✅ FIX: Sonuçları Collection olarak döndürür.
     */
    public function getResults(): Collection
    {
        $results = $this->query->get();

        // ✅ Pivot attributes'leri hydrate et
        return $this->query->getModel()->newCollection(
            $this->hydratePivotRelation($results->all())
        );
    }

    /**
     * Pivot tablodan alınacak ekstra sütunları belirler.
     */
    public function withPivot(array $columns): self
    {
        $this->pivotColumns = array_merge($this->pivotColumns, $columns);

        // Sorguyu yeniden oluştur
        $this->query = $this->query->getModel()->newQuery();
        $this->addConstraints();

        return $this;
    }

    /**
     * Pivot tabloya timestamps ekler.
     */
    public function withTimestamps(): self
    {
        $this->pivotColumns = array_merge(
            $this->pivotColumns,
            ['created_at', 'updated_at']
        );
        $this->withTimestamps = true;

        return $this->withPivot(['created_at', 'updated_at']);
    }

    /**
     * Pivot sütunlarını alias ile formatlar.
     */
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

    /*
    |--------------------------------------------------------------------------
    | PIVOT YÖNETİM METOTLARI
    |--------------------------------------------------------------------------
    */

    /**
     * Pivot tabloya ilişki ekler.
     */
    public function attach(mixed $id, array $attributes = []): void
    {
        $ids = is_array($id) ? $id : [$id];
        $records = [];
        $now = date('Y-m-d H:i:s');

        foreach ($ids as $relatedId) {
            $record = $this->createPivotRecord($relatedId, $attributes, $now);
            $records[] = $record;
        }

        $this->newPivotQuery()->insertMultiple($records);
    }

    /**
     * Pivot tablodan ilişkiyi kaldırır.
     */
    public function detach(mixed $id = null): int
    {
        $query = $this->newPivotQuery();

        if (!is_null($id)) {
            $ids = is_array($id) ? $id : [$id];
            $query->whereIn($this->relatedPivotKey, $ids);
        }

        return $query->delete();
    }

    /**
     * Pivot tablodaki ilişkileri senkronize eder.
     */
    public function sync(array $ids): array
    {
        $current = $this->getCurrentPivotIds();
        $new = $this->formatSyncIds($ids);

        $toAttach = array_diff_key($new, $current);
        $toDetach = array_diff_key($current, $new);

        $results = [
            'attached' => [],
            'detached' => [],
            'updated' => [],
        ];

        if (count($toAttach) > 0) {
            $this->attach(array_keys($toAttach), []);
            $results['attached'] = array_keys($toAttach);
        }

        if (count($toDetach) > 0) {
            $this->detach(array_keys($toDetach));
            $results['detached'] = array_keys($toDetach);
        }

        return $results;
    }

    /**
     * Pivot kaydı oluşturur.
     */
    protected function createPivotRecord(mixed $relatedId, array $attributes, string $now): array
    {
        $record = [
            $this->foreignPivotKey => $this->parent->getAttribute($this->parentKey),
            $this->relatedPivotKey => $relatedId,
        ];

        if ($this->withTimestamps) {
            $record['created_at'] = $now;
            $record['updated_at'] = $now;
        }

        return array_merge($record, $attributes);
    }

    /**
     * Mevcut pivot ID'lerini alır.
     */
    protected function getCurrentPivotIds(): array
    {
        $results = $this->newPivotQuery()
            ->select($this->relatedPivotKey)
            ->get();

        $ids = [];
        foreach ($results as $row) {
            $ids[$row[$this->relatedPivotKey]] = true;
        }

        return $ids;
    }

    /**
     * Sync ID'lerini formatlar.
     */
    protected function formatSyncIds(array $ids): array
    {
        $formatted = [];
        foreach ($ids as $key => $value) {
            if (is_numeric($key)) {
                $formatted[$value] = [];
            } else {
                $formatted[$key] = $value;
            }
        }
        return $formatted;
    }

    /**
     * Pivot tablo için query oluşturur.
     */
    protected function newPivotQuery(): \Zephyr\Database\QueryBuilder
    {
        $query = new \Zephyr\Database\QueryBuilder($this->query->getModel()->getConnection());

        return $query
            ->from($this->table)
            ->where($this->foreignPivotKey, '=', $this->parent->getAttribute($this->parentKey));
    }
}