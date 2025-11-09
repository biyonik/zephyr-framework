<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\ReturnsMany;
use Zephyr\Support\Collection;

/**
 * Has Many Relation
 *
 * One-to-many ilişkisini temsil eder (Bir kullanıcının birden fazla gönderisi var).
 * ReturnsMany interface'ini implement eder - her zaman Collection döndürür.
 *
 * Kullanım:
 * class User extends Model {
 *     public function posts() {
 *         return $this->hasMany(Post::class);
 *     }
 * }
 *
 * Çağırma:
 * $user->posts; // Collection<Post>
 * $user->posts()->where('published', true)->get();
 *
 * Eager Loading:
 * User::with('posts')->get();
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class HasMany extends Relation implements ReturnsMany
{
    /**
     * İlişkili modeldeki foreign key (örn: 'user_id')
     */
    protected string $foreignKey;

    /**
     * Üst modeldeki local key (örn: 'id')
     */
    protected string $localKey;

    /**
     * Constructor
     *
     * @param Builder $query İlişkili model query'si
     * @param Model $parent Üst model
     * @param string $foreignKey Foreign key (örn: 'user_id')
     * @param string $localKey Local key (örn: 'id')
     */
    public function __construct(
        Builder $query,
        Model $parent,
        string $foreignKey,
        string $localKey
    ) {
        parent::__construct($query, $parent);

        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

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
     * $user->posts() için: WHERE posts.user_id = 1
     */
    public function addConstraints(): void
    {
        $localValue = $this->parent->getAttribute($this->localKey);

        if (!is_null($localValue)) {
            $this->query->where(
                $this->foreignKey,
                '=',
                $localValue
            );
        }
    }

    /**
     * Eager loading için kısıtları ekler (çoklu model için)
     *
     * RelationContract interface implementasyonu.
     * WHERE IN clause ile çoklu üst model için query oluşturur.
     *
     * @param array<Model> $models Üst modeller
     * @return void
     *
     * @example
     * User::with('posts') için: WHERE posts.user_id IN (1, 2, 3, ...)
     */
    public function addEagerConstraints(array $models): void
    {
        // Tüm üst modellerden local key değerlerini topla
        $keys = array_map(function ($model) {
            return $model->getAttribute($this->localKey);
        }, $models);

        // Null ve duplicate'leri temizle
        $keys = array_unique(array_filter($keys));

        if (empty($keys)) {
            // Key yoksa hiçbir şey döndürme
            $this->query->where($this->foreignKey, '=', null);
            return;
        }

        // WHERE IN kısıtı ekle
        $this->query->whereIn($this->foreignKey, $keys);
    }

    /**
     * Eager loading sonuçlarını üst modellerle eşleştirir
     *
     * RelationContract interface implementasyonu.
     * Sonuçları foreign key'e göre gruplar ve her üst model'e atar.
     *
     * @param array<Model> $models Üst modeller
     * @param array<Model> $results İlişkili modeller
     * @param string $relation İlişki adı
     * @return array<Model> İlişkiler yüklenmiş üst modeller
     *
     * @example
     * Input:
     *   $models = [User(id=1), User(id=2)]
     *   $results = [Post(user_id=1), Post(user_id=1), Post(user_id=2)]
     *
     * Output:
     *   User(id=1)->posts = Collection[Post, Post]
     *   User(id=2)->posts = Collection[Post]
     */
    public function match(array $models, array $results, string $relation): array
    {
        // Sonuçları foreign key'e göre grupla
        $dictionary = $this->buildDictionary($results);

        // Her üst model'e sonuçları ata
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                // Collection döndür
                $collection = $this->query->getModel()?->newCollection($dictionary[$key]);
                $model->setRelation($relation, $collection);
            } else {
                // Boş Collection döndür
                $model->setRelation($relation, $this->query->getModel()?->newCollection([]));
            }
        }

        return $models;
    }

    /**
     * Sonuçları foreign key'e göre dictionary oluşturur
     *
     * Verimli eşleştirme için lookup dictionary.
     *
     * @param array<Model> $results İlişkili modeller
     * @return array<int|string, array<Model>> Dictionary: foreign_key => [models]
     *
     * @example
     * Input: [Post(user_id=1), Post(user_id=1), Post(user_id=2)]
     * Output: [
     *   1 => [Post, Post],
     *   2 => [Post]
     * ]
     */
    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $foreignKeyValue = $result->getAttribute($this->foreignKey);

            if (!isset($dictionary[$foreignKeyValue])) {
                $dictionary[$foreignKeyValue] = [];
            }

            $dictionary[$foreignKeyValue][] = $result;
        }

        return $dictionary;
    }

    /**
     * İlişki sonuçlarını döndürür
     *
     * ReturnsMany interface implementasyonu.
     *
     * @return Collection İlişkili modeller
     *
     * @example
     * $posts = $user->posts()->getResults();
     * // Returns: Collection<Post>
     */
    public function getResults(): Collection
    {
        return $this->query->get();
    }

    /**
     * Yeni ilişkili model oluşturur ve kaydeder
     *
     * Foreign key otomatik set edilir.
     *
     * @param array $attributes Model attribute'ları
     * @return Model Oluşturulan model
     *
     * @example
     * $post = $user->posts()->create([
     *     'title' => 'Yeni Gönderi',
     *     'content' => 'İçerik...'
     * ]);
     * // Otomatik: $post->user_id = $user->id
     */
    public function create(array $attributes): Model
    {
        // Foreign key'i ekle
        $attributes[$this->foreignKey] = $this->parent->getAttribute($this->localKey);

        return $this->query->create($attributes);
    }

    /**
     * İlişkili model'i kaydeder
     *
     * Foreign key set edilir ve model kaydedilir.
     *
     * @param Model $model Kaydedilecek model
     * @return bool Başarı durumu
     *
     * @example
     * $post = new Post(['title' => 'Başlık']);
     * $user->posts()->save($post);
     * // Sets: $post->user_id = $user->id ve kaydeder
     */
    public function save(Model $model): bool
    {
        // Foreign key'i set et
        $model->setAttribute(
            $this->foreignKey,
            $this->parent->getAttribute($this->localKey)
        );

        return $model->save();
    }

    /**
     * Çoklu ilişkili model'i kaydeder
     *
     * @param array<Model> $models Kaydedilecek modeller
     * @return bool Başarı durumu
     *
     * @example
     * $user->posts()->saveMany([
     *     new Post(['title' => 'Post 1']),
     *     new Post(['title' => 'Post 2'])
     * ]);
     */
    public function saveMany(array $models): bool
    {
        foreach ($models as $model) {
            $this->save($model);
        }

        return true;
    }
}