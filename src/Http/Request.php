<?php

declare(strict_types=1);

namespace Zephyr\Http;

/**
 * HTTP Request Handler
 * 
 * Encapsulates HTTP request data including headers, query parameters,
 * body data, files, and route parameters. Provides a clean interface
 * for accessing request information.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Request
{
    /**
     * HTTP method (GET, POST, PUT, DELETE, etc.)
     */
    protected string $method;

    /**
     * Request URI path
     */
    protected string $uri;

    /**
     * Request headers
     */
    protected array $headers = [];

    /**
     * Query parameters ($_GET)
     */
    protected array $query = [];

    /**
     * Request body data
     */
    protected array $body = [];

    /**
     * Uploaded files ($_FILES)
     */
    protected array $files = [];

    /**
     * Route parameters
     */
    protected array $routeParams = [];

    /**
     * Server variables ($_SERVER)
     */
    protected array $server = [];

    /**
     * Cookies ($_COOKIE)
     */
    protected array $cookies = [];

    /**
     * Raw request body
     */
    protected ?string $rawBody = null;

    /**
     * User IP address
     */
    protected ?string $ip = null;

    /**
     * Constructor
     */
    public function __construct(
        string $method,
        string $uri,
        array $headers = [],
        array $query = [],
        array $body = [],
        array $files = [],
        array $server = [],
        array $cookies = []
    ) {
        $this->method = strtoupper($method);
        $this->uri = $this->normalizeUri($uri);
        $this->headers = $this->normalizeHeaders($headers);
        $this->query = $query;
        $this->body = $body;
        $this->files = $files;
        $this->server = $server;
        $this->cookies = $cookies;
    }

    /**
     * Capture current HTTP request from globals
     */
    public static function capture(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Parse headers from $_SERVER
        $headers = static::parseHeaders($_SERVER);
        
        // Parse body based on content type
        $body = static::parseBody($method, $headers['Content-Type'] ?? '');
        
        // Parse uploaded files
        $files = static::parseFiles($_FILES);
        
        return new self(
            $method,
            $uri,
            $headers,
            $_GET,
            $body,
            $files,
            $_SERVER,
            $_COOKIE
        );
    }

    /**
     * Parse headers from $_SERVER
     */
    protected static function parseHeaders(array $server): array
    {
        $headers = [];
        
        foreach ($server as $key => $value) {
            // Convert HTTP_* headers
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace('_', '-', substr($key, 5));
                $header = ucwords(strtolower($header), '-');
                $headers[$header] = $value;
            }
            // Special cases without HTTP_ prefix
            elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'])) {
                $header = ucwords(strtolower(str_replace('_', '-', $key)), '-');
                $headers[$header] = $value;
            }
        }
        
        return $headers;
    }

    /**
     * Parse request body
     */
    protected static function parseBody(string $method, string $contentType): array
    {
        // No body for GET, HEAD, OPTIONS
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return [];
        }
        
        // Get raw body
        $rawBody = file_get_contents('php://input');
        
        if (empty($rawBody)) {
            return $_POST;
        }
        
        // Parse based on content type
        if (str_contains($contentType, 'application/json')) {
            $data = json_decode($rawBody, true);
            return is_array($data) ? $data : [];
        }
        
        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($rawBody, $data);
            return $data;
        }
        
        // Default to $_POST for form data
        return $_POST;
    }

    /**
     * Parse uploaded files recursively
     */
    protected static function parseFiles(array $files): array
    {
        $parsed = [];
        
        foreach ($files as $key => $file) {
            // Handle multiple file uploads
            if (is_array($file['name'] ?? null)) {
                $parsed[$key] = static::parseMultipleFiles($file);
            } else {
                $parsed[$key] = $file;
            }
        }
        
        return $parsed;
    }

    /**
     * Parse multiple file uploads
     */
    protected static function parseMultipleFiles(array $files): array
    {
        $parsed = [];
        $count = count($files['name']);
        
        for ($i = 0; $i < $count; $i++) {
            $parsed[] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
        }
        
        return $parsed;
    }

    /**
     * Normalize URI by removing query string
     */
    protected function normalizeUri(string $uri): string
    {
        $uri = parse_url($uri, PHP_URL_PATH) ?? '/';
        $uri = rtrim($uri, '/') ?: '/';
        
        return $uri;
    }

    /**
     * Normalize header names
     */
    protected function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        
        foreach ($headers as $key => $value) {
            $key = ucwords(strtolower(str_replace('_', '-', $key)), '-');
            $normalized[$key] = $value;
        }
        
        return $normalized;
    }

    /**
     * Get HTTP method
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Check if method matches
     */
    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * Get request URI
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Get full URL
     */
    public function url(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->header('Host', 'localhost');
        
        return "{$scheme}://{$host}{$this->uri}";
    }

    /**
     * Get request path
     */
    public function path(): string
    {
        return $this->uri;
    }

    /**
     * Get a header value
     */
    public function header(string $key, mixed $default = null): mixed
    {
        $key = ucwords(strtolower(str_replace('_', '-', $key)), '-');
        return $this->headers[$key] ?? $default;
    }

    /**
     * Get all headers
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Check if header exists
     */
    public function hasHeader(string $key): bool
    {
        $key = ucwords(strtolower(str_replace('_', '-', $key)), '-');
        return isset($this->headers[$key]);
    }

    /**
     * Get query parameter
     */
    public function query(string $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return $this->query;
        }
        
        return $this->query[$key] ?? $default;
    }

    /**
     * Get body parameter
     */
    public function input(string $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return $this->body;
        }
        
        return $this->body[$key] ?? $default;
    }

    /**
     * Get route parameter
     */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * Set route parameters
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * Get all input (query + body merged)
     */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /**
     * Get only specified keys
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    /**
     * Get all except specified keys
     */
    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    /**
     * Check if input exists
     */
    public function has(string|array $keys): bool
    {
        $keys = is_array($keys) ? $keys : [$keys];
        $all = $this->all();
        
        foreach ($keys as $key) {
            if (!array_key_exists($key, $all)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Check if input is filled (not empty)
     */
    public function filled(string|array $keys): bool
    {
        $keys = is_array($keys) ? $keys : [$keys];
        $all = $this->all();
        
        foreach ($keys as $key) {
            if (empty($all[$key])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get uploaded file
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Check if file was uploaded
     */
    public function hasFile(string $key): bool
    {
        $file = $this->file($key);
        return $file && $file['error'] === UPLOAD_ERR_OK;
    }

    /**
     * Get cookie value
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Get server variable
     */
    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * Get client IP address
     */
    public function ip(): string
    {
        if ($this->ip !== null) {
            return $this->ip;
        }
        
        // Check for proxied IPs
        $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($keys as $key) {
            if ($ip = $this->server($key)) {
                // Handle comma-separated IPs (from proxies)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $this->ip = $ip;
                    return $this->ip;
                }
            }
        }
        
        $this->ip = $this->server('REMOTE_ADDR', '127.0.0.1');
        return $this->ip;
    }

    /**
     * Check if request is secure (HTTPS)
     */
    public function isSecure(): bool
    {
        return $this->server('HTTPS') === 'on' 
            || $this->server('HTTP_X_FORWARDED_PROTO') === 'https'
            || $this->server('HTTP_X_FORWARDED_SSL') === 'on';
    }

    /**
     * Check if request is AJAX
     */
    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Check if request accepts JSON
     */
    public function wantsJson(): bool
    {
        $accept = $this->header('Accept', '');
        return str_contains($accept, 'application/json');
    }

    /**
     * Get bearer token from Authorization header
     */
    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization', '');
        
        if (preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Get raw request body
     */
    public function getRawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = file_get_contents('php://input');
        }
        
        return $this->rawBody;
    }
}