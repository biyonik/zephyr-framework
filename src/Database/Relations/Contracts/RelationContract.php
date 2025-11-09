<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations\Contracts;

use Zephyr\Database\Model;

/**
 * Relation Contract
 *
 * Tüm ilişki tiplerinin implement etmesi gereken temel arayüz.
 * Ortak davranışları tanımlar.
 *
 * Bu interface'i implement eden sınıflar:
 * - HasMany (one-to-many)
 * - BelongsTo (inverse one-to-many)
 * - HasOne (one-to-one)
 * - BelongsToMany (many-to-many)
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
interface RelationContract
{
    /**
     * İlişkiye göre query'ye kısıtları ekler
     *
     * Lazy loading için kullanılır.
     * Tek bir model için WHERE clause ekler.
     *
     * @return void
     *
     * @example
     * // HasMany için:
     * $user->posts() -> WHERE posts.user_id = 1
     *
     * // BelongsTo için:
     * $post->user() -> WHERE users.id = $post->user_id
     */
    public function addConstraints(): void;

    /**
     * Eager loading için query'ye kısıtları ekler
     *
     * Çoklu model için WHERE IN clause ekler.
     *
     * @param array<Model> $models Üst modeller
     * @return void
     *
     * @example
     * // HasMany için:
     * User::with('posts') -> WHERE posts.user_id IN (1, 2, 3, ...)
     *
     * // BelongsTo için:
     * Post::with('user') -> WHERE users.id IN (1, 2, 3, ...)
     */
    public function addEagerConstraints(array $models): void;

    /**
     * Eager loading sonuçlarını üst modellerle eşleştirir
     *
     * Dictionary oluşturarak verimli eşleştirme yapar.
     *
     * @param array<Model> $models Üst modeller
     * @param array<Model> $results Eager loading sonuçları
     * @param string $relation İlişki adı
     * @return array<Model> İlişkiler yüklenmiş modeller
     *
     * @example
     * Input:
     *   $models = [User(id=1), User(id=2)]
     *   $results = [Post(user_id=1), Post(user_id=2)]
     *   $relation = 'posts'
     *
     * Output:
     *   User(id=1)->posts = [Post(user_id=1)]
     *   User(id=2)->posts = [Post(user_id=2)]
     */
    public function match(array $models, array $results, string $relation): array;
}