<?php

declare(strict_types=1);

namespace Zephyr\Database\Concerns;

use Zephyr\Database\Query\Builder;

/**
 * Has Soft Deletes Trait
 *
 * Verilerin veritabanından kalıcı olarak silinmesi yerine
 * 'deleted_at' sütununa bir zaman damgası ekler.
 *
 * NOT: Bu trait, global bir scope UYGULAMAZ.
 * Sorgularınızda ->whereNull('deleted_at') kullanmalı
 * veya scope'ları (örn: scopeWithoutTrashed) çağırmalısınız.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
trait HasSoftDeletes
{
    /**
     * Modelin "soft delete" kullandığını belirtir.
     */
    protected bool $softDelete = true;

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
     * Modeli "soft delete" (çöp kutusuna taşı) yapar.
     * Bu metot, Model::delete() metodunu override eder.
     */
    public function delete(): bool
    {
        // Model veritabanında mevcut değilse bir şey yapma
        if (!$this->exists) {
            return false;
        }

        // 'deleted_at' sütununu güncelle
        $this->setAttribute($this->getDeletedAtColumn(), $this->freshTimestamp());

        // Değişikliği (UPDATE sorgusu olarak) kaydet
        $saved = $this->save();

        // Kayıt başarılıysa, modeli "var olmayan" (silinmiş)
        // olarak işaretle (çünkü varsayılan sorgularda görünmeyecek)
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
        // Model::delete() metodunun orijinal (override edilmemiş)
        // mantığını burada yeniden uygularız.
        
        // Modelin anahtarı yoksa silinemez
        if (is_null($this->getKey())) {
            return false;
        }

        // Kapsam (scope) uygulanmamış temiz bir sorgu oluştur
        // (Model::newQuery() bu trait tarafından override edilmediği için güvenli)
        $query = $this->newQuery();

        // Sadece bu modeli hedefle
        $deleted = $query
            ->where($this->getKeyName(), '=', $this->getKey())
            ->delete(); // Bu, QueryBuilder::delete() metodunu çağırır (kalıcı silme)

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
        // 'deleted_at' sütununu NULL yap
        $this->setAttribute($this->getDeletedAtColumn(), null);

        // Modeli tekrar "var" olarak işaretle
        $this->exists = true;

        // Değişikliği kaydet (UPDATE)
        return $this->save();
    }

    /**
     * SCOPE: Sorguya "çöp kutusu dahil" koşulunu ekler.
     * (Bu basit implementasyonda bu bir no-op (boş işlem) scope'udur,
     * çünkü varsayılan sorgu zaten her şeyi getirir)
     */
    public function scopeWithTrashed(Builder $query): Builder
    {
        return $query;
    }

    /**
     * SCOPE: Sorguya "sadece çöp kutusu" koşulunu ekler.
     */
    public function scopeOnlyTrashed(Builder $query): Builder
    {
        return $query->whereNotNull($this->getDeletedAtColumn());
    }
    
    /**
     * SCOPE: Sorguya "çöp kutusu hariç" (varsayılan) koşulunu ekler.
     * KULLANIM: User::withoutTrashed()->get()
     */
    public function scopeWithoutTrashed(Builder $query): Builder
    {
        return $query->whereNull($this->getDeletedAtColumn());
    }
}