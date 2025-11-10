<?php

declare(strict_types=1);

namespace Zephyr\Database;

use PDO;
use Zephyr\Database\Exception\DatabaseException;
use Zephyr\Database\Query\Expression;
use Zephyr\Support\Collection;

/**
 * SQL Sorgu Oluşturucu
 * 
 * Bu sınıf hem ARRAY hem MODEL döndürebilir!
 * Model set edilirse model metotları kullanılabilir.
 */
class QueryBuilder
{
    protected array $columns = ['*'];
    protected ?string $from = null;
    protected array $joins = [];
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $orders = [];
    protected array $groups = [];
    protected array $havings = [];
    protected array $havingBindings = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    
    // ✅ YENİ: Model support
    protected ?\Zephyr\Database\Model $model = null;

    public function __construct(
        protected Connection $connection
    ) {}

    // ✅ YENİ: Model setter
    public function setModel(\Zephyr\Database\Model $model): self
    {
        $this->model = $model;
        return $this;
    }

    // ✅ YENİ: Model getter
    public function getModel(): ?\Zephyr\Database\Model
    {
        return $this->model;
    }

    public function select(string|array|Expression ...$columns): self
    {
        $this->columns = empty($columns) ? ['*'] : $this->flattenColumns($columns);
        return $this;
    }

    public function addSelect(string|array|Expression ...$columns): self
    {
        $this->columns = array_merge($this->columns, $this->flattenColumns($columns));
        return $this;
    }

    public function from(string $table, ?string $alias = null): self
    {
        $this->from = $alias ? "{$table} AS {$alias}" : $table;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin('INNER', $table, $first, $operator, $second);
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin('LEFT', $table, $first, $operator, $second);
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin('RIGHT', $table, $first, $operator, $second);
    }

