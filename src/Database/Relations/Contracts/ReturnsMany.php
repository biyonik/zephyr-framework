<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations\Contracts;

use Zephyr\Support\Collection;

/**
 * Returns Many Contract
 *
 * Çoklu model döndüren ilişkiler için arayüz.
 * Collection döndürmeyi garanti eder.
 *
 * Bu interface'i implement eden sınıflar:
 * - HasMany (one-to-many)
 * - BelongsToMany (many-to-many)
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
interface ReturnsMany extends RelationContract
{
    /**
     * İlişki sonuçlarını döndürür
     *
     * Her zaman Collection döndürür (boş olsa bile).
     *
     * @return Collection Model collection'ı
     *
     * @example
     * $posts = $user->posts()->getResults();
     * // Returns: Collection<Post> (boş veya dolu)
     */
    public function getResults(): Collection;
}