<?php

declare(strict_types=1);

namespace Zephyr\Exceptions\Http;

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