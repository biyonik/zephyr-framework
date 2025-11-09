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
 * - delete() metodu deleted_at'i set eder
 * - forceDelete() metodu gerçek silme yapar
 * - restore() metodu silinen kaydı geri yükler
 * - Global scope ile deleted_at IS NULL otomatik eklenir
 * - withTrashed() scope'u ile silinen kayıtları da görebilirsiniz
 *
 * Migration'da:
 * $table->softDeletes(); veya $table->timestamp('deleted_at')->nullable();
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
     *
     * @return void
     */
    public static function bootHasSoftDeletes(): void
    {
        static::addGlobalScope(new SoftDeletingScope);
    }

    /**
     * deleted_at sütun adını döndürür
     *
     * Model'de DELETED_AT constant tanımlı ise onu kullanır,
     * değilse varsayılan 'deleted_at' kullanır.
     *
     * @return string
     */
    public function getDeletedAtColumn(): string
    {
        return defined('static::DELETED_AT') ? static::DELETED_AT : 'deleted_at';
    }

    /**
     * Model silinmiş mi kontrol eder
     *
     * @return bool
     *
     * @example
     * if ($user->isTrashed()) {
     *     echo "Bu kullanıcı silinmiş";
     * }
     */
    public function isTrashed(): bool
    {
        return !is_null($this->getAttribute($this->getDeletedAtColumn()));
    }

    /**
     * Model'i soft delete yapar
     *
     * Fiziksel silme yerine deleted_at sütununu doldurur.
     *
     * @return bool
     *
     * @example
     * $user->delete(); // deleted_at = NOW()
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        // deleted_at'i set et
        $this->setAttribute($this->getDeletedAtColumn(), $this->freshTimestamp());
        $saved = $this->save();

        if ($saved) {
            $this->exists = false;
        }

        return $saved;
    }

    /**
     * Model'i kalıcı olarak siler (fiziksel silme)
     *
     * Veritabanından tamamen kaldırır, geri getirilemez.
     *
     * @return bool
     *
     * @example
     * $user->forceDelete(); // Veritabanından tamamen sil
     */
    public function forceDelete(): bool
    {
        if (is_null($this->getKey())) {
            return false;
        }

        // Global scope olmadan query oluştur
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
     *
     * @return bool
     *
     * @example
     * $user = User::withTrashed()->find(1);
     * $user->restore(); // deleted_at = NULL
     */
    public function restore(): bool
    {
        // deleted_at'i temizle
        $this->setAttribute($this->getDeletedAtColumn(), null);
        $this->exists = true;

        return $this->save();
    }

    /**
     * SCOPE: Silinen kayıtları da gösterir
     *
     * Global scope'u devre dışı bırakır.
     *
     * @param Builder $query
     * @return Builder
     *
     * @example
     * User::withTrashed()->get(); // Tüm kayıtlar (silinen dahil)
     */
    public function scopeWithTrashed(Builder $query): Builder
    {
        return $query->withoutGlobalScope(SoftDeletingScope::class);
    }

    /**
     * SCOPE: Sadece silinen kayıtları gösterir
     *
     * @param Builder $query
     * @return Builder
     *
     * @example
     * User::onlyTrashed()->get(); // Sadece silinmiş kayıtlar
     */
    public function scopeOnlyTrashed(Builder $query): Builder
    {
        return $query->withoutGlobalScope(SoftDeletingScope::class)
            ->whereNotNull($this->getDeletedAtColumn());
    }

    /**
     * SCOPE: Silinen kayıtları hariç tutar
     *
     * Zaten varsayılan davranış budur (global scope sayesinde).
     * Bu metot açıkça çağrılabilir.
     *
     * @param Builder $query
     * @return Builder
     *
     * @example
     * User::withoutTrashed()->get(); // Aktif kayıtlar
     */
    public function scopeWithoutTrashed(Builder $query): Builder
    {
        return $query->whereNull($this->getDeletedAtColumn());
    }
}