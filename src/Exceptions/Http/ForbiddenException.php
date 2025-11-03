<?php

declare(strict_types=1);

namespace Zephyr\Exceptions\Http;

/**
 * 403 Forbidden Exception
 */
class ForbiddenException extends HttpException
{
    public function __construct(
        string $message = 'Forbidden',
        array $headers = [],
        \Throwable $previous = null
    ) {
        parent::__construct($message, 403, $headers, $previous);
    }
}