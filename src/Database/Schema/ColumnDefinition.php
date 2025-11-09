<?php

declare(strict_types=1);

namespace Zephyr\Database\Schema;

/**
 * Column Definition
 *
 * Fluent interface ile sütun özelliklerini tanımlar.
 * Blueprint tarafından döndürülür.
 *
 * Kullanım:
 * $table->string('email')->nullable()->unique();
 * $table->integer('user_id')->unsigned()->index();
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class ColumnDefinition
{
    /**
     * Constructor
     *
     * Sütun tanımına referans tutar (pass by reference).
     *
     * @param array $column Sütun tanımı (referans)
     */
    public function __construct(
        private array &$column
    ) {}

    /**
     * Sütunu nullable (NULL kabul eden) yapar
     *
     * @return self
     */
    public function nullable(): self
    {
        $this->column['nullable'] = true;
        return $this;
    }

    /**
     * Varsayılan değer belirler
     *
     * @param mixed $value Varsayılan değer
     * @return self
     */
    public function default(mixed $value): self
    {
        $this->column['default'] = $value;
        return $this;
    }

    /**
     * Sütunu unique (benzersiz) yapar
     *
     * @return self
     */
    public function unique(): self
    {
        $this->column['unique'] = true;
        return $this;
    }

    /**
     * Sütunu unsigned (işaretsiz) yapar
     *
     * Sadece numeric tipler için (integer, bigInteger).
     *
     * @return self
     */
    public function unsigned(): self
    {
        $this->column['unsigned'] = true;
        return $this;
    }

    /**
     * Sütunu auto increment yapar
     *
     * Sadece integer primary key için.
     *
     * @return self
     */
    public function autoIncrement(): self
    {
        $this->column['autoIncrement'] = true;
        return $this;
    }

    /**
     * Sütunu primary key yapar
     *
     * @return self
     */
    public function primary(): self
    {
        $this->column['primary'] = true;
        return $this;
    }

    /**
     * İndeks ekler
     *
     * @return self
     */
    public function index(): self
    {
        $this->column['index'] = true;
        return $this;
    }

    /**
     * Yorum ekler
     *
     * @param string $comment Sütun yorumu
     * @return self
     */
    public function comment(string $comment): self
    {
        $this->column['comment'] = $comment;
        return $this;
    }

    /**
     * Sütunu AFTER belirtilen sütunun sonrasına yerleştirir
     *
     * @param string $column Referans sütun
     * @return self
     */
    public function after(string $column): self
    {
        $this->column['after'] = $column;
        return $this;
    }

    /**
     * Sütunu tablonun en başına yerleştirir
     *
     * @return self
     */
    public function first(): self
    {
        $this->column['first'] = true;
        return $this;
    }
}