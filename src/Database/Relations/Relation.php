<?php

declare(strict_types=1);

namespace Zephyr\Database\Relations;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;
use Zephyr\Database\Relations\Contracts\RelationContract;

/**
 * Base Relation Class
 *
 * Tüm ilişki tiplerinin miras aldığı temel sınıf.
 * Ortak fonksiyonalite sağlar.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
abstract class Relation implements RelationContract
{
    protected Model $parent;
    protected Builder $query;

    public function __construct(Builder $query, Model $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
    }

    abstract public function addConstraints(): void;
    abstract public function addEagerConstraints(array $models): void;
    abstract public function match(array $models, array $results, string $relation): array;

    public function eagerLoadAndMatch(array $models, string $relation): array
    {
        $this->addEagerConstraints($models);
        $results = $this->getEager();
        return $this->match($models, $results, $relation);
    }

    protected function getEager(): array
    {
        return $this->query->get()->all();
    }

    public function getQuery(): Builder
    {
        return $this->query;
    }

    public function getParent(): Model
    {
        return $this->parent;
    }

    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->query->$method(...$parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }
}