<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsOne;
use Zephyr\Support\Collection;

/**
 * Has One Relation
 *
 * One-to-one ilişkisini temsil eder (Bir kullanıcının bir profili var).
 * HasMany'yi extend eder ama ReturnsOne implement eder - tek model döndürür.
 *
 * Kullanım:
 * class User extends Model {
 *     public function profile() {
 *         return $this->hasOne(Profile::class);
 *     }
 * }
 *
 * Çağırma:
 * $user->profile; // ?Model
 * $user->profile()->where('verified', true)->first();
 *
 * Eager Loading:
 * User::with('profile')->get();
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class HasOne extends HasMany implements ReturnsOne
{
    /**
     * İlişki sonucunu döndürür
     *
     * ReturnsOne interface implementasyonu.
     * HasMany'nin getResults() metodunu override eder.
     *
     * @return Model|null Tek model veya null
     *
     * @example
     * $profile = $user->profile()->getResults();
     * // Returns: ?Profile
     */
    public function getResults(): ?Model
    {
        $results = parent::getResults();

        return $results->first() ?: null;
    }

    /**
     * Eager loading sonuçlarını üst modellerle eşleştirir
     *
     * HasMany'nin match() metodunu override eder.
     * Her üst model'e tek sonuç (veya null) atar.
     *
     * @param array<Model> $models Üst modeller
     * @param array<Model> $results İlişkili modeller
     * @param string $relation İlişki adı
     * @return array<Model> İlişkiler yüklenmiş üst modeller
     *
     * @example
     * Input:
     *   $models = [User(id=1), User(id=2), User(id=3)]
     *   $results = [Profile(user_id=1), Profile(user_id=3)]
     *
     * Output:
     *   User(id=1)->profile = Profile(user_id=1)
     *   User(id=2)->profile = null
     *   User(id=3)->profile = Profile(user_id=3)
     */
    public function match(array $models, array $results, string $relation): array
    {
        // Sonuçları foreign key'e göre dictionary oluştur
        $dictionary = $this->buildDictionary($results);

        // Her üst model'e tek sonuç ata
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                // İlk (ve tek) sonucu al
                $model->setRelation($relation, $dictionary[$key][0] ?? null);
            } else {
                $model->setRelation($relation, null);
            }
        }

        return $models;
    }

    /**
     * İlişkileri model setine başlatır
     *
     * Eager loading öncesi tüm modellere null atar.
     *
     * @param array<Model> $models
     * @param string $relation
     * @return array<Model>
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }
}