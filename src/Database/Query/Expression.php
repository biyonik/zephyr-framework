<?php

declare(strict_types=1);

namespace Zephyr\Database\Query;

/**
 * Raw SQL Expression
 *
 * Represents a raw SQL expression that should not be escaped.
 * Used for functions, subqueries, and complex expressions.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Expression
{
    /**
     * Constructor
     */
    public function __construct(
        protected string $value
    ) {}

    /**
     * Get expression value
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Create expression instance
     */
    public static function make(string $value): self
    {
        return new self($value);
    }
}