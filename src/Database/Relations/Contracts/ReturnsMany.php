<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations\Contracts;

use Zephyr\Database\Model;

/**
 * Returns Many Contract
 *
 * Interface for relationships that return multiple models.
 * Examples: HasMany
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
interface ReturnsMany extends RelationContract
{
    /**
     * Get relationship results
     *
     * Returns an array of related models.
     *
     * @return array<Model> Array of related model instances
     *
     * @example
     * $user->posts()->getResults() // Returns array of Post models
     */
    public function getResults(): array;
}