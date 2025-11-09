<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsOne;

/**
 * Belongs To Relation
 *
 * One-to-many ilişkisinin tersi (Bir gönderi bir kullanıcıya aittir).
 * ReturnsOne interface'ini implement eder - tek model veya null döndürür.
 *
 * Kullanım:
 * class Post extends Model {
 *     public function user() {
 *         return $this->belongsTo(User::class);
 *     }
 * }
 *
 * Çağırma:
 * $post->user; // ?Model
 * $post->user()->where('active', true)->first();
 *
 * Eager Loading:
 * Post::with('user')->get();
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class BelongsTo extends Relation implements ReturnsOne
{
    /**
     * Bu modeldeki foreign key (örn: 'user_id')
     */
    protected string $foreignKey;

    /**
     * İlişkili modeldeki owner key (örn: 'id')
     */
    protected string $ownerKey;

    /**
     * Constructor
     *
     * @param Builder $query İlişkili model query'si
     * @param Model $parent Alt model (child)
     * @param string $foreignKey Bu modeldeki foreign key
     * @param string $ownerKey İlişkili modeldeki primary key
     */
    public function __construct(
        Builder $query,
        Model $parent,
        string $foreignKey,
        string $ownerKey
    ) {
        parent::__construct($query, $parent);

        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;

        $this->addConstraints();
    }

    /**
     * Query'ye kısıtları ekler (tek model için)
     *
     * RelationContract interface implementasyonu.
     * Lazy loading için WHERE clause ekler.
     *
     * @return void
     *
     * @example
     * $post->user() için: WHERE users.id = $post->user_id
     */
    public function addConstraints(): void
    {
        $foreignKeyValue = $this->parent->getAttribute($this->foreignKey);

        if (!is_null($foreignKeyValue)) {
            $this->query->where($this->ownerKey, '=', $foreignKeyValue);
        }
    }

    /**
     * Eager loading için kısıtları ekler (çoklu model için)
     *
     * RelationContract interface implementasyonu.
     * WHERE IN clause ile çoklu alt model için query oluşturur.
     *
     * @param array<Model> $models Alt modeller
     * @return void
     *
     * @example
     * Post::with('user') için: WHERE users.id IN (1, 2, 3, ...)
     * (1, 2, 3 post'ların user_id değerleri)
     */
    public function addEagerConstraints(array $models): void
    {
        // Tüm alt modellerden foreign key değerlerini topla
        $keys = array_map(function ($model) {
            return $model->getAttribute($this->foreignKey);
        }, $models);

        // Null ve duplicate'leri temizle
        $keys = array_unique(array_filter($keys));

        if (empty($keys)) {
            // Key yoksa hiçbir şey döndürme
            $this->query->where($this->ownerKey, '=', null);
            return;
        }

        // WHERE IN kısıtı ekle
        $this->query->whereIn($this->ownerKey, $keys);
    }

    /**
     * Eager loading sonuçlarını alt modellerle eşleştirir
     *
     * RelationContract interface implementasyonu.
     * Sonuçları owner key'e göre dictionary oluşturur ve alt modellere atar.
     *
     * @param array<Model> $models Alt modeller
     * @param array<Model> $results Üst modeller
     * @param string $relation İlişki adı
     * @return array<Model> İlişkiler yüklenmiş alt modeller
     *
     * @example
     * Input:
     *   $models = [Post(user_id=1), Post(user_id=2), Post(user_id=1)]
     *   $results = [User(id=1), User(id=2)]
     *
     * Output:
     *   Post(user_id=1)->user = User(id=1)
     *   Post(user_id=2)->user = User(id=2)
     *   Post(user_id=1)->user = User(id=1)
     */
    public function match(array $models, array $results, string $relation): array
    {
        // Sonuçları owner key'e göre dictionary oluştur
        $dictionary = $this->buildDictionary($results);

        // Her alt model'e sonucu ata
        foreach ($models as $model) {
            $foreignKeyValue = $model->getAttribute($this->foreignKey);

            if (isset($dictionary[$foreignKeyValue])) {
                $model->setRelation($relation, $dictionary[$foreignKeyValue]);
            } else {
                $model->setRelation($relation, null);
            }
        }

        return $models;
    }

    /**
     * Sonuçları owner key'e göre dictionary oluşturur
     *
     * Verimli eşleştirme için lookup dictionary.
     *
     * @param array<Model> $results Üst modeller
     * @return array<int|string, Model> Dictionary: owner_key => Model
     *
     * @example
     * Input: [User(id=1), User(id=2), User(id=3)]
     * Output: [
     *   1 => User(id=1),
     *   2 => User(id=2),
     *   3 => User(id=3)
     * ]
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $ownerKeyValue = $result->getAttribute($this->ownerKey);
            $dictionary[$ownerKeyValue] = $result;
        }

        return $dictionary;
    }

    /**
     * İlişki sonucunu döndürür
     *
     * ReturnsOne interface implementasyonu.
     *
     * @return Model|null İlişkili üst model veya null
     *
     * @example
     * $user = $post->user()->getResults();
     * // Returns: ?User
     */
    public function getResults(): ?Model
    {
        return $this->query->first();
    }

    /**
     * İlişkili model'i ilişkilendirir (associate)
     *
     * Foreign key'i ilişkili modelin primary key'ine set eder.
     * İlişki kurmadan önce kullanılır.
     *
     * @param Model $model İlişkilendirilecek model
     * @return Model Alt model (foreign key güncellenmiş)
     *
     * @example
     * $post = new Post(['title' => 'Başlık']);
     * $post->user()->associate($user);
     * // Sets: $post->user_id = $user->id
     * $post->save();
     */
    public function associate(Model $model): Model
    {
        // Foreign key'i set et
        $this->parent->setAttribute(
            $this->foreignKey,
            $model->getAttribute($this->ownerKey)
        );

        // İlişkili model'i cache'le
        $this->parent->setRelation(
            $this->getRelationName(),
            $model
        );

        return $this->parent;
    }

    /**
     * İlişkili model'i ilişkiden ayırır (dissociate)
     *
     * Foreign key'i temizler (NULL yapar).
     *
     * @return Model Alt model (foreign key temizlenmiş)
     *
     * @example
     * $post->user()->dissociate();
     * // Sets: $post->user_id = null
     * $post->save();
     */
    public function dissociate(): Model
    {
        // Foreign key'i temizle
        $this->parent->setAttribute($this->foreignKey, null);

        // Cache'i temizle
        $this->parent->setRelation($this->getRelationName(), null);

        return $this->parent;
    }

    /**
     * İlişki metot adını döndürür (cache için)
     *
     * Backtrace'den ilişki metot adını çıkarmaya çalışır.
     *
     * @return string İlişki metot adı
     */
    protected function getRelationName(): string
    {
        // Backtrace'den çıkar
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        return $backtrace[2]['function'] ?? 'relation';
    }

    /**
     * İlişkili üst model'i günceller
     *
     * @param array $attributes Güncellenecek attribute'lar
     * @return int Etkilenen satır sayısı
     *
     * @example
     * $post->user()->update(['name' => 'Yeni İsim']);
     * // Post'un ait olduğu user'ı günceller
     */
    public function update(array $attributes): int
    {
        return $this->query->update($attributes);
    }
}