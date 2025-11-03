<?php

declare(strict_types=1);

namespace Zephyr\Exceptions\Http;

use RuntimeException;

/**
 * HTTP Exception Classes
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

/**
 * Base HTTP Exception
 */
abstract class HttpException extends RuntimeException
{
    protected int $statusCode;
    protected array $headers = [];

    public function __construct(
        string $message = '',
        int $statusCode = 500,
        array $headers = [],
        \Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}