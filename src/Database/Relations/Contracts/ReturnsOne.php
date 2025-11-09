<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations\Contracts;

use Zephyr\Database\Model;

/**
 * Returns One Contract
 *
 * Tek model veya null döndüren ilişkiler için arayüz.
 * ?Model döndürmeyi garanti eder.
 *
 * Bu interface'i implement eden sınıflar:
 * - HasOne (one-to-one)
 * - BelongsTo (inverse one-to-many)
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
interface ReturnsOne extends RelationContract
{
    /**
     * İlişki sonucunu döndürür
     *
     * Tek model veya null döndürür.
     *
     * @return Model|null Model instance veya null
     *
     * @example
     * $profile = $user->profile()->getResults();
     * // Returns: ?Profile
     *
     * $user = $post->user()->getResults();
     * // Returns: ?User
     */
    public function getResults(): ?Model;
}