<?php

declare(strict_types=1);

namespace Zephyr\Database\Schema;

/**
 * Temel Şema Dilbilgisi.
 * Farklı veritabanı sürücüleri (MySQL, SQLite) için genişletilir.
 */
abstract class Grammar
{
    /**
     * Bir 'create' şemasını SQL'e çevirir.
     */
    abstract public function compileCreate(Blueprint $blueprint): string;

    /**
     * Bir 'drop' şemasını SQL'e çevirir.
     */
    abstract public function compileDrop(Blueprint $blueprint): string;

    /**
     * Bir 'drop if exists' şemasını SQL'e çevirir.
     */
    abstract public function compileDropIfExists(Blueprint $blueprint): string;

    /**
     * Sütun tanımını SQL'e çevirir.
     */
    protected function compileColumn(array $column): string
    {
        $sql = "`{$column['name']}` " . $this->getTypeString($column);

        // Değiştiriciler (Modifiers)
        $sql .= ($column['nullable'] === false) ? ' NOT NULL' : ' NULL';

        if (isset($column['autoIncrement']) && $column['autoIncrement']) {
            $sql .= ' AUTO_INCREMENT';
        }

        if (isset($column['primary']) && $column['primary']) {
            $sql .= ' PRIMARY KEY';
        }

        if (isset($column['unique']) && $column['unique']) {
            $sql .= ' UNIQUE';
        }

        if (isset($column['default'])) {
            $sql .= " DEFAULT " . $this->formatDefault($column['default']);
        }

        return $sql;
    }

    /**
     * Sütun tipini (örn: 'string') SQL tipine (örn: 'VARCHAR') çevirir.
     */
    abstract protected function getTypeString(array $column): string;

    /**
     * Varsayılan değeri SQL için formatlar.
     */
    protected function formatDefault($value): string
    {
        if (is_string($value)) {
            // 'CURRENT_TIMESTAMP' gibi SQL fonksiyonlarını tırnaksız bırak
            if (strtoupper($value) === 'CURRENT_TIMESTAMP') {
                return 'CURRENT_TIMESTAMP';
            }
            return "'{$value}'";
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return (string) $value;
    }
}