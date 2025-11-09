<?php

declare(strict_types=1);

namespace Zephyr\Database;

use PDO;
use PDOException;
use Zephyr\Database\Exception\DatabaseException;
use Zephyr\Support\Config;

/**
 * Veritabanı Bağlantı Yöneticisi
 *
 * PDO bağlantılarını singleton pattern ile yönetir.
 * Lazy connection ve connection pooling sağlar.
 *
 * Özellikler:
 * - Singleton pattern (tek instance)
 * - Lazy connection (ihtiyaç anında bağlanır)
 * - Transaction yönetimi
 * - Prepared statement desteği
 * - Otomatik hata yönetimi
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Connection
{
    /**
     * Aktif PDO bağlantısı
     */
    protected ?PDO $pdo = null;

    /**
     * Bağlantı konfigürasyonu
     */
    protected array $config;

    /**
     * Singleton instance
     */
    protected static ?self $instance = null;

    /**
     * Constructor (private - singleton için)
     */
    private function __construct(array $config = [])
    {
        $this->config = $config ?: $this->getDefaultConfig();
    }

    /**
     * Singleton instance'ı döndürür
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (is_null(self::$instance)) {
            $config = [
                'driver' => Config::get('database.default', 'mysql'),
                'host' => Config::get('database.connections.mysql.host', '127.0.0.1'),
                'port' => Config::get('database.connections.mysql.port', 3306),
                'database' => Config::get('database.connections.mysql.database', ''),
                'username' => Config::get('database.connections.mysql.username', 'root'),
                'password' => Config::get('database.connections.mysql.password', ''),
                'charset' => Config::get('database.connections.mysql.charset', 'utf8mb4'),
                'collation' => Config::get('database.connections.mysql.collation', 'utf8mb4_unicode_ci'),
            ];

            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * PDO bağlantısını döndürür (lazy)
     *
     * İlk çağrıda bağlantı kurulur, sonraki çağrılarda cache'den döner.
     *
     * @return PDO
     * @throws DatabaseException
     */
    public function getPdo(): PDO
    {
        if (is_null($this->pdo)) {
            $this->connect();
        }

        return $this->pdo;
    }

    /**
     * Veritabanına bağlanır
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

            // Hata modunu exception'a çevir
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Varsayılan fetch modunu associative array yap
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            throw new DatabaseException(
                "Veritabanı bağlantısı kurulamadı: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * DSN string'ini oluşturur
     *
     * @return string
     * @throws DatabaseException
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
            default => throw new DatabaseException("Desteklenmeyen veritabanı sürücüsü: {$driver}")
        };
    }

    /**
     * PDO seçeneklerini döndürür
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
    }

    /**
     * Varsayılan konfigürasyonu döndürür
     *
     * @return array
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
        ];
    }

    /**
     * Bağlantıyı kapatır
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }

    /**
     * Yeniden bağlanır
     */
    public function reconnect(): void
    {
        $this->disconnect();
        $this->connect();
    }

    /**
     * Bağlı mı kontrol eder
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return !is_null($this->pdo);
    }

    /**
     * Transaction başlatır
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->getPdo()->beginTransaction();
    }

    /**
     * Transaction'ı commit eder
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->getPdo()->commit();
    }

    /**
     * Transaction'ı rollback eder
     *
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->getPdo()->rollBack();
    }

    /**
     * Transaction içinde mi kontrol eder
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->getPdo()->inTransaction();
    }

    /**
     * Son eklenen kaydın ID'sini döndürür
     *
     * @param string|null $name Sequence adı (PostgreSQL için)
     * @return string
     */
    public function lastInsertId(?string $name = null): string
    {
        return $this->getPdo()->lastInsertId($name);
    }

    /**
     * Ham SQL sorgusu çalıştırır (SELECT)
     *
     * @param string $sql SQL sorgusu
     * @param array $bindings Parametre değerleri
     * @return array Sonuç satırları
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
                "Sorgu hatası: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
                $sql,
                $bindings
            );
        }
    }

    /**
     * Ham SQL statement çalıştırır (INSERT, UPDATE, DELETE)
     *
     * @param string $sql SQL statement
     * @param array $bindings Parametre değerleri
     * @return int Etkilenen satır sayısı
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
                "Statement hatası: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
                $sql,
                $bindings
            );
        }
    }

    /**
     * Konfigürasyonu döndürür (şifre hariç)
     *
     * @return array
     */
    public function getConfig(): array
    {
        $config = $this->config;
        unset($config['password']);
        return $config;
    }

    /**
     * Clone'u engelle (singleton)
     */
    private function __clone() {}

    /**
     * Unserialize'ı engelle (singleton)
     * @throws \Exception
     */
    public function __wakeup()
    {
        throw new \RuntimeException("Singleton sınıfı unserialize edilemez");
    }
}