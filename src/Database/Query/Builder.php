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
 * QueryBuilder'ı extends eder AMA override YAPMAZ!
 * Farklı metot adları kullanır: getModels(), firstModel() gibi
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Builder extends QueryBuilder
{
    protected ?Model $model = null;
    protected array $eagerLoad = [];
    protected array $removedScopes = [];

    public function setModel(Model $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    /**
     * Model Collection döndürür
     * Parent'ın get() metodunu OVERRIDE ETMİYORUZ!
     */
    public function getModels(): Collection
    {
        $results = parent::get(); // array

        if (empty($results)) {
            return $this->model->newCollection([]);
        }

        $models = $this->hydrate($results);
        return $this->model->newCollection($models);
    }

    /**
     * İlk modeli döndürür
     * Parent'ın first() metodunu OVERRIDE ETMİYORUZ!
     */
    public function firstModel(): ?Model
    {
        $result = parent::first(); // ?array

        if (is_null($result)) {
            return null;
        }

        $models = $this->hydrate([$result]);
        return $models[0] ?? null;
    }

    /**
     * Primary key ile model bulur
     */
    public function find(mixed $id, array $columns = ['*']): ?Model
    {
        return $this->select(...$columns)
            ->where($this->model->getKeyName(), '=', $id)
            ->firstModel();
    }

    /**
     * Model bulamazsa exception fırlatır
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
     * Çoklu ID ile modelleri bulur
     */
    public function findMany(array $ids, array $columns = ['*']): Collection
    {
        if (empty($ids)) {
            return $this->model->newCollection([]);
        }

        return $this->select(...$columns)
            ->whereIn($this->model->getKeyName(), $ids)
            ->getModels();
    }

    /**
     * Bul veya yeni instance oluştur (kaydetmeden)
     */
    public function firstOrNew(array $attributes = [], array $values = []): Model
    {
        $instance = $this->whereArray($attributes)->firstModel();

        if (!is_null($instance)) {
            return $instance;
        }

        return $this->model->newInstance(array_merge($attributes, $values));
    }

    /**
     * Bul veya oluştur ve kaydet
     */
    public function firstOrCreate(array $attributes = [], array $values = []): Model
    {
        $instance = $this->whereArray($attributes)->firstModel();

        if (!is_null($instance)) {
            return $instance;
        }

        $instance = $this->model->newInstance(array_merge($attributes, $values));
        $instance->save();

        return $instance;
    }

    /**
     * Güncelle veya oluştur
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        $instance = $this->whereArray($attributes)->firstModel();

        if (!is_null($instance)) {
            $instance->fill($values)->save();
            return $instance;
        }

        return $this->firstOrCreate($attributes, $values);
    }

    /**
     * Model oluşturur ve kaydeder
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
     */
    public function insertGetId(array $values): int|string
    {
        return parent::insert($values);
    }

    /**
     * Eager loading için ilişkileri belirler
     */
    public function with(array|string $relations): self
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        foreach ($relations as $name => $constraints) {
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
     */
    public function withoutGlobalScopes(): self
    {
        $this->removedScopes = ['*' => true];
        return $this;
    }

    protected function scopeIsRemoved(string $scope): bool
    {
        return isset($this->removedScopes['*']) || isset($this->removedScopes[$scope]);
    }

    protected function eagerLoadRelations(array $models): array
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            $models = $this->eagerLoadRelation($models, $name, $constraints);
        }

        return $models;
    }

    protected function eagerLoadRelation(array $models, string $name, ?callable $constraints): array
    {
        if (empty($models)) {
            return $models;
        }

        $relation = $models[0]->$name();

        if (!is_null($constraints)) {
            $constraints($relation);
        }

        return $relation->eagerLoadAndMatch($models, $name);
    }

    protected function hydrate(array $items): array
    {
        $models = array_map(function ($item) {
            return $this->model->newFromBuilder($item);
        }, $items);

        if (!empty($this->eagerLoad)) {
            $models = $this->eagerLoadRelations($models);
        }

        return $models;
    }

    /**
     * Sayfalama (data'yı hydrate eder)
     */
    public function paginateModels(int $page = 1, int $perPage = 15): array
    {
        $result = parent::paginate($page, $perPage);

        $result['data'] = $this->model->newCollection(
            $this->hydrate($result['data'])
        );

        return $result;
    }

    /**
     * Chunk processing (Model'lerle)
     */
    public function chunkModels(int $size, callable $callback): bool
    {
        $page = 1;

        do {
            $result = $this->paginateModels($page, $size);
            $models = $result['data'];

            if ($models->isEmpty()) {
                break;
            }

            if ($callback($models, $page) === false) {
                return false;
            }

            $page++;
        } while ($models->count() === $size);

        return true;
    }

    /**
     * Model scope'larını uygular
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
     * Magic call - scope'ları kontrol eder
     */
    public function __call(string $method, array $parameters): mixed
    {
        $scopeMethod = 'scope' . ucfirst($method);
        if (method_exists($this->model, $scopeMethod)) {
            return $this->scope($method, $parameters);
        }

        return parent::__call($method, $parameters);
    }
}