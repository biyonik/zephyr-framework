<?php

declare(strict_types=1);

namespace Zephyr\Exceptions;

use Throwable;
use Zephyr\Http\{Request, Response};
use Zephyr\Exceptions\Http\BadRequestException;
use Zephyr\Exceptions\Http\ForbiddenException;
use Zephyr\Exceptions\Http\HttpException;
use Zephyr\Exceptions\Http\JsonEncodingException;
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
 * ✅ FIXED: JSON encoding error handling
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

        // ✅ FIX: Handle JSON encoding errors specially
        if ($e instanceof JsonEncodingException) {
            return $this->handleJsonEncodingException($e, $request);
        }

        // Determine status code
        $statusCode = $this->getStatusCode($e);

        // Build error response
        $error = $this->buildErrorResponse($e, $statusCode);

        // Create standardized error response
        try {
            $response = Response::error(
                message: $error['message'],
                status: $statusCode,
                details: $error['details'] ?? null
            );
        } catch (JsonEncodingException $jsonError) {
            // ✅ If error response itself fails to encode, return plain text
            return $this->handleNestedJsonError($jsonError);
        }

        // Associate request with response
        $response->setRequest($request);

        return $response;
    }

    /**
     * Handle JSON encoding exceptions
     * 
     * When a response fails to JSON encode, we need to return
     * a safe response that won't have encoding issues.
     * 
     * @param JsonEncodingException $e The encoding exception
     * @param Request $request The request
     * @return Response Safe error response
     */
    protected function handleJsonEncodingException(JsonEncodingException $e, Request $request): Response
    {
        // In debug mode, provide detailed context
        if ($this->isDebugMode()) {
            $errorData = [
                'success' => false,
                'error' => [
                    'message' => 'JSON encoding failed',
                    'code' => 'JSON_ENCODING_ERROR',
                    'details' => $e->getErrorContext(),
                ],
                'meta' => [
                    'timestamp' => now()->format('Y-m-d\TH:i:s\Z'),
                    'request_id' => uniqid('req_', true),
                ],
            ];
        } else {
            // In production, be generic
            $errorData = [
                'success' => false,
                'error' => [
                    'message' => 'Unable to generate response',
                    'code' => 'JSON_ENCODING_ERROR',
                ],
                'meta' => [
                    'timestamp' => now()->format('Y-m-d\TH:i:s\Z'),
                    'request_id' => uniqid('req_', true),
                ],
            ];
        }

        // Try to encode error data (should work as it's simple)
        try {
            $content = json_encode($errorData, JSON_UNESCAPED_UNICODE);
            
            if ($content === false) {
                // Even simple error failed, fallback to plain text
                return $this->handleNestedJsonError($e);
            }
            
            $response = new Response($content, 500, ['Content-Type' => 'application/json']);
            $response->setRequest($request);
            
            return $response;
            
        } catch (\Throwable $fallbackError) {
            // Last resort: plain text
            return $this->handleNestedJsonError($e);
        }
    }

    /**
     * Handle nested JSON encoding failures
     * 
     * When even error responses fail to encode, fall back to plain text.
     * This is the absolute last resort.
     * 
     * @param JsonEncodingException $e The encoding exception
     * @return Response Plain text error response
     */
    protected function handleNestedJsonError(JsonEncodingException $e): Response
    {
        $message = $this->isDebugMode()
            ? "JSON Encoding Error: {$e->getJsonErrorMessage()}\n\nSuggestion: {$e->getSuggestion()}"
            : "An error occurred while generating the response.";

        return new Response(
            content: $message,
            statusCode: 500,
            headers: ['Content-Type' => 'text/plain']
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
            $e instanceof JsonEncodingException => 500,
            default => 500,
        };
    }

    /**
     * Build error response array
     */
    protected function buildErrorResponse(Throwable $e, int $statusCode): array
    {
        $response = [
            'message' => $this->getErrorMessage($e, $statusCode),
        ];

        // Collect all details in one array
        $details = [];

        // Add validation errors if present
        if ($e instanceof ValidationException) {
            $details = $e->getErrors();
        }

        // Add JSON encoding context if present
        if ($e instanceof JsonEncodingException && $this->isDebugMode()) {
            $details = array_merge($details, $e->getErrorContext());
        }

        // Add debug information in debug mode
        if ($this->isDebugMode()) {
            $debugInfo = $this->getDebugInfo($e);
            $details = array_merge($debugInfo, $details);
        }

        // Only add details if not empty
        if (!empty($details)) {
            $response['details'] = $details;
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

        // For JSON encoding errors, be specific
        if ($e instanceof JsonEncodingException) {
            return 'Unable to generate response';
        }

        // In production, show generic messages for server errors
        if ($statusCode >= 500) {
            return 'Internal server error occurred';
        }

        // For client errors, show the exception message
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