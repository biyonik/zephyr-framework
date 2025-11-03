<?php

declare(strict_types=1);

namespace Zephyr\Exceptions;

use Throwable;
use Zephyr\Http\{Request, Response};
use Zephyr\Exceptions\Http\BadRequestException;
use Zephyr\Exceptions\Http\ForbiddenException;
use Zephyr\Exceptions\Http\HttpException;
use Zephyr\Exceptions\Http\MethodNotAllowedException;
use Zephyr\Exceptions\Http\NotFoundException;
use Zephyr\Exceptions\Http\UnauthorizedException;
use Zephyr\Exceptions\Http\ValidationException;
use Zephyr\Support\Config;

/**
 * Global Exception Handler
 *
 * Catches and handles all exceptions, converting them to appropriate
 * HTTP responses. In debug mode, provides detailed error information.
 * In production, shows generic error messages to protect sensitive data.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Handler
{
    /**
     * List of exception types that should not be logged
     *
     * @var array<class-string>
     */
    protected array $dontReport = [
        NotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Handle an exception and return a response
     */
    public function handle(Throwable $e, Request $request): Response
    {
        // Log the exception (if it should be logged)
        $this->report($e);

        // Determine status code
        $statusCode = $this->getStatusCode($e);

        // Build error response
        $error = $this->buildErrorResponse($e, $statusCode);

        // Return JSON response
        return Response::error(
            message: $error['message'],
            status: $statusCode,
            errors: $error['errors'] ?? null,
            data: $error['debug'] ?? null
        );
    }

    /**
     * Report (log) an exception
     */
    protected function report(Throwable $e): void
    {
        // Don't report certain exceptions
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return;
            }
        }

        // Log to error log (simple implementation for now)
        // TODO: Implement proper logging system later
        error_log(sprintf(
            "[%s] %s in %s:%d",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
    }

    /**
     * Determine HTTP status code from exception
     */
    protected function getStatusCode(Throwable $e): int
    {
        // If it's an HTTP exception, use its status code
        if ($e instanceof HttpException) {
            return $e->getStatusCode();
        }

        // Map common exceptions to status codes
        return match (true) {
            $e instanceof NotFoundException => 404,
            $e instanceof UnauthorizedException => 401,
            $e instanceof ForbiddenException => 403,
            $e instanceof ValidationException => 422,
            $e instanceof BadRequestException => 400,
            $e instanceof MethodNotAllowedException => 405,
            default => 500,
        };
    }

    /**
     * Build error response array
     *
     * @return array{message: string, errors?: array, debug?: array}
     */
    protected function buildErrorResponse(Throwable $e, int $statusCode): array
    {
        $response = [
            'message' => $this->getErrorMessage($e, $statusCode),
        ];

        // Add validation errors if it's a ValidationException
        if ($e instanceof ValidationException) {
            $response['errors'] = $e->getErrors();
        }

        // Add debug information in debug mode
        if ($this->isDebugMode()) {
            $response['debug'] = $this->getDebugInfo($e);
        }

        return $response;
    }

    /**
     * Get appropriate error message
     */
    protected function getErrorMessage(Throwable $e, int $statusCode): string
    {
        // In debug mode, always show actual exception message
        if ($this->isDebugMode()) {
            return $e->getMessage();
        }

        // In production, show generic messages for server errors
        if ($statusCode >= 500) {
            return 'Internal server error occurred';
        }

        // For client errors, show the exception message
        // (they're usually safe to expose)
        return $e->getMessage() ?: $this->getGenericMessage($statusCode);
    }

    /**
     * Get generic error message for status code
     */
    protected function getGenericMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            405 => 'Method not allowed',
            422 => 'Validation failed',
            429 => 'Too many requests',
            500 => 'Internal server error',
            503 => 'Service unavailable',
            default => 'An error occurred',
        };
    }

    /**
     * Get debug information from exception
     *
     * @return array{
     *     exception: string,
     *     file: string,
     *     line: int,
     *     trace: array<int, string>
     * }
     */
    protected function getDebugInfo(Throwable $e): array
    {
        return [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $this->formatStackTrace($e->getTrace()),
        ];
    }

    /**
     * Format stack trace for readability
     *
     * @param array<int, array<string, mixed>> $trace
     * @return array<int, string>
     */
    protected function formatStackTrace(array $trace): array
    {
        return array_map(function ($item, $index) {
            $file = $item['file'] ?? '[internal]';
            $line = $item['line'] ?? 0;
            $function = $item['function'] ?? '';
            $class = isset($item['class']) ? $item['class'] . $item['type'] : '';

            return sprintf(
                "#%d %s(%d): %s%s()",
                $index,
                $file,
                $line,
                $class,
                $function
            );
        }, $trace, array_keys($trace));
    }

    /**
     * Check if application is in debug mode
     */
    protected function isDebugMode(): bool
    {
        return (bool) Config::get('app.debug', false);
    }

    /**
     * Render exception as HTML (fallback for non-API requests)
     *
     * This is rarely used since Zephyr is API-first, but it's here
     * for completeness and testing purposes.
     */
    public function renderHtml(Throwable $e): string
    {
        $statusCode = $this->getStatusCode($e);
        $message = htmlspecialchars($this->getErrorMessage($e, $statusCode));

        $html = sprintf(
            '<!DOCTYPE html><html lang="en"><head><title>Error %d</title></head><body>',
            $statusCode
        );
        $html .= sprintf('<h1>Error %d</h1>', $statusCode);
        $html .= sprintf('<p>%s</p>', $message);

        if ($this->isDebugMode()) {
            $html .= '<h2>Debug Information</h2>';
            $html .= '<pre>' . htmlspecialchars(print_r($this->getDebugInfo($e), true)) . '</pre>';
        }

        $html .= '</body></html>';

        return $html;
    }
}