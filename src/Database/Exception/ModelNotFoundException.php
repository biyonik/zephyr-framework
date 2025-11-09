<?php

declare(strict_types=1);

namespace Zephyr\Database\Exception;

use RuntimeException;

/**
 * Model Not Found Exception
 *
 * Primary key ile model bulunamadığında fırlatılır.
 * Genellikle findOrFail() metodu kullanıldığında fırlar.
 *
 * Kullanım:
 * try {
 *     $user = User::findOrFail($id);
 * } catch (ModelNotFoundException $e) {
 *     return response()->json(['error' => 'Kullanıcı bulunamadı'], 404);
 * }
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class ModelNotFoundException extends RuntimeException
{
    /**
     * Model sınıf adı
     */
    protected string $model = '';

    /**
     * Aranan primary key değerleri
     */
    protected array $ids = [];

    /**
     * Model sınıfını set eder
     *
     * @param string $model Model sınıf adı
     * @return self
     */
    public function setModel(string $model): self
    {
        $this->model = $model;
        $this->message = "Sorgu sonuç bulamadı: [{$model}]";
        return $this;
    }

    /**
     * Primary key değerlerini set eder
     *
     * @param array $ids Primary key değerleri
     * @return self
     */
    public function setIds(array $ids): self
    {
        $this->ids = $ids;
        $this->message = "Sorgu sonuç bulamadı: [{$this->model}] - ID: [" . implode(', ', $ids) . "]";
        return $this;
    }

    /**
     * Model sınıf adını döndürür
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Primary key değerlerini döndürür
     *
     * @return array
     */
    public function getIds(): array
    {
        return $this->ids;
    }
}