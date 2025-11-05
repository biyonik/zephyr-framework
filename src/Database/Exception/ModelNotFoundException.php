<?php

declare(strict_types=1);

namespace Zephyr\Database\Exception;

use RuntimeException;

/**
 * Model Not Found Exception
 *
 * Thrown when a model cannot be found by primary key.
 * Typically used with findOrFail() methods.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class ModelNotFoundException extends RuntimeException
{
    /**
     * Model class name
     */
    protected string $model;

    /**
     * Primary key values
     */
    protected array $ids = [];

    /**
     * Set model class
     */
    public function setModel(string $model): self
    {
        $this->model = $model;
        $this->message = "No query results for model [{$model}].";
        return $this;
    }

    /**
     * Set IDs
     */
    public function setIds(array $ids): self
    {
        $this->ids = $ids;
        $this->message = "No query results for model [{$this->model}] with IDs [" . implode(', ', $ids) . "].";
        return $this;
    }

    /**
     * Get model class
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get IDs
     */
    public function getIds(): array
    {
        return $this->ids;
    }
}