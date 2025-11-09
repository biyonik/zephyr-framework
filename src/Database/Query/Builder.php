<?php

declare(strict_types=1);

namespace Zephyr\Database\Query;

use Zephyr\Database\QueryBuilder;
use Zephyr\Database\Model;
use Zephyr\Database\Exception\ModelNotFoundException;
use Zephyr\Support\Collection;

/**
 * Model-Aware Query Builder
 *
 * QueryBuilder'ı extends ederek Model nesneleri döndürür.
 * Array yerine Model instance'ları ile çalışır.
 *
 * Özellikler:
 * - Model hydration (array → Model dönüşümü)
 * - Eager loading (with)
 * - Query scopes
 * - Global scopes
 * - Collection döndürme
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Builder extends QueryBuilder
{
    /**
     * Sorgulanacak model
     */
    protected ?Model $model = null;

    /**
     * Eager load edilecek ilişkiler
     */
    protected array $eagerLoad = [];

    /**
     * Devre dışı bırakılmış global scope'lar
     */
    protected array $removedScopes = [];

    /**
     * Model'i set eder
     *
     * @param Model $model
     * @return self
     */
    public function setModel(Model $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Model instance'ını döndürür
     *
     * @return Model|null
     */
    public function getModel(): ?Model
    {
        return $this->model;
    }

    /**
     * Sorguyu çalıştırır ve Model Collection döndürür
     *
     * @return Collection Model collection
     */
    public function get(): Collection
    {
        $results = parent::get(); // array döner

        if (empty($results)) {
            return $this->model->newCollection([]);
        }

        $models = $this->hydrate($results);
        return $this->model->newCollection($models);
    }

    /**
     * İlk modeli döndürür
     *
     * @return Model|null
     */
    public function first(): ?Model
    {
        $result = parent::first(); // ?array döner

        if (is_null($result)) {
            return null;
        }

        $models = $this->hydrate([$result]);
        return $models[0] ?? null;
    }

    /**
     * Primary key ile model bulur
     *
     * @param mixed $id Primary key değeri
     * @param array $columns Seçilecek sütunlar
     * @return Model|null
     */
    public function find(mixed $id, array $columns = ['*']): ?Model
    {
        return $this->select(...$columns)
            ->where($this->model->getKeyName(), '=', $id)
            ->first();
    }

    /**
     * Model bulamazsa exception fırlatır
     *
     * @param mixed $id
     * @param array $columns
     * @return Model
     * @throws ModelNotFoundException
     */
    public function findOrFail(mixed $id, array $columns = ['*']): Model
    {
        $model = $this->find($id, $columns);

        if (is_null($model)) {
            throw (new ModelNotFoundException)
                ->setModel(get_class($this->model))
                ->setIds([$id]);
        }

        return $model;
    }

    /**
     * Çoklu primary key ile modelleri bulur
     *
     * @param array $ids
     * @param array $columns
     * @return Collection
     */
    public function findMany(array $ids, array $columns = ['*']): Collection
    {
        if (empty($ids)) {
            return $this->model->newCollection([]);
        }

        return $this->select(...$columns)
            ->whereIn($this->model->getKeyName(), $ids)
            ->get();
    }

    /**
     * Model oluşturur ve kaydeder
     *
     * @param array $values
     * @return Model
     */
    public function create(array $values): Model
    {
        $id = $this->insertGetId($values);

        $model = $this->model->newInstance();
        $model->setAttribute($model->getKeyName(), $id);
        $model->setRawAttributes($values, true);
        $model->exists = true;
        $model->wasRecentlyCreated = true;

        return $model;
    }

    /**
     * INSERT yapar ve ID döndürür
     *
     * @param array $values
     * @return string
     */
    public function insertGetId(array $values): string
    {
        return $this->insert($values);
    }

    /**
     * Eager loading için ilişkileri belirler
     *
     * @param array|string $relations
     * @return self
     *
     * @example
     * User::with('posts', 'profile')->get();
     * User::with(['posts' => fn($q) => $q->where('published', true)])->get();
     */
    public function with(array|string $relations): self
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        foreach ($relations as $name => $constraints) {
            // Basit string relation
            if (is_numeric($name)) {
                $name = $constraints;
                $constraints = null;
            }

            $this->eagerLoad[$name] = $constraints;
        }

        return $this;
    }

    /**
     * Global scope'u devre dışı bırakır
     *
     * @param string|array $scopes Scope sınıf adı(ları)
     * @return self
     */
    public function withoutGlobalScope(string|array $scopes): self
    {
        $scopes = is_array($scopes) ? $scopes : func_get_args();

        foreach ($scopes as $scope) {
            $this->removedScopes[$scope] = true;
        }

        return $this;
    }

    /**
     * Tüm global scope'ları devre dışı bırakır
     *
     * @return self
     */
    public function withoutGlobalScopes(): self
    {
        $this->removedScopes = ['*' => true];
        return $this;
    }

    /**
     * Global scope devre dışı mı kontrol eder
     *
     * @param string $scope
     * @return bool
     */
    protected function scopeIsRemoved(string $scope): bool
    {
        return isset($this->removedScopes['*']) || isset($this->removedScopes[$scope]);
    }

    /**
     * Model'leri eager load eder
     *
     * @param array $models
     * @return array
     */
    protected function eagerLoadRelations(array $models): array
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            $models = $this->eagerLoadRelation($models, $name, $constraints);
        }

        return $models;
    }

    /**
     * Tek bir ilişkiyi eager load eder
     *
     * @param array $models
     * @param string $name İlişki adı
     * @param callable|null $constraints Query constraints
     * @return array
     */
    protected function eagerLoadRelation(array $models, string $name, ?callable $constraints): array
    {
        if (empty($models)) {
            return $models;
        }

        // İlk model'den relation instance al
        $relation = $models[0]->$name();

        // Constraint'leri uygula
        if (!is_null($constraints)) {
            $constraints($relation);
        }

        // Tüm modeller için relation'ı yükle
        return $relation->eagerLoadAndMatch($models, $name);
    }

    /**
     * Array'leri Model'lere dönüştürür (hydration)
     *
     * @param array $items Ham database satırları
     * @return array Model instance'ları
     */
    protected function hydrate(array $items): array
    {
        $models = array_map(function ($item) {
            return $this->model->newFromBuilder($item);
        }, $items);

        // Eager loading varsa uygula
        if (!empty($this->eagerLoad)) {
            $models = $this->eagerLoadRelations($models);
        }

        return $models;
    }

    /**
     * Sayfalama yapar ve Model collection döndürür
     *
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $result = parent::paginate($page, $perPage);

        // Data'yı hydrate et
        $result['data'] = $this->hydrate($result['data']);

        return $result;
    }

    /**
     * Model sayısını döndürür
     *
     * @return int
     */
    public function count(): int
    {
        return parent::count();
    }

    /**
     * Model var mı kontrol eder
     *
     * @return bool
     */
    public function exists(): bool
    {
        return parent::exists();
    }

    /**
     * Chunk processing (Model'lerle)
     *
     * @param int $size
     * @param callable $callback
     * @return bool
     */
    public function chunk(int $size, callable $callback): bool
    {
        return parent::chunk($size, function ($results) use ($callback) {
            $models = $this->hydrate($results);
            return $callback($models);
        });
    }

    /**
     * Model scope'larını uygular
     *
     * @param string $scope Scope method adı
     * @param array $parameters Parametreler
     * @return self
     *
     * @example
     * Model'de: public function scopeActive($query) { ... }
     * Kullanım: User::active()->get(); veya $query->scope('active')
     */
    public function scope(string $scope, array $parameters = []): self
    {
        $method = 'scope' . ucfirst($scope);

        if (method_exists($this->model, $method)) {
            $this->model->$method($this, ...$parameters);
        }

        return $this;
    }

    /**
     * Dinamik scope method çağrıları
     *
     * @example User::active()->get() -> scopeActive() çağrılır
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Scope dene
        if (method_exists($this->model, 'scope' . ucfirst($method))) {
            return $this->scope($method, $parameters);
        }

        // Parent'a düş
        return parent::__call($method, $parameters);
    }
}