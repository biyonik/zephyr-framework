<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations\Contracts;

use Zephyr\Database\Model;

/**
 * Returns One Contract
 *
 * Interface for relationships that return a single model or null.
 * Examples: HasOne, BelongsTo
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
interface ReturnsOne extends RelationContract
{
    /**
     * Get relationship results
     *
     * Returns a single related model or null if not found.
     *
     * @return Model|null Related model instance or null
     *
     * @example
     * $user->profile()->getResults() // Returns Profile model or null
     * $post->user()->getResults() // Returns User model or null
     */
    public function getResults(): ?Model;
}