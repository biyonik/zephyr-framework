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
 * ✅ YENİ: Advanced Eager Loading Support
 * ✅ YENİ: Nested Relations (posts.comments)
 * ✅ YENİ: Relation Constraints
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Builder extends QueryBuilder
{
    protected array $eagerLoad = [];
    protected array $removedScopes = [];

    /**
     * Model bulamazsa exception fırlatır
     */
    public function findOrFail(mixed $id, array $columns = ['*']): Model
    {
        $model = $this->find($id, $columns);

        if (is_null($model)) {
            $modelClass = $this->getModel() ? get_class($this->getModel()) : 'Model';
            
            throw (new ModelNotFoundException)
                ->setModel($modelClass)
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
            $model = $this->getModel();
            return $model ? $model->newCollection([]) : new Collection([]);
        }

        if (!$this->getModel()) {
            throw new \RuntimeException('Model not set on query builder');
        }

        return $this->select(...$columns)
            ->whereIn($this->getModel()->getKeyName(), $ids)
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

        if (!$this->getModel()) {
            throw new \RuntimeException('Model not set on query builder');
        }

        return $this->getModel()->newInstance(array_merge($attributes, $values));
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

        if (!$this->getModel()) {
            throw new \RuntimeException('Model not set on query builder');
        }

        $instance = $this->getModel()->newInstance(array_merge($attributes, $values));
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
        if (!$this->getModel()) {
            throw new \RuntimeException('Model not set on query builder');
        }

        $id = $this->insertGetId($values);

        $model = $this->getModel()->newInstance();
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
        return $this->insert($values);
    }

    /**
     * ✅ YENİ: Advanced Eager Loading - Nested Relations Support
     * 
     * Supports:
     * - Simple: with('posts')
     * - Multiple: with(['posts', 'comments'])  
     * - Nested: with('posts.comments')
     * - Constraints: with(['posts' => function($q) { $q->where('published', true); }])
     * - Mixed: with(['posts.comments', 'profile'])
     */
    public function with(array|string $relations): self
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        foreach ($relations as $name => $constraints) {
            if (is_numeric($name)) {
                // Simple string relation: 'posts' or 'posts.comments'
                $name = $constraints;
                $constraints = null;
            }

            // Parse nested relations: 'posts.comments.author'
            $this->parseNestedRelation($name, $constraints);
        }

        return $this;
    }

    /**
     * ✅ YENİ: Nested relation parsing
     * 
     * 'posts.comments.author' -> 
     * [
     *   'posts' => function($q) { $q->with('comments.author'); }
     * ]
     */
    protected function parseNestedRelation(string $name, ?callable $constraints): void
    {
        $segments = explode('.', $name);
        $topLevel = array_shift($segments);

        if (empty($segments)) {
            // Simple relation: 'posts'
            $this->eagerLoad[$topLevel] = $constraints;
        } else {
            // Nested relation: 'posts.comments'
            $nested = implode('.', $segments);
            
            $existingConstraints = $this->eagerLoad[$topLevel] ?? null;
            
            $this->eagerLoad[$topLevel] = function ($query) use ($nested, $constraints, $existingConstraints) {
                // Apply existing constraints first
                if ($existingConstraints) {
                    $existingConstraints($query);
                }
                
                // Add nested relation
                $query->with($nested, $constraints);
            };
        }
    }

    /**
     * ✅ YENİ: Relationship existence query
     * 
     * User::has('posts')->get(); // Users who have posts
     */
    public function has(string $relation, string $operator = '>=', int $count = 1): self
    {
        return $this->whereHas($relation, null, $operator, $count);
    }

    /**
     * ✅ YENİ: Relationship existence query with constraints
     * 
     * User::whereHas('posts', function($q) {
     *     $q->where('published', true);
     * })->get(); // Users who have published posts
     */
    public function whereHas(string $relation, ?callable $callback = null, string $operator = '>=', int $count = 1): self
    {
        if (!$this->getModel()) {
            throw new \RuntimeException('Model not set on query builder');
        }

        // Get relation instance
        $relationInstance = $this->getModel()->$relation();
        $relatedModel = $relationInstance->getQuery()->getModel();
        
        if (!$relatedModel) {
            throw new \RuntimeException("Related model not found for relation: {$relation}");
        }

        // Create subquery
        $subQuery = $relatedModel->newQuery();
        
        // Apply callback constraints
        if ($callback) {
            $callback($subQuery);
        }

        // Add relation constraints based on relation type
        $this->addRelationSubqueryConstraints($relationInstance, $subQuery);

        // Add the whereExists constraint
        $sql = "({$subQuery->toSql()}) {$operator} {$count}";
        
        return $this->whereRaw("EXISTS (SELECT COUNT(*) FROM ({$subQuery->toSql()}) as subquery)", $subQuery->getBindings());
    }

    /**
     * ✅ YENİ: Add count column for relationships
     * 
     * User::withCount('posts')->get(); 
     * // Result: $user->posts_count
     */
    public function withCount(array|string $relations): self
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }

        if (!$this->getModel()) {
            throw new \RuntimeException('Model not set on query builder');
        }

        foreach ($relations as $relation) {
            $relationInstance = $this->getModel()->$relation();
            $relatedModel = $relationInstance->getQuery()->getModel();
            
            if (!$relatedModel) {
                continue;
            }

            // Create count subquery
            $subQuery = $relatedModel->newQuery();
            $this->addRelationSubqueryConstraints($relationInstance, $subQuery);

            // Add as select
            $countSql = "({$subQuery->count()}) as {$relation}_count";
            $this->addSelect(\Zephyr\Database\Query\Expression::make($countSql));
        }

        return $this;
    }

    /**
     * ✅ YENİ: Relationship constraint helper
     */
    protected function addRelationSubqueryConstraints($relationInstance, Builder $subQuery): void
    {
        $relationClass = get_class($relationInstance);
        
        if (str_contains($relationClass, 'HasMany')) {
            // HasMany: posts.user_id = users.id
            $foreignKey = $relationInstance->getForeignKeyName();
            $localKey = $relationInstance->getLocalKeyName();
            $subQuery->whereColumn($foreignKey, $this->getModel()->getTable() . '.' . $localKey);
            
        } elseif (str_contains($relationClass, 'BelongsTo')) {
            // BelongsTo: users.id = posts.user_id  
            $ownerKey = $relationInstance->getOwnerKeyName();
            $foreignKey = $relationInstance->getForeignKeyName();
            $subQuery->whereColumn($ownerKey, $this->getModel()->getTable() . '.' . $foreignKey);
            
        } elseif (str_contains($relationClass, 'HasOne')) {
            // HasOne: profile.user_id = users.id
            $foreignKey = $relationInstance->getForeignKeyName();
            $localKey = $relationInstance->getLocalKeyName();  
            $subQuery->whereColumn($foreignKey, $this->getModel()->getTable() . '.' . $localKey);
        }
        // BelongsToMany is more complex, implement later if needed
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

    /**
     * ✅ İYİLEŞTİRİLDİ: Eager load relations with nested support
     */
    protected function eagerLoadRelations(array $models): array
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            $models = $this->eagerLoadRelation($models, $name, $constraints);
        }

        return $models;
    }

    /**
     * ✅ İYİLEŞTİRİLDİ: Single relation eager loading with constraint support
     */
    protected function eagerLoadRelation(array $models, string $name, ?callable $constraints): array
    {
        if (empty($models)) {
            return $models;
        }

        // Get relation from first model
        $relation = $models[0]->$name();

        // Apply constraints if provided
        if ($constraints) {
            $constraints($relation);
        }

        // Load and match results
        return $relation->eagerLoadAndMatch($models, $name);
    }

    /**
     * ✅ DÜZELTME: Parent'daki hydrate() override ediliyor  
     * Eager loading eklenmiş versiyon
     */
    protected function hydrate(array $items): array
    {
        $models = parent::hydrate($items);

        if (!empty($this->eagerLoad)) {
            $models = $this->eagerLoadRelations($models);
        }

        return $models;
    }

    /**
     * Sayfalama (data'yı hydrate eder) - Parent'daki paginate()'i override
     */
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $countQuery = clone $this;
        $total = $countQuery->count();

        $lastPage = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + $perPage, $total);

        $this->limit($perPage)->offset($offset);

        // Model collection döndür (hydrate ile eager loading dahil)
        $data = $this->getModels();

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Chunk processing (Model'lerle)
     */
    public function chunkModels(int $size, callable $callback): bool
    {
        $page = 1;

        do {
            $result = $this->paginate($page, $size);
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

        if ($this->getModel() && method_exists($this->getModel(), $method)) {
            $this->getModel()->$method($this, ...$parameters);
        }

        return $this;
    }

    /**
     * Magic call - scope'ları kontrol eder
     */
    public function __call(string $method, array $parameters): mixed
    {
        $scopeMethod = 'scope' . ucfirst($method);
        if ($this->getModel() && method_exists($this->getModel(), $scopeMethod)) {
            return $this->scope($method, $parameters);
        }

        return parent::__call($method, $parameters);
    }
}