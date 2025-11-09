<?php

namespace Zephyr\Database\Schema;

class ColumnDefinition
{
    public function __construct(private array &$column)
    {
    }

    public function nullable(): self
    {
        $this->column['nullable'] = true;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->column['default'] = $value;
        return $this;
    }

    public function unique(): self
    {
        $this->column['unique'] = true;
        return $this;
    }

    public function unsigned(): self
    {
        $this->column['unsigned'] = true;
        return $this;
    }

    public function autoIncrement(): self
    {
        $this->column['autoIncrement'] = true;
        return $this;
    }

    public function primary(): self
    {
        $this->column['primary'] = true;
        return $this;
    }
}