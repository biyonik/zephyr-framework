<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\RelationContract;
use Zephyr\Support\Collection;

/**
 * Base Relation Class
 *
 * Tüm ilişki tiplerinin miras aldığı temel sınıf.
 * Ortak fonksiyonalite sağlar:
 * - Query builder yönetimi
 * - Eager loading koordinasyonu
 * - Metot forwarding
 *
 * Alt sınıflar ReturnsMany veya ReturnsOne implement ederek
 * getResults() metodunun return type'ını belirler.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
abstract class Relation implements RelationContract
{
    /**
     * Üst model instance
     */
    protected Model $parent;

    /**
     * İlişkili model query builder
     */
    protected Builder $query;

    /**
     * Constructor
     *
     * @param Builder $query İlişkili model query'si
     * @param Model $parent Üst model
     */
    public function __construct(Builder $query, Model $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
    }

    /**
     * Query'ye kısıtları ekler
     *
     * Alt sınıflar tarafından implement edilir (HasMany, BelongsTo, vb.)
     *
     * @return void
     */
    abstract public function addConstraints(): void;

    /**
     * Eager loading için kısıtları ekler
     *
     * Alt sınıflar tarafından implement edilir.
     *
     * @param array<Model> $models Üst modeller
     * @return void
     */
    abstract public function addEagerConstraints(array $models): void;

    /**
     * Eager loading sonuçlarını üst modellerle eşleştirir
     *
     * Alt sınıflar tarafından implement edilir.
     *
     * @param array<Model> $models Üst modeller
     * @param array<Model> $results Eager loading sonuçları
     * @param string $relation İlişki adı
     * @return array<Model> İlişkiler yüklenmiş modeller
     */
    abstract public function match(array $models, array $results, string $relation): array;

    /**
     * Eager loading ve eşleştirme koordinasyonu
     *
     * Eager loading sırasında çağrılan ana metot.
     * Süreç:
     * 1. Eager loading kısıtları ekle
     * 2. Sonuçları getir
     * 3. Sonuçları üst modellerle eşleştir
     *
     * @param array<Model> $models Üst modeller
     * @param string $relation İlişki adı
     * @return array<Model> İlişkiler yüklenmiş modeller
     */
    public function eagerLoadAndMatch(array $models, string $relation): array
    {
        // Eager loading kısıtlarını ekle
        $this->addEagerConstraints($models);

        // Sonuçları getir
        $results = $this->getEager();

        // Sonuçları üst modellerle eşleştir
        return $this->match($models, $results, $relation);
    }

    /**
     * Eager loading sonuçlarını getirir
     *
     * Query'yi çalıştırır ve tüm sonuçları array olarak döndürür.
     *
     * @return Collection İlişkili modeller
     */
    protected function getEager(): Collection
    {
        return $this->query->get();
    }

    /**
     * Query builder'ı döndürür
     *
     * @return Builder
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Üst model'i döndürür
     *
     * @return Model
     */
    public function getParent(): Model
    {
        return $this->parent;
    }

    /**
     * Metot çağrılarını query builder'a forward eder
     *
     * Relation üzerinde query builder metotları çağrılabilir:
     * $user->posts()->where('published', true)->get()
     *
     * @param string $method Metot adı
     * @param array $parameters Parametreler
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->query->$method(...$parameters);

        // Chainable metotlar için self döndür
        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }
}