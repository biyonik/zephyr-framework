<?php

declare(strict_types=1);

namespace Zephyr\Database\Concerns;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Scopes\SoftDeletingScope;

/**
 * ✅ FIX: Has Soft Deletes Trait
 *
 * Artık GLOBAL SCOPE uygular!
 * Tüm sorgulara otomatik olarak "WHERE deleted_at IS NULL" ekler.
 */
trait HasSoftDeletes
{
    /**
     * Modelin "soft delete" kullandığını belirtir.
     */
    protected bool $softDelete = true;

    /**
     * ✅ FIX: Boot trait - Global scope ekler.
     *
     * Bu metod Model::bootIfNotBooted() tarafından otomatik çağrılır.
     */
    public static function bootHasSoftDeletes(): void
    {
        static::addGlobalScope(new SoftDeletingScope);
    }

    /**
     * 'deleted_at' sütununun adını alır.
     */
    public function getDeletedAtColumn(): string
    {
        return defined('static::DELETED_AT') ? static::DELETED_AT : 'deleted_at';
    }

    /**
     * Modelin silinip silinmediğini kontrol eder.
     */
    public function isTrashed(): bool
    {
        return !is_null($this->getAttribute($this->getDeletedAtColumn()));
    }

    /**
     * Modeli "soft delete" yapar.
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $this->setAttribute($this->getDeletedAtColumn(), $this->freshTimestamp());
        $saved = $this->save();

        if ($saved) {
            $this->exists = false;
        }

        return $saved;
    }

    /**
     * Modeli veritabanından kalıcı olarak siler.
     */
    public function forceDelete(): bool
    {
        if (is_null($this->getKey())) {
            return false;
        }

        // Global scope'suz query oluştur
        $query = $this->newQueryWithoutScopes();

        $deleted = $query
            ->where($this->getKeyName(), '=', $this->getKey())
            ->delete();

        if ($deleted > 0) {
            $this->exists = false;
            return true;
        }

        return false;
    }

    /**
     * "Soft delete" yapılmış bir modeli geri yükler.
     */
    public function restore(): bool
    {
        $this->setAttribute($this->getDeletedAtColumn(), null);
        $this->exists = true;

        return $this->save();
    }

    /**
     * SCOPE: Çöp kutusu dahil (override global scope).
     */
    public function scopeWithTrashed(Builder $query): Builder
    {
        return $query->withoutGlobalScope(SoftDeletingScope::class);
    }

    /**
     * SCOPE: Sadece çöp kutusu.
     */
    public function scopeOnlyTrashed(Builder $query): Builder
    {
        return $query->withoutGlobalScope(SoftDeletingScope::class)
            ->whereNotNull($this->getDeletedAtColumn());
    }

    /**
     * SCOPE: Çöp kutusu hariç (zaten default, ama açıkça çağrılabilir).
     */
    public function scopeWithoutTrashed(Builder $query): Builder
    {
        return $query->whereNull($this->getDeletedAtColumn());
    }

    /**
     * ✅ Global scope olmadan yeni query.
     */
    protected function newQueryWithoutScopes(): Builder
    {
        return (new Builder($this->getConnection()))
            ->setModel($this)
            ->from($this->getTable());
    }
}