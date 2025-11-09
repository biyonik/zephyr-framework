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
 * Fluent interface ile güvenli SQL sorguları oluşturur.
 * Prepared statement kullanarak SQL injection'dan korur.
 *
 * Bu sınıf Model'den bağımsızdır ve sadece array döndürür.
 * Model-aware işlemler için Query\Builder kullanılır.
 *
 * Özellikler:
 * - SELECT, INSERT, UPDATE, DELETE
 * - WHERE, JOIN, ORDER BY, GROUP BY, HAVING
 * - Aggregate fonksiyonlar (COUNT, SUM, AVG, MIN, MAX)
 * - Pagination
 * - Transaction
 * - Chunk processing
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class QueryBuilder
{
    /**
     * Sorgu bileşenleri
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
     * SELECT sütunlarını belirler
     *
     * @param string|array|Expression ...$columns Sütun adları
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
     * Mevcut SELECT'e sütun ekler
     *
     * @param string|array|Expression ...$columns
     * @return self
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
     * FROM tablosunu belirler
     *
     * @param string $table Tablo adı
     * @param string|null $alias Tablo alias'ı
     * @return self
     */
    public function from(string $table, ?string $alias = null): self
    {
        $this->from = $alias ? "{$table} AS {$alias}" : $table;
        return $this;
    }

    /**
     * INNER JOIN ekler
     *
     * @param string $table Join edilecek tablo
     * @param string $first İlk sütun
     * @param string $operator Karşılaştırma operatörü
     * @param string $second İkinci sütun
     * @return self
     */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin('INNER', $table, $first, $operator, $second);
    }

    /**
     * LEFT JOIN ekler
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin('LEFT', $table, $first, $operator, $second);
    }

    /**
     * RIGHT JOIN ekler
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->addJoin('RIGHT', $table, $first, $operator, $second);
    }

    /**
     * JOIN clause ekler
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
     * WHERE koşulu ekler
     *
     * @param string $column Sütun adı
     * @param string $operator Operatör (=, !=, >, <, >=, <=, LIKE)
     * @param mixed $value Değer
     * @param string $boolean Boolean operatör (AND, OR)
     * @return self
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
     * OR WHERE koşulu ekler
     */
    public function orWhere(string $column, string $operator, mixed $value): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * WHERE IN koşulu ekler
     *
     * @param string $column Sütun adı
     * @param array $values Değerler
     * @param string $boolean Boolean operatör
     * @return self
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND'): self
    {
        if (empty($values)) {
            // Boş array: hiçbir kayıt eşleşmemeli
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

    /**
     * OR WHERE IN koşulu ekler
     */
    public function orWhereIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, 'OR');
    }

    /**
     * WHERE NOT IN koşulu ekler
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): self
    {
        if (empty($values)) {
            // Boş array: tüm kayıtlar eşleşmeli
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

    /**
     * WHERE NULL koşulu ekler
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
     * WHERE NOT NULL koşulu ekler
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
     * WHERE BETWEEN koşulu ekler
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
     * Ham WHERE koşulu ekler
     *
     * @param string $sql Ham SQL
     * @param array $bindings Parametreler
     * @param string $boolean Boolean operatör
     * @return self
     */
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
     * ORDER BY ekler
     *
     * @param string|Expression $column Sütun
     * @param string $direction Yön (ASC, DESC)
     * @return self
     */
    public function orderBy(string|Expression $column, string $direction = 'ASC'): self
    {
        if ($column instanceof Expression) {
            $this->orders[] = [
                'column' => $column,
                'direction' => '',
            ];
            return $this;
        }

        // Sütun adı validasyonu
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $column)) {
            throw new \InvalidArgumentException("Geçersiz sütun adı: {$column}");
        }

        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException("Geçersiz sıralama yönü: {$direction}");
        }

        $this->orders[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * Ham ORDER BY ekler
     */
    public function orderByRaw(string $sql): self
    {
        return $this->orderBy(Expression::make($sql));
    }

    /**
     * En yeniden eskiliye sıralar (created_at DESC)
     */
    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * En eskiden yeniye sıralar (created_at ASC)
     */
    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'ASC');
    }

    /**
     * GROUP BY ekler
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
     * HAVING koşulu ekler
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
     * LIMIT belirler
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit > 0 ? $limit : null;
        return $this;
    }

    /**
     * Limit için alias
     */
    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    /**
     * OFFSET belirler
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset >= 0 ? $offset : null;
        return $this;
    }

    /**
     * Offset için alias
     */
    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    /**
     * SQL sorgusunu string olarak döndürür
     *
     * @return string
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
     * Sorgudaki tüm binding'leri döndürür
     *
     * @return array
     */
    public function getBindings(): array
    {
        return array_merge($this->bindings, $this->havingBindings);
    }

    /**
     * Sorguyu çalıştırır ve tüm sonuçları döndürür
     *
     * NOT: Bu metot array döndürür. Model nesnesi için Query\Builder kullanın.
     *
     * @return array Sonuç satırları
     * @throws DatabaseException
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
                "Sorgu hatası: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
                $sql ?? null,
                $bindings ?? null
            );
        }
    }

    /**
     * İlk sonucu döndürür
     *
     * NOT: Bu metot ?array döndürür. Model nesnesi için Query\Builder kullanın.
     *
     * @return array|null
     */
    public function first(): ?Model
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Kayıt sayısını döndürür
     *
     * @return int
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
     * Sonuç var mı kontrol eder
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Sonuç yok mu kontrol eder
     *
     * @return bool
     */
    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    /**
     * Sütun toplamını döndürür
     */
    public function sum(string $column): float
    {
        return (float) $this->aggregate('SUM', $column);
    }

    /**
     * Sütun ortalamasını döndürür
     */
    public function avg(string $column): float
    {
        return (float) $this->aggregate('AVG', $column);
    }

    /**
     * Minimum değeri döndürür
     */
    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    /**
     * Maximum değeri döndürür
     */
    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    /**
     * Aggregate fonksiyon çalıştırır
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
     * INSERT sorgusu çalıştırır
     *
     * @param array $data Sütun => Değer
     * @return string|false Son eklenen ID
     * @throws DatabaseException
     */
    public function insert(array $data): string|false
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

    /**
     * Çoklu INSERT sorgusu
     *
     * @param array $data Satırlar (array of arrays)
     * @return bool
     * @throws \Throwable
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
     * UPDATE sorgusu çalıştırır
     *
     * @param array $data Güncellenecek veri
     * @return int Etkilenen satır sayısı
     * @throws DatabaseException
     */
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

    /**
     * DELETE sorgusu çalıştırır
     *
     * @return int Silinen satır sayısı
     * @throws DatabaseException
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
                "DELETE hatası: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
                $sql ?? null,
                $this->bindings
            );
        }
    }

    /**
     * Tabloyu truncate eder
     *
     * @return bool
     */
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

    /**
     * Sayfalama yapar
     *
     * @param int $page Sayfa numarası
     * @param int $perPage Sayfa başına kayıt
     * @return array Sayfalama verileri
     */
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        // Query'yi clone et (count() state'i değiştiriyor)
        $countQuery = clone $this;
        $total = $countQuery->count();

        // Pagination hesapla
        $lastPage = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + $perPage, $total);

        // Veriyi çek
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
     * Transaction içinde çalıştırır
     *
     * @param callable $callback
     * @return mixed
     * @throws \Throwable
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
     * Ham SQL çalıştırır
     *
     * @param string $sql
     * @param array $bindings
     * @return array
     */
    public function raw(string $sql, array $bindings = []): array
    {
        return $this->connection->query($sql, $bindings);
    }

    /**
     * Chunk processing
     *
     * @param int $size Chunk boyutu
     * @param callable $callback Her chunk için callback
     * @return bool
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
     * SQL ve binding'leri dump eder
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
     * SQL ve binding'leri dump edip çıkar
     */
    public function dd(): never
    {
        dd([
            'sql' => $this->toSql(),
            'bindings' => $this->getBindings(),
        ]);
    }

    /**
     * Nested array'leri düzleştirir
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
     * Sütun string'ini oluşturur
     */
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

    /**
     * JOIN string'ini oluşturur
     */
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

    /**
     * WHERE clause'larını oluşturur
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
                'raw' => $boolean . $where['sql'],
                default => '',
            };
        }

        return implode(' ', $sql);
    }

    /**
     * HAVING clause'larını oluşturur
     */
    protected function buildHavings(): string
    {
        return implode(' AND ', array_map(function ($having) {
            return $having['column'] . ' ' . $having['operator'] . ' ?';
        }, $this->havings));
    }

    /**
     * ORDER BY string'ini oluşturur
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
     * Parametre placeholder'ları oluşturur
     */
    protected function parameterize(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    /**
     * Query'yi clone eder
     */
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

    /**
     * String'e çevirir
     */
    public function __toString(): string
    {
        return $this->toSql();
    }

    public function __call(string $name, array $arguments)
    {

    }
}