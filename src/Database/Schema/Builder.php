<?php

declare(strict_types=1);

namespace Zephyr\Database\Schema;

use Zephyr\Database\Connection;
use Closure;

/**
 * Schema Builder
 *
 * Database şemasını yönetir:
 * - Tablo oluşturma (create)
 * - Tablo silme (drop, dropIfExists)
 * - Tablo varlık kontrolü (hasTable)
 * - Sütun varlık kontrolü (hasColumn)
 *
 * Kullanım:
 * use Zephyr\Database\Schema\Builder as Schema;
 *
 * Schema::create('users', function (Blueprint $table) {
 *     $table->id();
 *     $table->string('name');
 *     $table->timestamps();
 * });
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Builder
{
    /**
     * Database bağlantısı
     */
    protected Connection $connection;

    /**
     * SQL grammar (MySQL, PostgreSQL, vb.)
     */
    protected Grammar $grammar;

    /**
     * Constructor
     *
     * @param Connection $connection Database bağlantısı
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;

        // Database sürücüsüne göre grammar seç
        $driver = $connection->getConfig()['driver'] ?? 'mysql';

        $this->grammar = match ($driver) {
            'mysql' => new MySqlGrammar(),
            // 'pgsql' => new PostgresGrammar(), // Gelecekte
            // 'sqlite' => new SqliteGrammar(), // Gelecekte
            default => throw new \Exception("Desteklenmeyen schema sürücüsü: {$driver}"),
        };
    }

    /**
     * Yeni tablo oluşturur
     *
     * @param string $table Tablo adı
     * @param Closure $callback Blueprint callback
     * @return void
     *
     * @example
     * Schema::create('users', function (Blueprint $table) {
     *     $table->id();
     *     $table->string('name');
     *     $table->string('email')->unique();
     *     $table->timestamps();
     * });
     */
    public function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $sql = $this->grammar->compileCreate($blueprint);
        $this->connection->getPdo()->exec($sql);
    }

    /**
     * Tabloyu siler (DROP TABLE)
     *
     * @param string $table Tablo adı
     * @return void
     */
    public function drop(string $table): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->drop();

        $sql = $this->grammar->compileDrop($blueprint);
        $this->connection->getPdo()->exec($sql);
    }

    /**
     * Tablo varsa siler (DROP TABLE IF EXISTS)
     *
     * @param string $table Tablo adı
     * @return void
     */
    public function dropIfExists(string $table): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->dropIfExists();

        $sql = $this->grammar->compileDropIfExists($blueprint);
        $this->connection->getPdo()->exec($sql);
    }

    /**
     * Tablo var mı kontrol eder
     *
     * @param string $table Tablo adı
     * @return bool
     */
    public function hasTable(string $table): bool
    {
        $sql = "SHOW TABLES LIKE ?";
        $result = $this->connection->query($sql, [$table]);

        return count($result) > 0;
    }

    /**
     * Tabloda sütun var mı kontrol eder
     *
     * @param string $table Tablo adı
     * @param string $column Sütun adı
     * @return bool
     */
    public function hasColumn(string $table, string $column): bool
    {
        $sql = "SHOW COLUMNS FROM `{$table}` LIKE ?";
        $result = $this->connection->query($sql, [$column]);

        return count($result) > 0;
    }

    /**
     * Tablonun tüm sütunlarını döndürür
     *
     * @param string $table Tablo adı
     * @return array Sütun listesi
     */
    public function getColumnListing(string $table): array
    {
        $sql = "SHOW COLUMNS FROM `{$table}`";
        $results = $this->connection->query($sql);

        return array_map(fn($row) => $row['Field'], $results);
    }

    /**
     * Grammar instance'ını döndürür
     *
     * @return Grammar
     */
    public function getGrammar(): Grammar
    {
        return $this->grammar;
    }

    /**
     * Connection instance'ını döndürür
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }
}