<?php

declare(strict_types=1);

namespace Zephyr\Database\Concerns;

/**
 * Has Timestamps Trait
 *
 * Automatically manages created_at and updated_at timestamps.
 * Can be disabled by setting $timestamps = false.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
trait HasTimestamps
{
    /**
     * Enable automatic timestamps
     */
    public bool $timestamps = true;

    /**
     * Created at column name
     */
    protected string $createdAtColumn = 'created_at';

    /**
     * Updated at column name
     */
    protected string $updatedAtColumn = 'updated_at';

    /**
     * Check if model uses timestamps
     */
    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    /**
     * Get created at column name
     */
    public function getCreatedAtColumn(): string
    {
        return $this->createdAtColumn;
    }

    /**
     * Get updated at column name
     */
    public function getUpdatedAtColumn(): string
    {
        return $this->updatedAtColumn;
    }

    /**
     * Update timestamp columns
     */
    public function updateTimestamps(): self
    {
        $time = $this->freshTimestamp();

        // Set updated_at
        $this->setUpdatedAt($time);

        // Set created_at only if new record
        if (!$this->exists && !$this->isDirty($this->getCreatedAtColumn())) {
            $this->setCreatedAt($time);
        }

        return $this;
    }

    /**
     * Get fresh timestamp for model
     */
    protected function freshTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Get fresh timestamp value
     */
    public function freshTimestampString(): string
    {
        return $this->fromDateTime($this->freshTimestamp());
    }

    /**
     * Set created_at timestamp
     */
    public function setCreatedAt(mixed $value): self
    {
        $this->setAttribute($this->getCreatedAtColumn(), $value);
        return $this;
    }

    /**
     * Set updated_at timestamp
     */
    public function setUpdatedAt(mixed $value): self
    {
        $this->setAttribute($this->getUpdatedAtColumn(), $value);
        return $this;
    }

    /**
     * Get created_at timestamp
     */
    public function getCreatedAt(): mixed
    {
        return $this->getAttribute($this->getCreatedAtColumn());
    }

    /**
     * Get updated_at timestamp
     */
    public function getUpdatedAt(): mixed
    {
        return $this->getAttribute($this->getUpdatedAtColumn());
    }

    /**
     * Touch updated_at timestamp
     */
    public function touch(): bool
    {
        if (!$this->usesTimestamps()) {
            return false;
        }

        $this->updateTimestamps();

        return $this->save();
    }
}