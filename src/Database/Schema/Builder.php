<?php

declare(strict_types=1);

namespace Zephyr\Database\Schema;

use Zephyr\Database\Connection;
use Closure;

class Builder
{
    protected Connection $connection;
    protected Grammar $grammar;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;

        // Veritabanı sürücüsüne göre doğru Grammar'ı seç
        // (Şimdilik sadece MySQL varsayıyoruz, burası genişletilebilir)
        $driver = $connection->getConfig()['driver'] ?? 'mysql';

        $this->grammar = match ($driver) {
            'mysql' => new MySqlGrammar(),
            // 'sqlite' => new SqliteGrammar(), // Gelecekte eklenebilir
            default => throw new \Exception("Unsupported schema driver: {$driver}"),
        };
    }

    /**
     * Yeni bir tablo oluşturur.
     */
    public function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint); // Geliştirici $table->string('name')... çağrılarını yapar

        $sql = $this->grammar->compileCreate($blueprint);

        $this->connection->getPdo()->exec($sql);
    }

    /**
     * Bir tabloyu (varsa) siler.
     */
    public function dropIfExists(string $table): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->dropIfExists();

        // Blueprint'teki komutları al
        $command = $blueprint->getCommands()[0];
        $sql = $this->grammar->compileDropIfExists($blueprint);

        $this->connection->getPdo()->exec($sql);
    }
}