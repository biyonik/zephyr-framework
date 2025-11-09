<?php

declare(strict_types=1);

namespace Zephyr\Database\Schema;

/**
 * Schema Grammar (Base)
 *
 * Blueprint'ten SQL'e dönüşüm yapan temel sınıf.
 * Database'e özel grammar'lar (MySQL, PostgreSQL) bu sınıfı extend eder.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
abstract class Grammar
{
    /**
     * CREATE TABLE SQL'ini derler
     *
     * @param Blueprint $blueprint Tablo şeması
     * @return string SQL sorgusu
     */
    abstract public function compileCreate(Blueprint $blueprint): string;

    /**
     * DROP TABLE SQL'ini derler
     *
     * @param Blueprint $blueprint Tablo şeması
     * @return string SQL sorgusu
     */
    abstract public function compileDrop(Blueprint $blueprint): string;

    /**
     * DROP TABLE IF EXISTS SQL'ini derler
     *
     * @param Blueprint $blueprint Tablo şeması
     * @return string SQL sorgusu
     */
    abstract public function compileDropIfExists(Blueprint $blueprint): string;

    /**
     * Sütun tanımını SQL string'e derler
     *
     * @param array $column Sütun tanımı
     * @return string SQL sütun tanımı
     */
    protected function compileColumn(array $column): string
    {
        $sql = "`{$column['name']}` " . $this->getTypeString($column);

        // NOT NULL / NULL
        $sql .= ($column['nullable'] === false) ? ' NOT NULL' : ' NULL';

        // AUTO_INCREMENT
        if (isset($column['autoIncrement']) && $column['autoIncrement']) {
            $sql .= ' AUTO_INCREMENT';
        }

        // PRIMARY KEY
        if (isset($column['primary']) && $column['primary']) {
            $sql .= ' PRIMARY KEY';
        }

        // UNIQUE
        if (isset($column['unique']) && $column['unique']) {
            $sql .= ' UNIQUE';
        }

        // DEFAULT
        if (isset($column['default'])) {
            $sql .= ' DEFAULT ' . $this->formatDefault($column['default']);
        }

        // COMMENT
        if (isset($column['comment'])) {
            $sql .= " COMMENT '" . addslashes($column['comment']) . "'";
        }

        return $sql;
    }

    /**
     * Sütun tipini SQL tipine çevirir
     *
     * Alt sınıflar database'e özel tipleri implement eder.
     *
     * @param array $column Sütun tanımı
     * @return string SQL tip string'i
     */
    abstract protected function getTypeString(array $column): string;

    /**
     * Default değeri SQL için formatlar
     *
     * @param mixed $value Default değer
     * @return string Formatlanmış değer
     */
    protected function formatDefault(mixed $value): string
    {
        if (is_string($value)) {
            // SQL fonksiyonlarını tırnaksız bırak
            if (strtoupper($value) === 'CURRENT_TIMESTAMP') {
                return 'CURRENT_TIMESTAMP';
            }
            if (strtoupper($value) === 'NULL') {
                return 'NULL';
            }
            // String değerleri tırnak içinde
            return "'" . addslashes($value) . "'";
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_null($value)) {
            return 'NULL';
        }

        return (string) $value;
    }
}