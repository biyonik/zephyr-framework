<?php

declare(strict_types=1);

namespace Zephyr\Exceptions\Http;

/**
 * JSON Encoding Exception
 * 
 * Thrown when JSON encoding fails during response generation.
 * Contains detailed information about the encoding failure.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class JsonEncodingException extends HttpException
{
    /**
     * The data that failed to encode
     */
    protected mixed $data;

    /**
     * JSON error code
     */
    protected int $jsonError;

    /**
     * Constructor
     * 
     * @param mixed $data The data that failed to encode
     * @param string $message Error message
     * @param int $jsonError JSON error code
     * @param array $headers Additional headers
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        mixed $data,
        string $message = 'JSON encoding failed',
        int $jsonError = JSON_ERROR_NONE,
        array $headers = [],
        \Throwable $previous = null
    ) {
        $this->data = $data;
        $this->jsonError = $jsonError;
        
        parent::__construct($message, 500, $headers, $previous);
    }

    /**
     * Get the data that failed to encode
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Get JSON error code
     */
    public function getJsonError(): int
    {
        return $this->jsonError;
    }

    /**
     * Get human-readable JSON error message
     */
    public function getJsonErrorMessage(): string
    {
        return match ($this->jsonError) {
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded',
            JSON_ERROR_RECURSION => 'One or more recursive references in the value to be encoded',
            JSON_ERROR_INF_OR_NAN => 'One or more NAN or INF values in the value to be encoded',
            JSON_ERROR_UNSUPPORTED_TYPE => 'A value of a type that cannot be encoded was given',
            JSON_ERROR_INVALID_PROPERTY_NAME => 'A property name that cannot be encoded was given',
            JSON_ERROR_UTF16 => 'Malformed UTF-16 characters, possibly incorrectly encoded',
            default => 'Unknown JSON error',
        };
    }

    /**
     * Get detailed error context for debugging
     * 
     * @return array{
     *     error: string,
     *     error_code: int,
     *     data_type: string,
     *     suggestion: string
     * }
     */
    public function getErrorContext(): array
    {
        $dataType = gettype($this->data);
        if (is_object($this->data)) {
            $dataType = get_class($this->data);
        }

        return [
            'error' => $this->getJsonErrorMessage(),
            'error_code' => $this->jsonError,
            'data_type' => $dataType,
            'suggestion' => $this->getSuggestion(),
        ];
    }

    /**
     * Get suggestion for fixing the error
     */
    public function getSuggestion(): string
    {
        return match ($this->jsonError) {
            JSON_ERROR_DEPTH => 'Reduce nesting level of your data structure',
            JSON_ERROR_UTF8 => 'Ensure all strings are valid UTF-8. Use mb_convert_encoding() if needed',
            JSON_ERROR_RECURSION => 'Remove circular references from your data structure',
            JSON_ERROR_INF_OR_NAN => 'Replace INF and NAN values with null or valid numbers',
            JSON_ERROR_UNSUPPORTED_TYPE => 'Ensure all data types are JSON-serializable (no resources, closures, etc.)',
            default => 'Check your data structure for JSON compatibility',
        };
    }

    /**
     * Create exception from json_last_error()
     * 
     * Factory method to create exception from current JSON error state.
     * 
     * @param mixed $data The data that failed to encode
     * @return self
     */
    public static function fromLastError(mixed $data): self
    {
        $errorCode = json_last_error();
        $errorMsg = json_last_error_msg();
        
        return new self(
            data: $data,
            message: "JSON encoding failed: {$errorMsg}",
            jsonError: $errorCode
        );
    }
}