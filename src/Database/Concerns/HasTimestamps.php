<?php

declare(strict_types=1);

namespace Zephyr\Database\Concerns;

/**
 * Has Timestamps Trait
 *
 * created_at ve updated_at sütunlarını otomatik yönetir.
 * Model kaydedilirken timestamps otomatik güncellenir.
 *
 * Kullanım:
 * - $model->timestamps = true (varsayılan)
 * - $model->timestamps = false (devre dışı)
 *
 * Özelleştirme:
 * - const CREATED_AT = 'custom_created_at';
 * - const UPDATED_AT = 'custom_updated_at';
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
trait HasTimestamps
{
    /**
     * Otomatik timestamps aktif mi?
     */
    public bool $timestamps = true;

    /**
     * Model timestamps kullanıyor mu?
     */
    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    /**
     * created_at sütun adını döndürür
     */
    public function getCreatedAtColumn(): string
    {
        return defined('static::CREATED_AT') ? static::CREATED_AT : 'created_at';
    }

    /**
     * updated_at sütun adını döndürür
     */
    public function getUpdatedAtColumn(): string
    {
        return defined('static::UPDATED_AT') ? static::UPDATED_AT : 'updated_at';
    }

    /**
     * Timestamp sütunlarını günceller
     * 
     * Bu metot Model::save() içinde otomatik çağrılır.
     */
    public function updateTimestamps(): self
    {
        if (!$this->usesTimestamps()) {
            return $this;
        }

        $time = $this->freshTimestamp();

        // updated_at'i her zaman güncelle
        $this->setUpdatedAt($time);

        // created_at'i sadece yeni kayıtlarda set et
        if (!$this->exists && !$this->isDirty($this->getCreatedAtColumn())) {
            $this->setCreatedAt($time);
        }

        return $this;
    }

    /**
     * Yeni timestamp değeri oluşturur
     */
    protected function freshTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Timestamp string olarak döndürür
     */
    public function freshTimestampString(): string
    {
        return $this->freshTimestamp();
    }

    /**
     * created_at değerini set eder
     */
    public function setCreatedAt(mixed $value): self
    {
        $this->setAttribute($this->getCreatedAtColumn(), $value);
        return $this;
    }

    /**
     * updated_at değerini set eder
     */
    public function setUpdatedAt(mixed $value): self
    {
        $this->setAttribute($this->getUpdatedAtColumn(), $value);
        return $this;
    }

    /**
     * created_at değerini döndürür
     */
    public function getCreatedAt(): mixed
    {
        return $this->getAttribute($this->getCreatedAtColumn());
    }

    /**
     * updated_at değerini döndürür
     */
    public function getUpdatedAt(): mixed
    {
        return $this->getAttribute($this->getUpdatedAtColumn());
    }

    /**
     * Model'i kaydetmeden sadece updated_at'i günceller
     * 
     * İlişkili modeller için parent model'in updated_at'ini güncellemek
     * için kullanılır.
     */
    public function touch(): bool
    {
        if (!$this->usesTimestamps()) {
            return false;
        }

        $this->updateTimestamps();

        return $this->save();
    }

    /**
     * Parent ilişkiyi touch eder
     * 
     * Örnek: Comment kaydedildiğinde Post'un updated_at'i güncellenir
     * 
     * @param string $relation Parent relation adı
     * @return bool
     */
    public function touchParent(string $relation): bool
    {
        if (!$this->relationLoaded($relation)) {
            $this->load($relation);
        }

        $parent = $this->getRelation($relation);

        if ($parent && method_exists($parent, 'touch')) {
            return $parent->touch();
        }

        return false;
    }
}