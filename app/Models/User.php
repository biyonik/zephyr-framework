<?php

declare(strict_types=1);

namespace App\Models;

use Zephyr\Database\Model;
use Zephyr\Auth\Contracts\Authenticatable; // <-- YENİ

class User extends Model implements Authenticatable // <-- YENİ
{
    protected string $table = 'users';

    protected array $fillable = [
        'name', 'email', 'password',
    ];

    protected array $hidden = [
        'password',
    ];

    /**
     * YENİ: Authenticatable Arayüz Metotları
     */

    /**
     * Kullanıcının ID'sini döndürür.
     */
    public function getAuthId(): mixed
    {
        return $this->getKey();
    }

    /**
     * Kullanıcının HASH'LENMİŞ şifresini döndürür.
     */
    public function getAuthPassword(): string
    {
        return $this->password;
    }

    /**
     * Şifreyi otomatik olarak hash'ler (Mutator).
     */
    public function setPasswordAttribute(string $value): void
    {
        // Model'in HasAttributes trait'indeki setMutator özelliğini kullanıyoruz
        $this->attributes['password'] = password_hash($value, PASSWORD_BCRYPT);
    }
}