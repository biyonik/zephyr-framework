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
     * Otomatik artan 'id' sütunu ekler.
     */
    public function id(string $column = 'id'): ColumnDefinition
    {
        $this->columns[] = [
            'type' => 'bigInteger',
            'name' => $column,
            'autoIncrement' => true,
            'unsigned' => true,
            'primary' => true,
            'nullable' => false,
        ];

        return $this->lastColumn();
    }

    /**
     * VARCHAR sütunu ekler.
     */
    public function string(string $name, int $length = 255): ColumnDefinition
    {
        $this->columns[] = [
            'type' => 'string',
            'name' => $name,
            'length' => $length,
            'nullable' => false,
        ];

        return $this->lastColumn();
    }

    /**
     * INTEGER sütunu ekler.
     */
    public function integer(string $name): ColumnDefinition
    {
        $this->columns[] = [
            'type' => 'integer',
            'name' => $name,
            'nullable' => false,
        ];

        return $this->lastColumn();
    }

    /**
     * TEXT sütunu ekler.
     */
    public function text(string $name): ColumnDefinition
    {
        $this->columns[] = [
            'type' => 'text',
            'name' => $name,
            'nullable' => false,
        ];

        return $this->lastColumn();
    }

    /**
     * Timestamps ekler.
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
            'default' => 'CURRENT_TIMESTAMP',
        ];
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

    /**
     * ✅ FIX: Son sütun için ColumnDefinition wrapper döndürür.
     */
    protected function lastColumn(): ColumnDefinition
    {
        $lastIndex = count($this->columns) - 1;
        return new ColumnDefinition($this->columns[$lastIndex]);
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getTable(): string
    {
        return $this->table;
    }
}