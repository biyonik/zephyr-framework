<?php

declare(strict_types=1);

namespace Zephyr\Database\Concerns;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Scopes\SoftDeletingScope;

/**
 * Has Soft Deletes Trait
 *
 * Soft delete (yumuşak silme) işlevselliği sağlar.
 * Kayıtlar fiziksel olarak silinmez, deleted_at sütunu set edilir.
 *
 * Özellikler:
 * - delete() deleted_at'i set eder
 * - forceDelete() gerçek silme yapar
 * - restore() silinen kaydı geri yükler
 * - Global scope ile deleted_at IS NULL otomatik eklenir
 * - withTrashed() silinen kayıtları da gösterir
 * - onlyTrashed() sadece silinen kayıtları gösterir
 *
 * Migration'da:
 * $table->softDeletes(); 
 * // veya
 * $table->timestamp('deleted_at')->nullable();
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
trait HasSoftDeletes
{
    /**
     * Model soft delete kullanıyor mu?
     */
    protected bool $softDelete = true;

    /**
     * Trait boot metodu
     * 
     * Model boot edildiğinde otomatik çağrılır.
     * SoftDeletingScope global scope'unu ekler.
     */
    public static function bootHasSoftDeletes(): void
    {
        static::addGlobalScope(new SoftDeletingScope);
    }

    /**
     * deleted_at sütun adını döndürür
     */
    public function getDeletedAtColumn(): string
    {
        return defined('static::DELETED_AT') ? static::DELETED_AT : 'deleted_at';
    }

    /**
     * Model silinmiş mi kontrol eder
     */
    public function isTrashed(): bool
    {
        return !is_null($this->getAttribute($this->getDeletedAtColumn()));
    }

    /**
     * Model'i soft delete yapar
     * 
     * Fiziksel silme yerine deleted_at sütununu doldurur.
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        // deleted_at'i set et
        $this->setAttribute($this->getDeletedAtColumn(), $this->freshTimestamp());
        
        // Kaydet
        $saved = $this->save();

        if ($saved) {
            // exists flag'ini false yap
            $this->exists = false;
        }

        return $saved;
    }

    /**
     * Model'i kalıcı olarak siler (fiziksel silme)
     * 
     * Veritabanından tamamen kaldırır, geri getirilemez.
     */
    public function forceDelete(): bool
    {
        if (is_null($this->getKey())) {
            return false;
        }

        // Global scope'ları bypass et
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
     * Soft delete yapılmış model'i geri yükler
     * 
     * deleted_at sütununu NULL yapar.
     */
    public function restore(): bool
    {
        // deleted_at'i temizle
        $this->setAttribute($this->getDeletedAtColumn(), null);
        
        // exists flag'ini true yap (zaten DB'de var)
        $this->exists = true;

        // Kaydet
        return $this->save();
    }

    /**
     * SCOPE: Silinen kayıtları da gösterir
     * 
     * Global scope'u devre dışı bırakır.
     */
    public function scopeWithTrashed(Builder $query): Builder
    {
        return $query->withoutGlobalScope(SoftDeletingScope::class);
    }

    /**
     * SCOPE: Sadece silinen kayıtları gösterir
     */
    public function scopeOnlyTrashed(Builder $query): Builder
    {
        return $query
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereNotNull($this->getDeletedAtColumn());
    }

    /**
     * SCOPE: Silinen kayıtları hariç tutar
     * 
     * Zaten varsayılan davranış budur (global scope sayesinde).
     */
    public function scopeWithoutTrashed(Builder $query): Builder
    {
        return $query->whereNull($this->getDeletedAtColumn());
    }

    /**
     * Query builder için trashed kontrolü
     * 
     * Model instance yerine query üzerinden kontrol edilebilir.
     */
    public static function allTrashed(): Builder
    {
        return (new static)->onlyTrashed();
    }
}