<?php 

declare(strict_types=1);

namespace Zephyr\Exceptions\Http;

/**
 * 405 Method Not Allowed Exception
 */
class MethodNotAllowedException extends HttpException
{
    public function __construct(
        string $message = 'Method Not Allowed',
        array $headers = [],
        \Throwable $previous = null
    ) {
        parent::__construct($message, 405, $headers, $previous);
    }
}