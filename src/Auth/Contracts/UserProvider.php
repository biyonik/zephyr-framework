<?php

declare(strict_types=1);

namespace Zephyr\Auth\Contracts;

interface UserProvider
{
    /**
     * Kullanıcıyı 'login' bilgileri (örn: email) ile bulur.
     *
     * @param array $credentials
     * @return Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable;

    /**
     * Kullanıcıyı 'id' bilgisi ile bulur.
     *
     * @param mixed $identifier
     * @return Authenticatable|null
     */
    public function retrieveById(mixed $identifier): ?Authenticatable;
}