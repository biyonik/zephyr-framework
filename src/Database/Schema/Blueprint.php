<?php

declare(strict_types=1);

namespace Zephyr\Database\Schema;

class Blueprint
{
    protected string $table;
    protected array $columns = [];
    protected array $commands = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Otomatik artan 'id' (BIGINT UNSIGNED) sütunu ekler.
     */
    public function id(string $column = 'id'): void
    {
        $this->columns[] = [
            'type' => 'bigInteger',
            'name' => $column,
            'autoIncrement' => true,
            'unsigned' => true,
            'primary' => true,
        ];
    }

    /**
     * Bir VARCHAR sütunu ekler.
     */
    public function string(string $name, int $length = 255): object
    {
        $this->columns[] = $column = [
            'type' => 'string',
            'name' => $name,
            'length' => $length,
            'nullable' => false,
        ];
        // Zincirleme (chaining) için son sütunu döndürelim
        return $this->lastColumn();
    }

    /**
     * Bir INTEGER sütunu ekler.
     */
    public function integer(string $name): object
    {
        $this->columns[] = [
            'type' => 'integer',
            'name' => $name,
            'nullable' => false,
        ];
        return $this->lastColumn();
    }

    /**
     * Bir TEXT sütunu ekler.
     */
    public function text(string $name): object
    {
        $this->columns[] = [
            'type' => 'text',
            'name' => $name,
            'nullable' => false,
        ];
        return $this->lastColumn();
    }

    /**
     * 'created_at' ve 'updated_at' TIMESTAMP sütunlarını ekler.
     */
    public function timestamps(): void
    {
        $this->columns[] = [
            'type' => 'timestamp',
            'name' => 'created_at',
            'nullable' => true,
            'default' => 'CURRENT_TIMESTAMP',
        ];
        $this->columns[] = [
            'type' => 'timestamp',
            'name' => 'updated_at',
            'nullable' => true,
            'default' => 'CURRENT_TIMESTAMP', // Veya 'ON UPDATE CURRENT_TIMESTAMP'
        ];
    }

    // --- Zincirleme Metotlar (Modifiers) ---

    /**
     * Sütunun 'NULL' değer kabul etmesine izin verir.
     */
    public function nullable(): object
    {
        $this->lastColumn()->nullable = true;
        return $this->lastColumn();
    }

    /**
     * Sütun için varsayılan bir değer atar.
     */
    public function default(mixed $value): object
    {
        $this->lastColumn()->default = $value;
        return $this->lastColumn();
    }

    /**
     * Sütuna 'UNIQUE' kısıtlaması ekler.
     */
    public function unique(): object
    {
        $this->lastColumn()->unique = true;
        return $this->lastColumn();
    }

    /**
     * 'DROP TABLE' komutu ekler.
     */
    public function drop(): void
    {
        $this->commands[] = ['type' => 'drop'];
    }

    /**
     * 'DROP TABLE IF EXISTS' komutu ekler.
     */
    public function dropIfExists(): void
    {
        $this->commands[] = ['type' => 'dropIfExists'];
    }

    // --- Dahili Yardımcılar ---

    /**
     * Zincirleme için son eklenen sütun nesnesini döndürür.
     */
    protected function lastColumn(): object
    {
        return (object) $this->columns[count($this->columns) - 1];
    }

    /**
     * Tanımlanan sütunları alır.
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Tanımlanan komutları (drop vb.) alır.
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getTable(): string
    {
        return $this->table;
    }
}