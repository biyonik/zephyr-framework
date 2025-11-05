<?php

declare(strict_types=1);

namespace Zephyr\Database;

use PDO;
use PDOException;
use Zephyr\Database\Exception\DatabaseException;
use Zephyr\Support\Config;

/**
 * Database Connection Manager
 *
 * Manages PDO connections with singleton pattern.
 * Provides connection pooling and lazy connection.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Connection
{
    /**
     * Active PDO connection
     */
    protected ?PDO $pdo = null;

    /**
     * Connection configuration
     */
    protected array $config;

    /**
     * Whether to use persistent connections
     */
    protected bool $persistent = false;

    /**
     * Connection instance (singleton)
     */
    protected static ?self $instance = null;

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        $this->config = $config ?: $this->getDefaultConfig();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get PDO connection (lazy)
     */
    public function getPdo(): PDO
    {
        if (is_null($this->pdo)) {
            $this->connect();
        }

        return $this->pdo;
    }

    /**
     * Establish database connection
     *
     * @throws DatabaseException
     */
    protected function connect(): void
    {
        try {
            $dsn = $this->buildDsn();
            $options = $this->getOptions();

            $this->pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $options
            );

            // Set error mode to exceptions
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Set default fetch mode to associative array
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            throw new DatabaseException(
                "Connection failed: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Build DSN string
     */
    protected function buildDsn(): string
    {
        $driver = $this->config['driver'] ?? 'mysql';
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 3306;
        $database = $this->config['database'] ?? '';
        $charset = $this->config['charset'] ?? 'utf8mb4';

        return match ($driver) {
            'mysql' => "mysql:host={$host};port={$port};dbname={$database};charset={$charset}",
            'pgsql' => "pgsql:host={$host};port={$port};dbname={$database}",
            'sqlite' => "sqlite:{$database}",
            default => throw new DatabaseException("Unsupported driver: {$driver}")
        };
    }

    /**
     * Get PDO options
     */
    protected function getOptions(): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        if (!empty($this->config['persistent'])) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }

        return $options;
    }

    /**
     * Get default configuration from config files
     */
    protected function getDefaultConfig(): array
    {
        return [
            'driver' => Config::get('database.default', 'mysql'),
            'host' => Config::get('database.connections.mysql.host', '127.0.0.1'),
            'port' => Config::get('database.connections.mysql.port', 3306),
            'database' => Config::get('database.connections.mysql.database', ''),
            'username' => Config::get('database.connections.mysql.username', 'root'),
            'password' => Config::get('database.connections.mysql.password', ''),
            'charset' => Config::get('database.connections.mysql.charset', 'utf8mb4'),
            'collation' => Config::get('database.connections.mysql.collation', 'utf8mb4_unicode_ci'),
        ];
    }

    /**
     * Disconnect from database
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }

    /**
     * Reconnect to database
     */
    public function reconnect(): void
    {
        $this->disconnect();
        $this->connect();
    }

    /**
     * Check if connected
     */
    public function isConnected(): bool
    {
        return !is_null($this->pdo);
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->getPdo()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->getPdo()->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->getPdo()->rollBack();
    }

    /**
     * Check if in transaction
     */
    public function inTransaction(): bool
    {
        return $this->getPdo()->inTransaction();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(?string $name = null): string
    {
        return $this->getPdo()->lastInsertId($name);
    }

    /**
     * Execute raw SQL query
     *
     * @throws DatabaseException
     */
    public function query(string $sql, array $bindings = []): array
    {
        try {
            $statement = $this->getPdo()->prepare($sql);
            $statement->execute($bindings);

            return $statement->fetchAll();

        } catch (PDOException $e) {
            throw new DatabaseException(
                "Query failed: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
                $sql,
                $bindings
            );
        }
    }

    /**
     * Execute statement (INSERT, UPDATE, DELETE)
     *
     * @return int Number of affected rows
     * @throws DatabaseException
     */
    public function statement(string $sql, array $bindings = []): int
    {
        try {
            $statement = $this->getPdo()->prepare($sql);
            $statement->execute($bindings);

            return $statement->rowCount();

        } catch (PDOException $e) {
            throw new DatabaseException(
                "Statement failed: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
                $sql,
                $bindings
            );
        }
    }

    /**
     * Get connection configuration (without password)
     */
    public function getConfig(): array
    {
        $config = $this->config;
        unset($config['password']);
        return $config;
    }
}