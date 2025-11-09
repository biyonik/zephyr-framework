<?php

declare(strict_types=1);

namespace Zephyr\Database\Exception;

use RuntimeException;

/**
 * Mass Assignment Exception
 *
 * Korumalı attribute'lara mass assignment yapılmaya çalışıldığında fırlatılır.
 * Güvenlik için fillable veya guarded tanımlanmalıdır.
 *
 * Çözüm:
 * class User extends Model {
 *     protected $fillable = ['name', 'email']; // Whitelist
 *     // veya
 *     protected $guarded = ['id', 'is_admin']; // Blacklist
 * }
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class MassAssignmentException extends RuntimeException
{
    /**
     * Fillable property tanımlı değil hatası oluşturur
     *
     * @param string $model Model sınıf adı
     * @return self
     */
    public static function fillableNotSet(string $model): self
    {
        return new self(
            "Mass assignment kullanmak için [{$model}] model'inde [fillable] property tanımlayın."
        );
    }

    /**
     * Guarded attribute hatası oluşturur
     *
     * @param string $model Model sınıf adı
     * @param string $key Korumalı attribute adı
     * @return self
     */
    public static function guardedAttribute(string $model, string $key): self
    {
        return new self(
            "[{$key}] attribute'u [{$model}] model'inde korumalı (guarded)."
        );
    }
}