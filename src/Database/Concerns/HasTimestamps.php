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
 * - $model->timestamps = false (devre dışı bırakmak için)
 *
 * Özelleştirme:
 * - $createdAtColumn değiştirilebilir
 * - $updatedAtColumn değiştirilebilir
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
     * created_at sütun adı
     */
    protected string $createdAtColumn = 'created_at';

    /**
     * updated_at sütun adı
     */
    protected string $updatedAtColumn = 'updated_at';

    /**
     * Model timestamps kullanıyor mu kontrol eder
     *
     * @return bool
     */
    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    /**
     * created_at sütun adını döndürür
     *
     * @return string
     */
    public function getCreatedAtColumn(): string
    {
        return $this->createdAtColumn;
    }

    /**
     * updated_at sütun adını döndürür
     *
     * @return string
     */
    public function getUpdatedAtColumn(): string
    {
        return $this->updatedAtColumn;
    }

    /**
     * Timestamp sütunlarını günceller
     *
     * Bu metot Model::save() içinde otomatik çağrılır.
     *
     * @return self
     */
    public function updateTimestamps(): self
    {
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
     *
     * @return string Y-m-d H:i:s formatında timestamp
     */
    protected function freshTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Timestamp string olarak döndürür
     *
     * @return string
     */
    public function freshTimestampString(): string
    {
        return $this->fromDateTime($this->freshTimestamp());
    }

    /**
     * created_at değerini set eder
     *
     * @param mixed $value Timestamp değeri
     * @return self
     */
    public function setCreatedAt(mixed $value): self
    {
        $this->setAttribute($this->getCreatedAtColumn(), $value);
        return $this;
    }

    /**
     * updated_at değerini set eder
     *
     * @param mixed $value Timestamp değeri
     * @return self
     */
    public function setUpdatedAt(mixed $value): self
    {
        $this->setAttribute($this->getUpdatedAtColumn(), $value);
        return $this;
    }

    /**
     * created_at değerini döndürür
     *
     * @return mixed
     */
    public function getCreatedAt(): mixed
    {
        return $this->getAttribute($this->getCreatedAtColumn());
    }

    /**
     * updated_at değerini döndürür
     *
     * @return mixed
     */
    public function getUpdatedAt(): mixed
    {
        return $this->getAttribute($this->getUpdatedAtColumn());
    }

    /**
     * updated_at'i günceller (kayıt yapmadan)
     *
     * Model'i değiştirmeden sadece updated_at'i güncellemek için kullanılır.
     *
     * @return bool
     *
     * @example
     * $post->touch(); // updated_at güncellenir
     */
    public function touch(): bool
    {
        if (!$this->usesTimestamps()) {
            return false;
        }

        $this->updateTimestamps();

        return $this->save();
    }
}