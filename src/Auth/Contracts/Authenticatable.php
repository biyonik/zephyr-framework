<?php

declare(strict_types=1);

namespace Zephyr\Auth\Contracts;

/**
 * Kimliği doğrulanabilir bir varlık (genellikle User modeli) için arayüz.
 * AuthManager'ın, User modelinizin yapısını bilmesine gerek kalmadan
 * onunla iletişim kurmasını sağlar.
 */
interface Authenticatable
{
    /**
     * Varlığın benzersiz kimliğini (genellikle 'id') alır.
     *
     * @return mixed
     */
    public function getAuthId(): mixed;

    /**
     * Varlığın saklanan (hashed) şifresini alır.
     *
     * @return string
     */
    public function getAuthPassword(): string;
}