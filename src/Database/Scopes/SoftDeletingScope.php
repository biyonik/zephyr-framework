<?php

declare(strict_types=1);

namespace Zephyr\Database\Scopes;

use Zephyr\Database\ScopeInterface;
use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;

/**
 * Soft Deleting (Çöp Kutusu) için Global Scope
 *
 * Bu scope, tüm sorgulara otomatik olarak "WHERE deleted_at IS NULL"
 * koşulunu ekler.
 */
class SoftDeletingScope implements ScopeInterface
{
    public function apply(Builder $builder, Model $model): void
    {
        // Tüm sorgulara otomatik olarak bu koşulu ekle
        $builder->whereNull($model->getDeletedAtColumn());
    }
}