    protected function addJoin(string $type, string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];
        return $this;
    }

    /**
     * WHERE - Tek sütun için
     */
    public function where(string $column, string $operator, mixed $value, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        $this->bindings[] = $value;
        return $this;
    }

    /**
     * WHERE - Array için (çoklu sütun)
     * 
     * @example where(['email' => 'test@example.com', 'active' => true])
     */
    public function whereArray(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->where($key, '=', $value);
        }
        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND'): self
    {
        if (empty($values)) {
            return $this->whereRaw('1 = 0', [], $boolean);
        }

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
        ];

        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function orWhereIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, 'OR');
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): self
    {
        if (empty($values)) {
            return $this;
        }

        $this->wheres[] = [
            'type' => 'not_in',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
        ];

        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function whereNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => $boolean,
        ];
        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => $boolean,
        ];
        return $this;
    }

    public function whereBetween(string $column, array $values, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
        ];

        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => $sql,
            'boolean' => $boolean,
        ];

        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }

    public function when(mixed $condition, callable $callback, ?callable $default = null): self
    {
        if ($condition) {
            return $callback($this, $condition) ?? $this;
        }

        if ($default) {
            return $default($this, $condition) ?? $this;
        }

        return $this;
    }

    public function unless(mixed $condition, callable $callback, ?callable $default = null): self
    {
        return $this->when(!$condition, $callback, $default);
    }

    public function tap(callable $callback): self
    {
        $callback($this);
        return $this;
    }

    public function orderBy(string|Expression $column, string $direction = 'ASC'): self
    {
        if ($column instanceof Expression) {
            $this->orders[] = ['column' => $column, 'direction' => ''];
            return $this;
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $column)) {
            throw new \InvalidArgumentException("Geçersiz sütun adı: {$column}");
        }

        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException("Geçersiz sıralama yönü: {$direction}");
        }

        $this->orders[] = ['column' => $column, 'direction' => $direction];
        return $this;
    }

    public function orderByRaw(string $sql): self
    {
        return $this->orderBy(Expression::make($sql));
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'DESC');
    }

    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'ASC');
    }

    public function groupBy(string|array ...$columns): self
    {
        $this->groups = array_merge($this->groups, $this->flattenColumns($columns));
        return $this;
    }

    public function having(string $column, string $operator, mixed $value): self
    {
        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        $this->havingBindings[] = $value;
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit > 0 ? $limit : null;
        return $this;
    }

    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset >= 0 ? $offset : null;
        return $this;
    }

    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    public function toSql(): string
    {
        $sql = [];

        $sql[] = 'SELECT ' . $this->buildColumns();

        if ($this->from) {
            $sql[] = 'FROM ' . $this->from;
        }

        if (!empty($this->joins)) {
            $sql[] = $this->buildJoins();
        }

        if (!empty($this->wheres)) {
            $sql[] = 'WHERE ' . $this->buildWheres();
        }

        if (!empty($this->groups)) {
            $sql[] = 'GROUP BY ' . implode(', ', $this->groups);
        }

        if (!empty($this->havings)) {
            $sql[] = 'HAVING ' . $this->buildHavings();
        }

        if (!empty($this->orders)) {
            $sql[] = 'ORDER BY ' . $this->buildOrders();
        }

        if (!is_null($this->limit)) {
            $sql[] = 'LIMIT ' . $this->limit;
        }

        if (!is_null($this->offset)) {
            $sql[] = 'OFFSET ' . $this->offset;
        }

        return implode(' ', $sql);
    }

    public function getBindings(): array
    {
        return array_merge($this->bindings, $this->havingBindings);
    }

    /**
     * ARRAY döndürür - Raw SQL results
     */
    public function get(): array
    {
        try {
            $sql = $this->toSql();
            $bindings = $this->getBindings();

            $pdo = $this->connection->getPdo();
            $statement = $pdo->prepare($sql);
            $statement->execute($bindings);

            return $statement->fetchAll();

        } catch (\PDOException $e) {
            throw new DatabaseException(
                "Sorgu hatası: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
                $sql ?? null,
                $bindings ?? null
            );
        }
    }

    /**
     * ?ARRAY döndürür - Raw SQL results
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    // ✅ YENİ: Model Collection döndürür
    public function getModels(): Collection
    {
        if (!$this->model) {
            throw new \RuntimeException('Model not set on query builder. Use setModel() first.');
        }

        $results = $this->get(); // Array results

        if (empty($results)) {
            return $this->model->newCollection([]);
        }

        $models = $this->hydrate($results);
        return $this->model->newCollection($models);
    }

    // ✅ YENİ: İlk modeli döndürür
    public function firstModel(): ?\Zephyr\Database\Model
    {
        if (!$this->model) {
            throw new \RuntimeException('Model not set on query builder. Use setModel() first.');
        }

        $result = $this->first(); // ?Array

        if (is_null($result)) {
            return null;
        }

        $models = $this->hydrate([$result]);
        return $models[0] ?? null;
    }

    // ✅ YENİ: Primary key ile model bulur
    public function find(mixed $id, array $columns = ['*']): ?\Zephyr\Database\Model
    {
        if (!$this->model) {
            throw new \RuntimeException('Model not set on query builder. Use setModel() first.');
        }

        return $this->select(...$columns)
            ->where($this->model->getKeyName(), '=', $id)
            ->firstModel();
    }

    // ✅ YENİ: Array'i Model'lere çevirir
    protected function hydrate(array $items): array
    {
        return array_map(function ($item) {
            return $this->model->newFromBuilder($item);
        }, $items);
    }

    public function value(string $column): mixed
    {
        $result = $this->first();
        return $result[$column] ?? null;
    }

    public function pluck(string $column, ?string $key = null): array
    {
        $results = $this->get();

        if (is_null($key)) {
            return array_column($results, $column);
        }

        $plucked = [];
        foreach ($results as $result) {
            $plucked[$result[$key]] = $result[$column];
        }

        return $plucked;
    }

    public function count(): int
    {
        $original = $this->columns;
        $this->columns = [Expression::make('COUNT(*) as aggregate')];

        $result = $this->first();
        $this->columns = $original;

        return (int) ($result['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    public function sum(string $column): float
    {
        return (float) $this->aggregate('SUM', $column);
    }

    public function avg(string $column): float
    {
        return (float) $this->aggregate('AVG', $column);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    protected function aggregate(string $function, string $column): mixed
    {
        $original = $this->columns;
        $this->columns = [Expression::make("{$function}({$column}) as aggregate")];

        $result = $this->first();
        $this->columns = $original;

        return $result['aggregate'] ?? null;
    }

    public function insert(array $data): int|string
    {
        if (empty($data)) {
            throw new DatabaseException('Boş veri eklenemez');
        }

        try {
            $columns = array_keys($data);
            $values = array_values($data);
            $placeholders = $this->parameterize($values);

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $this->from,
                implode(', ', $columns),
                $placeholders
            );

            $pdo = $this->connection->getPdo();
            $statement = $pdo->prepare($sql);
            $statement->execute($values);

            return $pdo->lastInsertId();

        } catch (\PDOException $e) {
            throw new DatabaseException(
                "INSERT hatası: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
                $sql ?? null,
                $values ?? null
            );
        }
    }

    public function insertMultiple(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        try {
            $this->connection->beginTransaction();

            foreach ($data as $row) {
                $this->insert($row);
            }

            $this->connection->commit();
            return true;

        } catch (\Throwable $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    public function update(array $data): int
    {
        if (empty($data)) {
            throw new DatabaseException('Boş veri ile güncelleme yapılamaz');
        }

        try {
            $columns = array_keys($data);
            $values = array_values($data);

            $set = implode(', ', array_map(fn($col) => "{$col} = ?", $columns));
            $sql = "UPDATE {$this->from} SET {$set}";

            if (!empty($this->wheres)) {
                $sql .= ' WHERE ' . $this->buildWheres();
                $values = array_merge($values, $this->bindings);
            }

            $pdo = $this->connection->getPdo();
            $statement = $pdo->prepare($sql);
            $statement->execute($values);

            return $statement->rowCount();

        } catch (\PDOException $e) {
            throw new DatabaseException(
                "UPDATE hatası: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
                $sql ?? null,
                $values ?? null
            );
        }
    }

    public function delete(): int
    {
        try {
            $sql = "DELETE FROM {$this->from}";

            if (!empty($this->wheres)) {
                $sql .= ' WHERE ' . $this->buildWheres();
            }

            $pdo = $this->connection->getPdo();
            $statement = $pdo->prepare($sql);
            $statement->execute($this->bindings);

            return $statement->rowCount();

        } catch (\PDOException $e) {
            throw new DatabaseException(
                "DELETE hatası: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
                $sql ?? null,
                $this->bindings
            );
        }
    }

    public function truncate(): bool
    {
        try {
            $sql = "TRUNCATE TABLE {$this->from}";
            $this->connection->getPdo()->exec($sql);
            return true;

        } catch (\PDOException $e) {
            throw new DatabaseException(
                "TRUNCATE hatası: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

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

        // ✅ YENİ: Model varsa model collection döndür
        if ($this->model) {
            $data = $this->getModels();
        } else {
            $data = $this->get();
        }

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

    public function transaction(callable $callback): mixed
    {
        $this->connection->beginTransaction();

        try {
            $result = $callback($this);
            $this->connection->commit();
            return $result;

        } catch (\Throwable $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    public function raw(string $sql, array $bindings = []): array
    {
        return $this->connection->query($sql, $bindings);
    }

    public function chunk(int $size, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->paginate($page, $size)['data'];

            if (($this->model && $results->isEmpty()) || (!$this->model && empty($results))) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
            $count = $this->model ? $results->count() : count($results);
        } while ($count === $size);

        return true;
    }

    public function random(int $count = 1): array
    {
        return $this->orderByRaw('RAND()')->limit($count)->get();
    }

    public function dump(): self
    {
        dump([
            'sql' => $this->toSql(),
            'bindings' => $this->getBindings(),
        ]);
        return $this;
    }

    public function dd(): never
    {
        dd([
            'sql' => $this->toSql(),
            'bindings' => $this->getBindings(),
        ]);
    }

    protected function flattenColumns(array $columns): array
    {
        $flattened = [];

        foreach ($columns as $column) {
            if (is_array($column)) {
                $flattened = array_merge($flattened, $this->flattenColumns($column));
            } else {
                $flattened[] = $column;
            }
        }

        return $flattened;
    }

    protected function buildColumns(): string
    {
        $columns = array_map(static function ($column) {
            if ($column instanceof Expression) {
                return $column->getValue();
            }
            return $column;
        }, $this->columns);

        return implode(', ', $columns);
    }

    protected function buildJoins(): string
    {
        return implode(' ', array_map(static function ($join) {
            return sprintf(
                '%s JOIN %s ON %s %s %s',
                $join['type'],
                $join['table'],
                $join['first'],
                $join['operator'],
                $join['second']
            );
        }, $this->joins));
    }

    protected function buildWheres(): string
    {
        $sql = [];

        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? '' : $where['boolean'] . ' ';

            $sql[] = match ($where['type']) {
                'basic' => $boolean . $where['column'] . ' ' . $where['operator'] . ' ?',
                'in' => $boolean . $where['column'] . ' IN (' . $this->parameterize($where['values']) . ')',
                'not_in' => $boolean . $where['column'] . ' NOT IN (' . $this->parameterize($where['values']) . ')',
                'null' => $boolean . $where['column'] . ' IS NULL',
                'not_null' => $boolean . $where['column'] . ' IS NOT NULL',
                'between' => $boolean . $where['column'] . ' BETWEEN ? AND ?',
                'raw' => $boolean . $where['sql'],
                default => '',
            };
        }

        return implode(' ', $sql);
    }

    protected function buildHavings(): string
    {
        return implode(' AND ', array_map(function ($having) {
            return $having['column'] . ' ' . $having['operator'] . ' ?';
        }, $this->havings));
    }

    protected function buildOrders(): string
    {
        return implode(', ', array_map(function ($order) {
            if ($order['column'] instanceof Expression) {
                return $order['column']->getValue();
            }
            return $order['column'] . ' ' . $order['direction'];
        }, $this->orders));
    }

    protected function parameterize(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    public function __clone()
    {
        $this->columns = [...$this->columns];
        $this->wheres = array_map(fn($w) => is_array($w) ? [...$w] : $w, $this->wheres);
        $this->bindings = [...$this->bindings];
        $this->joins = array_map(fn($j) => [...$j], $this->joins);
        $this->orders = array_map(fn($o) => [...$o], $this->orders);
        $this->groups = [...$this->groups];
        $this->havings = array_map(fn($h) => [...$h], $this->havings);
        $this->havingBindings = [...$this->havingBindings];
    }

    public function __toString(): string
    {
        return $this->toSql();
    }

    public function __call(string $method, array $parameters): mixed
    {
        throw new \BadMethodCallException(
            "QueryBuilder üzerinde [{$method}] metodu bulunamadı."
        );
    }
}