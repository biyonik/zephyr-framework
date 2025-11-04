<?php

declare(strict_types=1);

namespace Zephyr\Http;

use Zephyr\Exceptions\Http\JsonEncodingException;

/**
 * HTTP Response Builder
 * 
 * Handles HTTP response creation with support for JSON responses,
 * status codes, headers, and standardized response formats.
 * 
 * ✅ FIXED: Multiple send() call protection
 * ✅ FIXED: Headers already sent detection
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Response
{
    /**
     * Response content
     */
    protected mixed $content;

    /**
     * HTTP status code
     */
    protected int $statusCode;

    /**
     * Response headers
     */
    protected array $headers = [];

    /**
     * Associated request (for HEAD detection)
     */
    protected ?Request $request = null;

    /**
     * Whether response has been sent
     * 
     * @var bool
     */
    protected bool $sent = false;

    /**
     * HTTP status texts
     */
    protected static array $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    /**
     * Constructor
     */
    public function __construct(mixed $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Create a JSON response
     * 
     * @throws \RuntimeException If JSON encoding fails
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json';

        $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($content === false) {
            throw JsonEncodingException::fromLastError($data);
        }

        return new self($content, $status, $headers);
    }

    /**
     * Create a success response
     */
    public static function success(mixed $data = null, string $message = null, int $status = 200): self
    {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        $response['meta'] = [
            'timestamp' => now()->format('Y-m-d\TH:i:s\Z'),
            'request_id' => uniqid('req_', true),
        ];

        return static::json($response, $status);
    }

    /**
     * Create an error response
     */
    public static function error(
        string $message,
        int $status = 400,
        ?array $details = null
    ): self {
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => static::getErrorCode($status),
            ],
        ];

        if ($details !== null && !empty($details)) {
            $response['error']['details'] = $details;
        }

        $response['meta'] = [
            'timestamp' => now()->format('Y-m-d\TH:i:s\Z'),
            'request_id' => uniqid('req_', true),
        ];

        return static::json($response, $status);
    }

    /**
     * Create a paginated response
     */
    public static function paginated(array $data, array $pagination, int $status = 200): self
    {
        $response = [
            'success' => true,
            'data' => $data,
            'meta' => [
                'pagination' => $pagination,
                'timestamp' => now()->format('Y-m-d\TH:i:s\Z'),
            ],
        ];

        if (isset($pagination['current_page']) && isset($pagination['last_page'])) {
            $response['links'] = static::generatePaginationLinks($pagination);
        }

        return static::json($response, $status);
    }

    /**
     * Generate pagination links
     */
    protected static function generatePaginationLinks(array $pagination): array
    {
        $currentPage = $pagination['current_page'];
        $lastPage = $pagination['last_page'];
        $baseUrl = $pagination['base_url'] ?? '/';

        $links = [
            'first' => "{$baseUrl}?page=1",
            'last' => "{$baseUrl}?page={$lastPage}",
            'prev' => null,
            'next' => null,
        ];

        if ($currentPage > 1) {
            $links['prev'] = "{$baseUrl}?page=" . ($currentPage - 1);
        }

        if ($currentPage < $lastPage) {
            $links['next'] = "{$baseUrl}?page=" . ($currentPage + 1);
        }

        return $links;
    }

    /**
     * Get error code from status
     */
    protected static function getErrorCode(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMIT_EXCEEDED',
            500 => 'SERVER_ERROR',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'ERROR',
        };
    }

    /**
     * Create a no content response
     */
    public static function noContent(): self
    {
        return new self('', 204);
    }

    /**
     * Create a redirect response
     */
    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    /**
     * Create a file download response
     */
    public static function download(string $file, string $name = null): self
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new \RuntimeException("File not found or not readable: {$file}");
        }

        $name ??= basename($file);
        $content = file_get_contents($file);
        $mimeType = mime_content_type($file) ?: 'application/octet-stream';

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Content-Length' => strlen($content),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        return new self($content, 200, $headers);
    }

    /**
     * Set response content
     */
    public function setContent(mixed $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Get response content
     */
    public function getContent(): mixed
    {
        return $this->content;
    }

    /**
     * Set status code
     */
    public function setStatusCode(int $code): self
    {
        if ($code < 100 || $code >= 600) {
            throw new \InvalidArgumentException("Invalid HTTP status code: {$code}");
        }

        $this->statusCode = $code;
        return $this;
    }

    /**
     * Get status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Add a header
     */
    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Add multiple headers
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Get headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Associate a request with this response
     */
    public function setRequest(Request $request): self
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Get associated request
     */
    public function getRequest(): ?Request
    {
        return $this->request;
    }

    /**
     * Check if this is a HEAD request
     */
    protected function isHeadRequest(): bool
    {
        if ($this->request) {
            return $this->request->isMethod('HEAD');
        }

        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';
    }

    /**
     * Check if response has been sent
     */
    public function isSent(): bool
    {
        return $this->sent;
    }

    /**
     * Set cookie
     */
    public function cookie(
        string $name,
        string $value,
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): self {
        $cookie = [
            'name' => $name,
            'value' => $value,
            'expire' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];

        $this->headers['Set-Cookie'][] = $this->buildCookieHeader($cookie);

        return $this;
    }

    /**
     * Build cookie header string
     */
    protected function buildCookieHeader(array $cookie): string
    {
        $header = urlencode($cookie['name']) . '=' . urlencode($cookie['value']);

        if ($cookie['expire'] > 0) {
            $header .= '; Expires=' . gmdate('D, d-M-Y H:i:s T', $cookie['expire']);
            $header .= '; Max-Age=' . ($cookie['expire'] - time());
        }

        if ($cookie['path']) {
            $header .= '; Path=' . $cookie['path'];
        }

        if ($cookie['domain']) {
            $header .= '; Domain=' . $cookie['domain'];
        }

        if ($cookie['secure']) {
            $header .= '; Secure';
        }

        if ($cookie['httponly']) {
            $header .= '; HttpOnly';
        }

        if ($cookie['samesite']) {
            $header .= '; SameSite=' . $cookie['samesite'];
        }

        return $header;
    }

    /**
     * Send the response
     * 
     * Outputs HTTP status code, headers, and content.
     * For HEAD requests, only status and headers are sent (no body).
     * 
     * ✅ FIXED: Multiple send() call protection
     * ✅ FIXED: Headers already sent detection
     * 
     * @throws \RuntimeException If response already sent or headers already sent
     */
    public function send(): void
    {
        // ✅ FIX 1: Check if response already sent
        if ($this->sent) {
            throw new \RuntimeException(
                'Response has already been sent and cannot be sent again'
            );
        }

        // ✅ FIX 2: Check if headers already sent by PHP
        if (headers_sent($file, $line)) {
            throw new \RuntimeException(
                "Headers already sent in {$file} on line {$line}. Cannot send response."
            );
        }

        // Mark as sent before actually sending (for atomic operation)
        $this->sent = true;

        try {
            // Send status code
            http_response_code($this->statusCode);

            // Send headers
            foreach ($this->headers as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        header("{$key}: {$v}", false);
                    }
                } else {
                    header("{$key}: {$value}");
                }
            }

            // Skip body for HEAD requests (HTTP spec compliance)
            if ($this->isHeadRequest()) {
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                return;
            }

            // Send content (only for non-HEAD requests)
            echo $this->content;

            // Terminate if FastCGI
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        } catch (\Throwable $e) {
            // If sending fails, mark as not sent so retry is possible
            $this->sent = false;
            throw $e;
        }
    }

    /**
     * Send response and terminate script
     * 
     * This is a convenience method that sends the response
     * and immediately terminates the script execution.
     * 
     * @return never
     */
    public function sendAndExit(): never
    {
        $this->send();
        exit(0);
    }

    /**
     * Prepare response for output (alias for send)
     * 
     * @deprecated Use send() instead
     */
    public function output(): void
    {
        $this->send();
    }

    /**
     * Get status text for a code
     */
    public static function getStatusText(int $code): string
    {
        return static::$statusTexts[$code] ?? 'Unknown';
    }

    /**
     * Get response as string (for testing)
     * 
     * Returns a string representation of the response
     * without actually sending it.
     */
    public function __toString(): string
    {
        $output = "HTTP/1.1 {$this->statusCode} " . static::getStatusText($this->statusCode) . "\r\n";

        foreach ($this->headers as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $output .= "{$key}: {$v}\r\n";
                }
            } else {
                $output .= "{$key}: {$value}\r\n";
            }
        }

        $output .= "\r\n";

        if (!$this->isHeadRequest()) {
            $output .= $this->content;
        }

        return $output;
    }
}
