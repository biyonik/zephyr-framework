<?php

declare(strict_types=1);

namespace Zephyr\Database;

use Zephyr\Database\Query\Builder;

/**
 * Global Scope Arayüzü (Contract)
 *
 * Tüm global scope sınıflarının bu arayüzü uygulaması gerekir.
 */
interface ScopeInterface
{
    /**
     * Global scope'u bir sorgu oluşturucuya (query builder) uygular.
     *
     * @param Builder $builder
     * @param Model $model
     * @return void
     */
    public function apply(Builder $builder, Model $model): void;
}