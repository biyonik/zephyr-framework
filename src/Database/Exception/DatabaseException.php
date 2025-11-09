<?php

declare(strict_types=1);

namespace Zephyr\Database\Exception;

use RuntimeException;
use Throwable;

/**
 * Database Exception
 *
 * Veritabanı işlemlerinde oluşan hatalar için exception.
 * SQL sorgusu ve binding'leri debug için saklar.
 *
 * Kullanım:
 * try {
 *     DB::table('users')->where('id', '=', $id)->get();
 * } catch (DatabaseException $e) {
 *     logger()->error($e->getMessage(), $e->getContext());
 * }
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class DatabaseException extends RuntimeException
{
    /**
     * Hataya neden olan SQL sorgusu
     */
    protected ?string $sql = null;

    /**
     * SQL sorgusu için binding'ler
     */
    protected ?array $bindings = null;

    /**
     * Constructor
     *
     * @param string $message Hata mesajı
     * @param int $code Hata kodu
     * @param Throwable|null $previous Önceki exception
     * @param string|null $sql SQL sorgusu
     * @param array|null $bindings Query binding'leri
     */
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        ?string $sql = null,
        ?array $bindings = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->sql = $sql;
        $this->bindings = $bindings;
    }

    /**
     * SQL sorgusunu döndürür
     *
     * @return string|null
     */
    public function getSql(): ?string
    {
        return $this->sql;
    }

    /**
     * Query binding'lerini döndürür
     *
     * @return array|null
     */
    public function getBindings(): ?array
    {
        return $this->bindings;
    }

    /**
     * Debug için detaylı hata context'i döndürür
     *
     * @return array
     *
     * @example
     * [
     *     'message' => 'Sorgu hatası: ...',
     *     'sql' => 'SELECT * FROM users WHERE id = ?',
     *     'bindings' => [1],
     *     'file' => '/path/to/file.php',
     *     'line' => 123
     * ]
     */
    public function getContext(): array
    {
        return [
            'message' => $this->getMessage(),
            'sql' => $this->sql,
            'bindings' => $this->bindings,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}