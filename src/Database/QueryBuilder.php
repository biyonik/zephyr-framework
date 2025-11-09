<?php

declare(strict_types=1);

namespace Zephyr\Database;

use PDO;
use Zephyr\Database\Exception\DatabaseException;
use Zephyr\Database\Query\Expression;
use Zephyr\Support\Collection;

/**
 * Query Builder
 *
 * Fluent interface for building SQL queries.
 * Provides safe, parameterized queries with method chaining.
 *
 * Features:
 * - SELECT with columns, joins, where clauses
 * - INSERT, UPDATE, DELETE operations
 * - Aggregates (COUNT, SUM, AVG, MIN, MAX)
 * - Pagination
 * - Transactions
 * - Raw queries
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class QueryBuilder
{
    /**
     * Query components
     */
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

    /**
     * Constructor
     */
    public function __construct(
        protected Connection $connection
    ) {}

    /**
     * Set columns to select
     *
     * @param string|array|Expression $columns
     * @return self
     *
     * @example
     * $query->select('id', 'name');
     * $query->select(['id', 'name', 'email']);
     * $query->select(Expression::make('COUNT(*) as total'));
     */
    public function select(string|array|Expression ...$columns): self
    {
        $this->columns = empty($columns) ? ['*'] : $this->flattenColumns($columns);

        return $this;
    }

    /**
     * Add columns to existing selection
     */
    public function addSelect(string|array|Expression ...$columns): self
    {
        $this->columns = array_merge(
            $this->columns,
            $this->flattenColumns($columns)
        );

        return $this;
    }

    /**
     * Set FROM table
     *
     * @param string $table Table name
     * @param string|null $alias Table alias
     * @return self
     *
     * @example
     * $query->from('users');
     * $query->from('users', 'u');
     */
    public function from(string $table, ?string $alias = null): self
    {
        $this->from = $alias ? "{$table} AS {$alias}" : $table;

        return $this;
    }

    /**
     * Add INNER JOIN
     *
     * @param string $table Table to join
     * @param string $first First column
     * @param string $operator Comparison operator
     * @param string $second Second column
     * @return self
     *
     * @example
     * $query->join('orders', 'users.id', '=', 'orders.user_id');
     */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin('INNER', $table, $first, $operator, $second);
    }

    /**
     * Add LEFT JOIN
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin('LEFT', $table, $first, $operator, $second);
    }

    /**
     * Add RIGHT JOIN
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin('RIGHT', $table, $first, $operator, $second);
    }

    /**
     * Add JOIN clause
     */
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
     * Add WHERE clause
     *
     * @param string $column Column name
     * @param string $operator Comparison operator (=, !=, >, <, >=, <=, LIKE)
     * @param mixed $value Value to compare
     * @param string $boolean Boolean operator (AND, OR)
     * @return self
     *
     * @example
     * $query->where('status', '=', 'active');
     * $query->where('age', '>', 18);
     * $query->where('name', 'LIKE', '%john%');
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
     * Add OR WHERE clause
     */
    public function orWhere(string $column, string $operator, mixed $value): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * Add WHERE IN clause
     *
     * @example
     * $query->whereIn('id', [1, 2, 3]);
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND'): self
    {
        // ✅ Empty array kontrolü
        if (empty($values)) {
            // Boş array: hiçbir kayıt match etmemeli
            // WHERE 1 = 0 ekle (always false)
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

    /**
     * Add OR WHERE IN clause
     */
    public function orWhereIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, 'OR');
    }

    /**
     * Add WHERE NOT IN clause
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): self
    {
        // ✅ Empty array kontrolü
        if (empty($values)) {
            // Boş array: tüm kayıtlar match etmeli (NOT IN () = true for all)
            // WHERE 1 = 1 ekle (always true) veya hiçbir şey ekleme
            return $this; // No-op, tüm kayıtlar geçerli
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

    /**
     * Add WHERE NULL clause
     */
    public function whereNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add WHERE NOT NULL clause
     */
    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add WHERE BETWEEN clause
     */
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

    /**
     * Flatten nested column arrays
     */
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

    /**
     * Add ORDER BY clause
     *
     * @param string $column Column to order by
     * @param string $direction ASC or DESC
     * @return self
     *
     * @example
     * $query->orderBy('created_at', 'DESC');
     * $query->orderBy('name')->orderBy('age', 'DESC');
     */
    public function orderBy(string|Expression $column, string $direction = 'ASC'): self
    {
        // ✅ Expression ise direkt kullan
        if ($column instanceof Expression) {
            $this->orders[] = [
                'column' => $column,
                'direction' => '', // Expression kendi direction'ını içerir
            ];
            return $this;
        }

        // ✅ String ise validate et
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name for ORDER BY: {$column}");
        }

        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException("Invalid direction for ORDER BY: {$direction}");
        }

        $this->orders[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    public function orderByRaw(string $sql): self
    {
        return $this->orderBy(Expression::make($sql));
    }



    /**
     * Order by latest (created_at DESC)
     */
    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Order by oldest (created_at ASC)
     */
    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'ASC');
    }

    /**
     * Add GROUP BY clause
     *
     * @param string|array $columns Columns to group by
     * @return self
     *
     * @example
     * $query->groupBy('status');
     * $query->groupBy(['department', 'status']);
     */
    public function groupBy(string|array ...$columns): self
    {
        $this->groups = array_merge(
            $this->groups,
            $this->flattenColumns($columns)
        );

        return $this;
    }

    /**
     * Add HAVING clause
     *
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Value to compare
     * @return self
     *
     * @example
     * $query->groupBy('status')
     *       ->having('COUNT(*)', '>', 10);
     */
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

    /**
     * Set LIMIT
     *
     * @param int $limit Maximum number of rows
     * @return self
     *
     * @example
     * $query->limit(10);
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit > 0 ? $limit : null;

        return $this;
    }

    /**
     * Alias for limit()
     */
    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    /**
     * Set OFFSET
     *
     * @param int $offset Number of rows to skip
     * @return self
     *
     * @example
     * $query->offset(20);
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset >= 0 ? $offset : null;

        return $this;
    }

    /**
     * Alias for offset()
     */
    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    /**
     * Build SQL query string
     *
     * @return string The SQL query
     */
    public function toSql(): string
    {
        $sql = [];

        // SELECT
        $sql[] = 'SELECT ' . $this->buildColumns();

        // FROM
        if ($this->from) {
            $sql[] = 'FROM ' . $this->from;
        }

        // JOINs
        if (!empty($this->joins)) {
            $sql[] = $this->buildJoins();
        }

        // WHERE
        if (!empty($this->wheres)) {
            $sql[] = 'WHERE ' . $this->buildWheres();
        }

        // GROUP BY
        if (!empty($this->groups)) {
            $sql[] = 'GROUP BY ' . implode(', ', $this->groups);
        }

        // HAVING
        if (!empty($this->havings)) {
            $sql[] = 'HAVING ' . $this->buildHavings();
        }

        // ORDER BY
        if (!empty($this->orders)) {
            $sql[] = 'ORDER BY ' . $this->buildOrders();
        }

        // LIMIT
        if (!is_null($this->limit)) {
            $sql[] = 'LIMIT ' . $this->limit;
        }

        // OFFSET
        if (!is_null($this->offset)) {
            $sql[] = 'OFFSET ' . $this->offset;
        }

        return implode(' ', $sql);
    }

    /**
     * Build columns string
     */
    protected function buildColumns(): string
    {
        $columns = array_map(function ($column) {
            if ($column instanceof Expression) {
                return $column->getValue();
            }
            return $column;
        }, $this->columns);

        return implode(', ', $columns);
    }

    /**
     * Build JOINs string
     */
    protected function buildJoins(): string
    {
        return implode(' ', array_map(function ($join) {
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

    /**
     * Build WHERE clauses
     */
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
                'raw' => $boolean . $where['sql'], // ✅ YENİ
                default => '',
            };
        }

        return implode(' ', $sql);
    }

    /**
     * Build HAVING clauses
     */
    protected function buildHavings(): string
    {
        return implode(' AND ', array_map(function ($having) {
            return $having['column'] . ' ' . $having['operator'] . ' ?';
        }, $this->havings));
    }

    /**
     * Build ORDER BY string
     */
    protected function buildOrders(): string
    {
        return implode(', ', array_map(function ($order) {
            if ($order['column'] instanceof Expression) {
                return $order['column']->getValue();
            }

            return $order['column'] . ' ' . $order['direction'];
        }, $this->orders));
    }

    /**
     * Create parameter placeholders
     */
    protected function parameterize(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    /**
     * Get all bindings
     */
    public function getBindings(): array
    {
        return array_merge($this->bindings, $this->havingBindings);
    }

    /**
     * Execute query and get all results
     *
     * @return array Query results
     * @throws DatabaseException
     *
     * @example
     * $users = $query->select('*')->from('users')->get();
     */
    public function get(): Collection
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
                "Query failed: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
                $sql ?? null,
                $bindings ?? null
            );
        }
    }

    /**
     * Execute query and get first result
     *
     * Note: Return type is mixed to allow child classes (Model Builder) 
     * to return specific types (?Model instead of ?array).
     * This enables covariant return types in the Builder class.
     *
     * @return mixed First row as array, Model instance (in Builder), or null
     *
     * @example
     * // In QueryBuilder
     * $row = DB::table('users')->first();  // Returns ?array
     * 
     * // In Model Builder
     * $user = User::query()->first();  // Returns ?Model
     */
    public function first(): ?Model
    {
        $this->limit(1);
        $results = $this->get();

        return $results[0] ?? null;
    }

    /**
     * Get count of results
     *
     * @return int Number of rows
     *
     * @example
     * $count = $query->where('status', '=', 'active')->count();
     */
    public function count(): int
    {
        $original = $this->columns;
        $this->columns = [Expression::make('COUNT(*) as aggregate')];

        $result = $this->first();

        $this->columns = $original;

        return (int) ($result['aggregate'] ?? 0);
    }

    /**
     * Check if any results exist
     *
     * @return bool True if results exist
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Check if no results exist
     */
    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    /**
     * Get sum of column
     *
     * @param string $column Column to sum
     * @return float Sum value
     *
     * @example
     * $total = $query->sum('amount');
     */
    public function sum(string $column): float
    {
        return $this->aggregate('SUM', $column);
    }

    /**
     * Get average of column
     */
    public function avg(string $column): float
    {
        return $this->aggregate('AVG', $column);
    }

    /**
     * Get minimum value
     */
    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    /**
     * Get maximum value
     */
    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    /**
     * Execute aggregate function
     */
    protected function aggregate(string $function, string $column): mixed
    {
        $original = $this->columns;
        $this->columns = [Expression::make("{$function}({$column}) as aggregate")];

        $result = $this->first();

        $this->columns = $original;

        return $result['aggregate'] ?? null;
    }

    /**
     * Insert data into table
     *
     * @param array $data Associative array of column => value
     * @return string|false Last insert ID or false on failure
     * @throws DatabaseException
     *
     * @example
     * $id = $query->from('users')->insert([
     *     'name' => 'John Doe',
     *     'email' => 'john@example.com'
     * ]);
     */
    public function insert(array $data): string|false
    {
        if (empty($data)) {
            throw new DatabaseException('Cannot insert empty data');
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
                "Insert failed: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
                $sql ?? null,
                $values ?? null
            );
        }
    }

    /**
     * Insert multiple rows
     *
     * @param array $data Array of associative arrays
     * @return bool Success status
     *
     * @example
     * $query->insertMultiple([
     *     ['name' => 'John', 'age' => 30],
     *     ['name' => 'Jane', 'age' => 25]
     * ]);
     */
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

    /**
     * Update rows
     *
     * @param array $data Associative array of column => value
     * @return int Number of affected rows
     * @throws DatabaseException
     *
     * @example
     * $affected = $query->where('id', '=', 1)
     *                   ->update(['status' => 'active']);
     */
    public function update(array $data): int
    {
        if (empty($data)) {
            throw new DatabaseException('Cannot update with empty data');
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
                "Update failed: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
                $sql ?? null,
                $values ?? null
            );
        }
    }

    /**
     * Delete rows
     *
     * @return int Number of deleted rows
     * @throws DatabaseException
     *
     * @example
     * $deleted = $query->where('status', '=', 'inactive')->delete();
     */
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
                "Delete failed: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
                $sql ?? null,
                $this->bindings
            );
        }
    }

    /**
     * Truncate table
     *
     * @return bool Success status
     */
    public function truncate(): bool
    {
        try {
            $sql = "TRUNCATE TABLE {$this->from}";
            $this->connection->getPdo()->exec($sql);

            return true;
        } catch (\PDOException $e) {
            throw new DatabaseException(
                "Truncate failed: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Paginate results
     *
     * @param int $page Current page (1-based)
     * @param int $perPage Items per page
     * @return array Pagination data
     *
     * @example
     * $result = $query->from('users')->paginate(2, 15);
     * // [
     * //   'data' => [...],
     * //   'total' => 100,
     * //   'per_page' => 15,
     * //   'current_page' => 2,
     * //   'last_page' => 7,
     * //   'from' => 16,
     * //   'to' => 30
     * // ]
     */
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        // ✅ Query'yi clone et (count() state'i değiştiriyor)
        $countQuery = clone $this;
        $total = $countQuery->count();

        // Calculate pagination
        $lastPage = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + $perPage, $total);

        // ✅ Original query'yi kullan
        $this->limit($perPage)->offset($offset);
        $data = $this->get();

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
     * Execute callback within transaction
     *
     * @param callable $callback Transaction callback
     * @return mixed Callback return value
     * @throws \Throwable
     *
     * @example
     * $result = $query->transaction(function($query) {
     *     $query->from('users')->insert(['name' => 'John']);
     *     $query->from('logs')->insert(['action' => 'user_created']);
     *     return true;
     * });
     */
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

    /**
     * Execute raw SQL query
     *
     * @param string $sql SQL query
     * @param array $bindings Query bindings
     * @return array Query results
     *
     * @example
     * $results = $query->raw('SELECT * FROM users WHERE age > ?', [18]);
     */
    public function raw(string $sql, array $bindings = []): array
    {
        return $this->connection->query($sql, $bindings);
    }

    /**
     * Process results in chunks
     *
     * @param int $size Chunk size
     * @param callable $callback Callback for each chunk
     * @return bool Success status
     *
     * @example
     * $query->from('users')->chunk(100, function($users) {
     *     foreach ($users as $user) {
     *         // Process user
     *     }
     * });
     */
    public function chunk(int $size, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->paginate($page, $size)['data'];

            if (empty($results)) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
        } while (count($results) === $size);

        return true;
    }

    /**
     * Dump query SQL and bindings
     */
    public function dump(): self
    {
        dump([
            'sql' => $this->toSql(),
            'bindings' => $this->getBindings(),
        ]);

        return $this;
    }

    /**
     * Dump query and die
     */
    public function dd(): never
    {
        dd([
            'sql' => $this->toSql(),
            'bindings' => $this->getBindings(),
        ]);
    }

    /**
     * Clone query builder
     */
    public function __clone()
    {
        // Arrays'leri deep clone et (referans kopyalanmasın)
        $this->columns = [...$this->columns];
        $this->wheres = array_map(function($where) {
            return is_array($where) ? [...$where] : $where;
        }, $this->wheres);
        $this->bindings = [...$this->bindings];
        $this->joins = array_map(function($join) {
            return [...$join];
        }, $this->joins);
        $this->orders = array_map(function($order) {
            return [...$order];
        }, $this->orders);
        $this->groups = [...$this->groups];
        $this->havings = array_map(function($having) {
            return [...$having];
        }, $this->havings);
        $this->havingBindings = [...$this->havingBindings];
    }

    /**
     * Convert to string (SQL)
     */
    public function __toString(): string
    {
        return $this->toSql();
    }
}
