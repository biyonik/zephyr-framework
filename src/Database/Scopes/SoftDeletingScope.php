<?php

declare(strict_types=1);

namespace Zephyr\Database\Scopes;

use Zephyr\Database\ScopeInterface;
use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;

/**
 * Soft Deleting Global Scope
 *
 * Soft delete (yumuşak silme) için global scope implementasyonu.
 * Tüm sorgulara otomatik olarak "WHERE deleted_at IS NULL" ekler.
 *
 * Bu scope HasSoftDeletes trait tarafından otomatik eklenir:
 * - Model::all() -> WHERE deleted_at IS NULL
 * - Model::find(1) -> WHERE id = 1 AND deleted_at IS NULL
 *
 * Scope'u devre dışı bırakmak için:
 * - Model::withTrashed()->get() -> Silinen kayıtları da göster
 * - Model::onlyTrashed()->get() -> Sadece silinen kayıtları göster
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class SoftDeletingScope implements ScopeInterface
{
    /**
     * Global scope'u query builder'a uygular
     *
     * deleted_at IS NULL koşulunu ekler.
     *
     * @param Builder $builder Query builder
     * @param Model $model Model instance
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        // deleted_at sütunu NULL olan kayıtları getir
        $builder->whereNull($model->getDeletedAtColumn());
    }
}