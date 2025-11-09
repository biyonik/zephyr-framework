<?php

declare(strict_types=1);

namespace Zephyr\Database\Schema;

/**
 * Schema Blueprint
 *
 * Migration'larda tablo şemasını tanımlar.
 * Fluent interface ile sütun ve constraint'ler eklenir.
 *
 * Kullanım:
 * Schema::create('users', function (Blueprint $table) {
 *     $table->id();
 *     $table->string('name');
 *     $table->string('email')->unique();
 *     $table->timestamps();
 * });
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Blueprint
{
    /**
     * Tablo adı
     */
    protected string $table;

    /**
     * Sütun tanımları
     */
    protected array $columns = [];

    /**
     * Komutlar (drop, dropIfExists, vb.)
     */
    protected array $commands = [];

    /**
     * Constructor
     *
     * @param string $table Tablo adı
     */
    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Auto-increment primary key ekler (BIGINT UNSIGNED)
     *
     * @param string $column Sütun adı (varsayılan: 'id')
     * @return ColumnDefinition
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
     * VARCHAR sütunu ekler
     *
     * @param string $name Sütun adı
     * @param int $length Maksimum uzunluk
     * @return ColumnDefinition
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
     * TEXT sütunu ekler
     *
     * @param string $name Sütun adı
     * @return ColumnDefinition
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
     * INTEGER sütunu ekler
     *
     * @param string $name Sütun adı
     * @return ColumnDefinition
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
     * BIGINT sütunu ekler
     *
     * @param string $name Sütun adı
     * @return ColumnDefinition
     */
    public function bigInteger(string $name): ColumnDefinition
    {
        $this->columns[] = [
            'type' => 'bigInteger',
            'name' => $name,
            'nullable' => false,
        ];

        return $this->lastColumn();
    }

    /**
     * TINYINT sütunu ekler (0-255 veya -128 to 127)
     *
     * @param string $name Sütun adı
     * @return ColumnDefinition
     */
    public function tinyInteger(string $name): ColumnDefinition
    {
        $this->columns[] = [
            'type' => 'tinyInteger',
            'name' => $name,
            'nullable' => false,
        ];

        return $this->lastColumn();
    }

    /**
     * BOOLEAN sütunu ekler (TINYINT(1))
     *
     * @param string $name Sütun adı
     * @return ColumnDefinition
     */
    public function boolean(string $name): ColumnDefinition
    {
        $this->columns[] = [
            'type' => 'boolean',
            'name' => $name,
            'nullable' => false,
        ];

        return $this->lastColumn();
    }

    /**
     * DECIMAL sütunu ekler
     *
     * @param string $name Sütun adı
     * @param int $precision Toplam basamak sayısı
     * @param int $scale Ondalık basamak sayısı
     * @return ColumnDefinition
     */
    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        $this->columns[] = [
            'type' => 'decimal',
            'name' => $name,
            'precision' => $precision,
            'scale' => $scale,
            'nullable' => false,
        ];

        return $this->lastColumn();
    }

    /**
     * DATE sütunu ekler (YYYY-MM-DD)
     *
     * @param string $name Sütun adı
     * @return ColumnDefinition
     */
    public function date(string $name): ColumnDefinition
    {
        $this->columns[] = [
            'type' => 'date',
            'name' => $name,
            'nullable' => false,
        ];

        return $this->lastColumn();
    }

    /**
     * DATETIME sütunu ekler (YYYY-MM-DD HH:MM:SS)
     *
     * @param string $name Sütun adı
     * @return ColumnDefinition
     */
    public function dateTime(string $name): ColumnDefinition
    {
        $this->columns[] = [
            'type' => 'datetime',
            'name' => $name,
            'nullable' => false,
        ];

        return $this->lastColumn();
    }

    /**
     * TIMESTAMP sütunu ekler
     *
     * @param string $name Sütun adı
     * @return ColumnDefinition
     */
    public function timestamp(string $name): ColumnDefinition
    {
        $this->columns[] = [
            'type' => 'timestamp',
            'name' => $name,
            'nullable' => true,
            'default' => 'CURRENT_TIMESTAMP',
        ];

        return $this->lastColumn();
    }

    /**
     * created_at ve updated_at sütunları ekler
     *
     * @return void
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
     * deleted_at sütunu ekler (soft delete için)
     *
     * @return ColumnDefinition
     */
    public function softDeletes(): ColumnDefinition
    {
        $this->columns[] = [
            'type' => 'timestamp',
            'name' => 'deleted_at',
            'nullable' => true,
        ];

        return $this->lastColumn();
    }

    /**
     * Foreign key sütunu ekler (BIGINT UNSIGNED)
     *
     * @param string $name Sütun adı (örn: 'user_id')
     * @return ColumnDefinition
     */
    public function foreignId(string $name): ColumnDefinition
    {
        $this->columns[] = [
            'type' => 'bigInteger',
            'name' => $name,
            'unsigned' => true,
            'nullable' => false,
        ];

        return $this->lastColumn();
    }

    /**
     * JSON sütunu ekler
     *
     * @param string $name Sütun adı
     * @return ColumnDefinition
     */
    public function json(string $name): ColumnDefinition
    {
        $this->columns[] = [
            'type' => 'json',
            'name' => $name,
            'nullable' => false,
        ];

        return $this->lastColumn();
    }

    /**
     * ENUM sütunu ekler
     *
     * @param string $name Sütun adı
     * @param array $values İzin verilen değerler
     * @return ColumnDefinition
     */
    public function enum(string $name, array $values): ColumnDefinition
    {
        $this->columns[] = [
            'type' => 'enum',
            'name' => $name,
            'values' => $values,
            'nullable' => false,
        ];

        return $this->lastColumn();
    }

    /**
     * DROP TABLE komutu ekler
     *
     * @return void
     */
    public function drop(): void
    {
        $this->commands[] = ['type' => 'drop'];
    }

    /**
     * DROP TABLE IF EXISTS komutu ekler
     *
     * @return void
     */
    public function dropIfExists(): void
    {
        $this->commands[] = ['type' => 'dropIfExists'];
    }

    /**
     * Son eklenen sütun için ColumnDefinition döndürür
     *
     * @return ColumnDefinition
     */
    protected function lastColumn(): ColumnDefinition
    {
        $lastIndex = count($this->columns) - 1;
        return new ColumnDefinition($this->columns[$lastIndex]);
    }

    /**
     * Sütunları döndürür
     *
     * @return array
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Komutları döndürür
     *
     * @return array
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Tablo adını döndürür
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }
}