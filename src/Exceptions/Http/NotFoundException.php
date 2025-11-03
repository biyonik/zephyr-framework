<?php 

declare(strict_types=1);

namespace Zephyr\Exceptions\Http;

/**
 * 404 Not Found Exception
 */

class NotFoundException extends HttpException
{
    public function __construct(
        string $message = 'Not Found',
        array $headers = [],
        \Throwable $previous = null
    ) {
        parent::__construct($message, 404, $headers, $previous);
    }
}