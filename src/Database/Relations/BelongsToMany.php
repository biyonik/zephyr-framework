<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsMany;
use Zephyr\Support\Collection;
use Zephyr\Database\QueryBuilder;

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

    public function addConstraints(): void
    {
        $this->performJoin();

        $this->query->where(
            $this->table . '.' . $this->foreignPivotKey,
            '=',
            $this->parent->getAttribute($this->parentKey)
        );
    }

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

    protected function performJoin(): void
    {
        $relatedTable = $this->query->getModel()?->getTable();

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

    public function match(array $models, array $results, string $relation): array
    {
        $results = $this->hydratePivotRelation($results);
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);

            if (isset($dictionary[$key])) {
                $collection = $this->query->getModel()?->newCollection($dictionary[$key]);
                $model->setRelation($relation, $collection);
            } else {
                $model->setRelation($relation, $this->query->getModel()?->newCollection([]));
            }
        }

        return $models;
    }

    protected function hydratePivotRelation(array $results): array
    {
        foreach ($results as $model) {
            $pivotAttributes = [];

            $attributes = $model->getAttributes();

            foreach ($attributes as $key => $value) {
                if (str_starts_with($key, 'pivot_')) {
                    $pivotKey = substr($key, 6);
                    $pivotAttributes[$pivotKey] = $value;

                    unset($model->$key);
                }
            }

            if (!empty($pivotAttributes)) {
                $model->setRelation('pivot', (object) $pivotAttributes);
            }
        }

        return $results;
    }

    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
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

    public function getResults(): Collection
    {
        $results = $this->query->get();

        return $this->query->getModel()?->newCollection(
            $this->hydratePivotRelation($results->all())
        );
    }

    public function withPivot(array $columns): self
    {
        $this->pivotColumns = array_merge($this->pivotColumns, $columns);

        $this->query = $this->query->getModel()?->newQuery();
        $this->addConstraints();

        return $this;
    }

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
     * Pivot tabloya ilişki ekler
     */
    public function attach(mixed $id, array $attributes = []): void
    {
        $ids = is_array($id) ? $id : [$id];
        $records = [];
        $now = date('Y-m-d H:i:s');

        foreach ($ids as $relatedId) {
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
     * Pivot tablodan ilişkiyi kaldırır
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
     * İlişkileri senkronize eder
     */
    public function sync(array $ids): array
    {
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

    protected function pivotRecordExists(mixed $relatedId): bool
    {
        return $this->newPivotQuery()
            ->where($this->relatedPivotKey, '=', $relatedId)
            ->exists();
    }

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

    protected function newPivotQuery(): QueryBuilder
    {
        $query = new QueryBuilder($this->query->getModel()?->getConnection());

        return $query
            ->from($this->table)
            ->where($this->foreignPivotKey, '=', $this->parent->getAttribute($this->parentKey));
    }
}