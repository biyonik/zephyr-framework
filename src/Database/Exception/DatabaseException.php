<?php

declare(strict_types=1);

namespace Zephyr\Database\Exception;

use RuntimeException;

/**
 * Database Exception
 *
 * Thrown when database operations fail.
 * Includes SQL query and bindings for debugging.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class DatabaseException extends RuntimeException
{
    /**
     * SQL query that caused the error
     */
    protected ?string $sql = null;

    /**
     * Query bindings
     */
    protected ?array $bindings = null;

    /**
     * Constructor
     */
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $sql = null,
        ?array $bindings = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->sql = $sql;
        $this->bindings = $bindings;
    }

    /**
     * Get SQL query
     */
    public function getSql(): ?string
    {
        return $this->sql;
    }

    /**
     * Get query bindings
     */
    public function getBindings(): ?array
    {
        return $this->bindings;
    }

    /**
     * Get detailed error context for debugging
     */
    public function getContext(): array
    {
        return [
            'message' => $this->getMessage(),
            'sql' => $this->sql,
            'bindings' => $this->bindings,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}