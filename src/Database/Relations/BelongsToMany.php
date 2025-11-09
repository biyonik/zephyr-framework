<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsMany;
use Zephyr\Support\Collection;
use Zephyr\Database\QueryBuilder;

/**
 * Belongs To Many Relation
 *
 * Many-to-many ilişkisini temsil eder (Bir kullanıcının birden fazla rolü,
 * bir rolün birden fazla kullanıcısı var).
 *
 * Pivot tablo kullanır (örn: role_user).
 *
 * Kullanım:
 * class User extends Model {
 *     public function roles() {
 *         return $this->belongsToMany(Role::class);
 *     }
 * }
 *
 * Çağırma:
 * $user->roles; // Collection<Role>
 * $user->roles()->attach(1);
 * $user->roles()->detach(1);
 * $user->roles()->sync([1, 2, 3]);
 *
 * Pivot Attributes:
 * $user->roles->first()->pivot->created_at;
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class BelongsToMany extends Relation implements ReturnsMany
{
    /**
     * Pivot tablo adı
     */
    protected string $table;

    /**
     * Üst modelin pivot'taki foreign key'i (örn: 'user_id')
     */
    protected string $foreignPivotKey;

    /**
     * İlişkili modelin pivot'taki foreign key'i (örn: 'role_id')
     */
    protected string $relatedPivotKey;

    /**
     * Üst modelin local key'i (örn: 'id')
     */
    protected string $parentKey;

    /**
     * İlişkili modelin local key'i (örn: 'id')
     */
    protected string $relatedKey;

    /**
     * Pivot'tan alınacak ekstra sütunlar
     */
    protected array $pivotColumns = [];

    /**
     * Pivot'ta timestamps var mı?
     */
    protected bool $withTimestamps = false;

    /**
     * Constructor
     *
     * @param Builder $query İlişkili model query'si
     * @param Model $parent Üst model
     * @param string $table Pivot tablo adı
     * @param string $foreignPivotKey Üst modelin pivot key'i
     * @param string $relatedPivotKey İlişkili modelin pivot key'i
     * @param string $parentKey Üst modelin local key'i
     * @param string $relatedKey İlişkili modelin local key'i
     */
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
     * Query'ye kısıtları ekler (tek model için - lazy loading)
     *
     * @return void
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
     * Eager loading için kısıtları ekler
     *
     * @param array<Model> $models
     * @return void
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
     * İlişkili sorgu için JOIN ve SELECT oluşturur
     *
     * @return void
     */
    protected function performJoin(): void
    {
        $relatedTable = $this->query->getModel()?->getTable();

        // Ana SELECT: 'roles.*'
        $this->query->select($relatedTable . '.*');

        // Pivot sütunlarını SELECT'e ekle
        foreach ($this->aliasedPivotColumns() as $pivotSelect) {
            $this->query->addSelect(\Zephyr\Database\Query\Expression::make($pivotSelect));
        }

        // JOIN pivot table
        $this->query->join(
            $this->table,
            $relatedTable . '.' . $this->relatedKey,
            '=',
            $this->table . '.' . $this->relatedPivotKey
        );
    }

    /**
     * Eager loading sonuçlarını üst modellerle eşleştirir
     *
     * @param array<Model> $models Üst modeller
     * @param array<Model> $results İlişkili modeller
     * @param string $relation İlişki adı
     * @return array<Model>
     */
    public function match(array $models, array $results, string $relation): array
    {
        // Pivot attributes'leri extract et
        $results = $this->hydratePivotRelation($results);

        // Sonuçları pivot key'e göre grupla
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

    /**
     * Pivot attributes'leri ayıklar ve modellere ekler
     *
     * @param array<Model> $results
     * @return array<Model>
     */
    protected function hydratePivotRelation(array $results): array
    {
        foreach ($results as $model) {
            $pivotAttributes = [];

            // 'pivot_' ile başlayan attribute'ları bul
            $attributes = $model->getAttributes();

            foreach ($attributes as $key => $value) {
                if (str_starts_with($key, 'pivot_')) {
                    $pivotKey = substr($key, 6); // 'pivot_' prefix'ini kaldır
                    $pivotAttributes[$pivotKey] = $value;

                    // Model attribute'undan kaldır
                    unset($model->$key);
                }
            }

            // Pivot object'i oluştur
            if (!empty($pivotAttributes)) {
                $model->setRelation('pivot', (object) $pivotAttributes);
            }
        }

        return $results;
    }

    /**
     * Sonuçları pivot key'e göre dictionary oluşturur
     *
     * @param array<Model> $results
     * @return array
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            // Pivot'tan foreign key'i al
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
     * İlişki sonuçlarını döndürür
     *
     * @return Collection
     */
    public function getResults(): Collection
    {
        $results = $this->query->get();

        return $this->query->getModel()?->newCollection(
            $this->hydratePivotRelation($results->all())
        );
    }

    /**
     * Pivot'tan alınacak ekstra sütunları belirler
     *
     * @param array $columns Sütun adları
     * @return self
     *
     * @example
     * $user->roles()->withPivot(['expires_at', 'is_active'])->get();
     */
    public function withPivot(array $columns): self
    {
        $this->pivotColumns = array_merge($this->pivotColumns, $columns);

        // Query'yi yeniden oluştur
        $this->query = $this->query->getModel()?->newQuery();
        $this->addConstraints();

        return $this;
    }

    /**
     * Pivot'a timestamps ekler (created_at, updated_at)
     *
     * @return self
     *
     * @example
     * $user->roles()->withTimestamps()->get();
     */
    public function withTimestamps(): self
    {
        $this->withTimestamps = true;
        return $this->withPivot(['created_at', 'updated_at']);
    }

    /**
     * Pivot sütunlarını alias ile formatlar
     *
     * @return array
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
    | PİVOT YÖNETİM METOTLARI
    |--------------------------------------------------------------------------
    */

    /**
     * Pivot tabloya ilişki ekler (attach)
     *
     * @param mixed $id Tek ID veya ID array'i
     * @param array $attributes Pivot attribute'ları
     * @return void
     *
     * @example
     * $user->roles()->attach(1);
     * $user->roles()->attach([1, 2, 3]);
     * $user->roles()->attach(1, ['expires_at' => '2025-12-31']);
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
     * Pivot tablodan ilişkiyi kaldırır (detach)
     *
     * @param mixed|null $id Tek ID, ID array'i veya null (hepsini sil)
     * @return int Silinen satır sayısı
     *
     * @example
     * $user->roles()->detach(1); // Tek rol
     * $user->roles()->detach([1, 2]); // Çoklu rol
     * $user->roles()->detach(); // Tüm roller
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
     * İlişkileri senkronize eder (sync)
     *
     * Mevcut ilişkileri verilen listeyeuygun hale getirir.
     * Fazla olanları siler, eksik olanları ekler.
     *
     * @param array $ids ID array'i
     * @return array Değişiklik raporu
     *
     * @example
     * $changes = $user->roles()->sync([1, 2, 3]);
     * // ['attached' => [1, 3], 'detached' => [4], 'updated' => []]
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
     * Pivot kaydı oluşturur
     *
     * @param mixed $relatedId İlişkili model ID
     * @param array $attributes Ekstra attribute'lar
     * @param string $now Timestamp
     * @return array
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
     * Mevcut pivot ID'lerini döndürür
     *
     * @return array
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
     * Sync ID'lerini formatlar
     *
     * @param array $ids
     * @return array
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
     * Pivot tablo için query oluşturur
     *
     * @return QueryBuilder
     */
    protected function newPivotQuery(): QueryBuilder
    {
        $query = new QueryBuilder($this->query->getModel()?->getConnection());

        return $query
            ->from($this->table)
            ->where($this->foreignPivotKey, '=', $this->parent->getAttribute($this->parentKey));
    }
}