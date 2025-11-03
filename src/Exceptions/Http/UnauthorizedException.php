<?php

declare(strict_types=1);

namespace Zephyr\Exceptions\Http;

/**
 * 401 Unauthorized Exception
 */
class UnauthorizedException extends HttpException
{
    public function __construct(
        string $message = 'Unauthorized',
        array $headers = [],
        \Throwable $previous = null
    ) {
        parent::__construct($message, 401, $headers, $previous);
    }
}