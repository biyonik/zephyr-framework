<?php

declare(strict_types=1);

namespace Zephyr\Exceptions\Http;

/**
 * 429 Too Many Requests (Rate Limit)
 */
class RateLimitException extends HttpException
{
    public function __construct(
        string $message = 'Too Many Requests',
        array $headers = [],
        \Throwable $previous = null
    ) {
        parent::__construct($message, 429, $headers, $previous);
    }
}