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

/**
 * 422 Unprocessable Entity (Validation Error)
 */
class ValidationException extends HttpException
{
    protected array $errors = [];

    public function __construct(
        array $errors,
        string $message = 'Validation failed',
        array $headers = [],
        \Throwable $previous = null
    ) {
        $this->errors = $errors;
        parent::__construct($message, 422, $headers, $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

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