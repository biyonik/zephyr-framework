<?php

declare(strict_types=1);

namespace Zephyr\Database\Schema;

class MySqlGrammar extends Grammar
{
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

    public function compileDrop(Blueprint $blueprint): string
    {
        return "DROP TABLE `{$blueprint->getTable()}`";
    }

    public function compileDropIfExists(Blueprint $blueprint): string
    {
        return "DROP TABLE IF EXISTS `{$blueprint->getTable()}`";
    }

    protected function getTypeString(array $column): string
    {
        return match ($column['type']) {
            'string' => "VARCHAR({$column['length']})",
            'integer' => 'INT',
            'bigInteger' => 'BIGINT' . ($column['unsigned'] ?? false ? ' UNSIGNED' : ''),
            'text' => 'TEXT',
            'timestamp' => 'TIMESTAMP',
            default => 'VARCHAR(255)',
        };
    }
}