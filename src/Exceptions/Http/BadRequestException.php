<?php

declare(strict_types=1);

namespace Zephyr\Exceptions\Http;

/**
 * 400 Bad Request Exception
 */
class BadRequestException extends HttpException
{
    public function __construct(
        string $message = 'Bad Request',
        array $headers = [],
        \Throwable $previous = null
    ) {
        parent::__construct($message, 400, $headers, $previous);
    }
}