<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsMany;

/**
 * Belongs To Many Relation
 *
 * (örn. User "belongs to many" Roles, Post "belongs to many" Tags)
 * Pivot (ara) bir tablo üzerinden çoğa çok ilişkiyi yönetir.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class BelongsToMany extends Relation implements ReturnsMany
{
    /**
     * Ara (pivot) tablo adı.
     */
    protected string $table;

    /**
     * Parent (üst) modelin pivot tablodaki "foreign key" adı.
     * (örn: user_roles tablosundaki 'user_id')
     */
    protected string $foreignPivotKey;

    /**
     * Related (ilişkili) modelin pivot tablodaki "foreign key" adı.
     * (örn: user_roles tablosundaki 'role_id')
     */
    protected string $relatedPivotKey;

    /**
     * Parent (üst) modelin kendi "primary key" adı.
     * (örn: users tablosundaki 'id')
     */
    protected string $parentKey;

    /**
     * Related (ilişkili) modelin kendi "primary key" adı.
     * (örn: roles tablosundaki 'id')
     */
    protected string $relatedKey;

    /**
     * Pivot tablodan seçilecek ekstra sütunlar.
     */
    protected array $pivotColumns = [];

    /**
     * Pivot tabloda 'created_at' ve 'updated_at' sütunlarının
     * yönetilip yönetilmeyeceği.
     */
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
     * (örn: $user->roles() çağrıldığında)
     *
     * SELECT roles.*, user_roles.user_id as pivot_user_id, ...
     * FROM roles
     * INNER JOIN user_roles ON roles.id = user_roles.role_id
     * WHERE user_roles.user_id = ?
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
     * (örn: User::with('roles') çağrıldığında)
     *
     * SELECT roles.*, user_roles.user_id as pivot_user_id, ...
     * FROM roles
     * INNER JOIN user_roles ON roles.id = user_roles.role_id
     * WHERE user_roles.user_id IN (?, ?, ...)
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
     * İlişkili sorgu için JOIN ve SELECT (pivot sütunları) kısımlarını oluşturur.
     */
    protected function performJoin(): void
    {
        // Temel SELECT: 'roles.*'
        $this->query->select($this->query->getModel()->getTable() . '.*');

        // Pivot sütunlarını SELECT'e ekle (alias ile)
        $this->query->addSelect($this->aliasedPivotColumns());

        // JOIN: INNER JOIN user_roles ON roles.id = user_roles.role_id
        $this->query->join(
            $this->table,
            $this->query->getModel()->getTable() . '.' . $this->relatedKey,
            '=',
            $this->table . '.' . $this->relatedPivotKey
        );
    }

    /**
     * Eager loading sonrası sonuçları üst modellerle eşleştirir.
     */
    public function match(array $models, array $results, string $relation): array
    {
        // Sonuçları 'pivot_user_id' gibi pivot anahtarına göre grupla
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            } else {
                $model->setRelation($relation, []);
            }
        }

        return $models;
    }

    /**
     * Eager loading için pivot anahtarına göre bir sözlük (dictionary) oluşturur.
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];
        $pivotKey = 'pivot_' . $this->foreignPivotKey; // 'pivot_user_id'

        foreach ($results as $result) {
            // Sonuçlar Model nesneleri olduğu için getAttribute kullanmalıyız.
            // Model, 'pivot_user_id'yi HasAttributes trait'i sayesinde
            // sihirli bir özellik olarak okuyabilir.
            $key = $result->getAttribute($pivotKey);

            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }
            $dictionary[$key][] = $result;
        }

        return $dictionary;
    }

    /**
     * Sonuçları (birden çok) alır.
     */
    public function getResults(): array
    {
        return $this->query->get();
    }

    /**
     * Pivot tablodan alınacak ekstra sütunları belirler.
     *
     * @param array $columns Sütun adları
     */
    public function withPivot(array $columns): self
    {
        $this->pivotColumns = array_merge($this->pivotColumns, $columns);
        // Sorguyu yeniden oluşturmak için kısıtları sıfırla ve tekrar ekle
        $this->query->select = [];
        $this->addConstraints();
        return $this;
    }

    /**
     * Pivot tabloya 'created_at' ve 'updated_at' ekler.
     */
    public function withTimestamps(): self
    {
        $this->withPivot(['created_at', 'updated_at']);
        $this->withTimestamps = true;
        return $this;
    }

    /**
     * Pivot sütunlarını "pivot_sutun_adi" şeklinde alias (takma ad) ile formatlar.
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
    | PIVOT YÖNETİM METOTLARI (attach, detach, sync)
    |--------------------------------------------------------------------------
    */

    /**
     * Pivot tabloya yeni bir ilişki ekler.
     *
     * @param mixed $id Eklenecek modelin ID'si (veya ID dizisi)
     * @param array $attributes Pivot tabloya eklenecek ekstra veriler
     */
    public function attach(mixed $id, array $attributes = []): void
    {
        $ids = is_array($id) ? $id : [$id];
        $records = [];
        $now = $this->parent->freshTimestamp();

        foreach ($ids as $relatedId) {
            $record = $this->createPivotRecord($relatedId, $attributes, $now);
            $records[] = $record;
        }

        // Toplu insert (insertMultiple)
        $this->newPivotQuery()->insertMultiple($records);
    }

    /**
     * Pivot tablodan bir ilişkiyi kaldırır.
     *
     * @param mixed|null $id Kaldırılacak modelin ID'si (veya ID dizisi). Null ise tümü.
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
     * Pivot tablodaki ilişkileri verilen ID dizisi ile senkronize eder.
     * (Sadece listede olanlar kalır, olmayanlar silinir, yeniler eklenir)
     *
     * @param array $ids Senkronize edilecek ID dizisi
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

        // 1. Yenileri Ekle
        if (count($toAttach) > 0) {
            $this->attach(array_keys($toAttach), []); // Ekstra pivot verisi şimdilik desteklenmiyor
            $results['attached'] = array_keys($toAttach);
        }

        // 2. Eskileri Sil
        if (count($toDetach) > 0) {
            $this->detach(array_keys($toDetach));
            $results['detached'] = array_keys($toDetach);
        }

        // 3. 'updated' (mevcut olanların pivot verisini güncelleme)
        // Bu implementasyon şimdilik 'update'i desteklemiyor.

        return $results;
    }

    /**
     * Pivot tabloya eklemek için temel kaydı oluşturur.
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
     * Mevcut parent için pivot tablodaki tüm ilişkili ID'leri alır.
     */
    protected function getCurrentPivotIds(): array
    {
        return $this->newPivotQuery()
            ->select($this->relatedPivotKey)
            ->get()
            ->pluck($this->relatedPivotKey)
            ->flip() // [1, 2, 3] -> [1 => 0, 2 => 1, 3 => 2] (Hızlı arama için)
            ->all();
    }
    
    /**
     * Sync için gelen ID'leri formatlar.
     * [1, 2, 3] -> [1 => [], 2 => [], 3 => []]
     */
    protected function formatSyncIds(array $ids): array
    {
        $formatted = [];
        foreach ($ids as $key => $value) {
            if (is_numeric($key)) {
                $formatted[$value] = []; // Basit ID: [1, 2, 3]
            } else {
                $formatted[$key] = $value; // Pivot verisiyle: [1 => ['extra' => 'data']]
            }
        }
        return $formatted;
    }

    /**
     * Pivot tablo için yeni bir (ilişkisiz) Query Builder başlatır.
     */
    protected function newPivotQuery(): Builder
    {
        return $this->connection->table($this->table)
            ->where($this->foreignPivotKey, '=', $this->parent->getAttribute($this->parentKey));
    }
}