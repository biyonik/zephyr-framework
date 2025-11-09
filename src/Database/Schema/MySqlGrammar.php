<?php

declare(strict_types=1);

namespace Zephyr\Database\Schema;

/**
 * MySQL Grammar
 *
 * MySQL veritabanı için schema SQL'lerini üretir.
 * Blueprint'i MySQL SQL sorgusuna çevirir.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class MySqlGrammar extends Grammar
{
    /**
     * CREATE TABLE SQL'ini derler
     *
     * @param Blueprint $blueprint Tablo şeması
     * @return string MySQL CREATE TABLE sorgusu
     */
    public function compileCreate(Blueprint $blueprint): string
    {
        $columnsSql = [];

        foreach ($blueprint->getColumns() as $column) {
            $columnsSql[] = '  ' . $this->compileColumn($column);
        }

        $sql = "CREATE TABLE IF NOT EXISTS `{$blueprint->getTable()}` (\n";
        $sql .= implode(",\n", $columnsSql);
        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        return $sql;
    }

    /**
     * DROP TABLE SQL'ini derler
     *
     * @param Blueprint $blueprint Tablo şeması
     * @return string MySQL DROP TABLE sorgusu
     */
    public function compileDrop(Blueprint $blueprint): string
    {
        return "DROP TABLE `{$blueprint->getTable()}`";
    }

    /**
     * DROP TABLE IF EXISTS SQL'ini derler
     *
     * @param Blueprint $blueprint Tablo şeması
     * @return string MySQL DROP TABLE IF EXISTS sorgusu
     */
    public function compileDropIfExists(Blueprint $blueprint): string
    {
        return "DROP TABLE IF EXISTS `{$blueprint->getTable()}`";
    }

    /**
     * Sütun tipini MySQL tipine çevirir
     *
     * @param array $column Sütun tanımı
     * @return string MySQL sütun tipi
     */
    protected function getTypeString(array $column): string
    {
        $unsigned = ($column['unsigned'] ?? false) ? ' UNSIGNED' : '';

        return match ($column['type']) {
            'string' => "VARCHAR({$column['length']})",
            'text' => 'TEXT',
            'integer' => 'INT' . $unsigned,
            'bigInteger' => 'BIGINT' . $unsigned,
            'tinyInteger' => 'TINYINT' . $unsigned,
            'boolean' => 'TINYINT(1)',
            'decimal' => "DECIMAL({$column['precision']}, {$column['scale']})",
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            'timestamp' => 'TIMESTAMP',
            'json' => 'JSON',
            'enum' => $this->compileEnum($column),
            default => 'VARCHAR(255)',
        };
    }

    /**
     * ENUM tipini derler
     *
     * @param array $column Sütun tanımı
     * @return string MySQL ENUM tipi
     */
    protected function compileEnum(array $column): string
    {
        $values = array_map(fn($v) => "'" . addslashes($v) . "'", $column['values']);
        return 'ENUM(' . implode(', ', $values) . ')';
    }
}