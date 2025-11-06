<?php

declare(strict_types=1);

namespace Zephyr\Auth;

use Zephyr\Auth\Contracts\Authenticatable;
use Zephyr\Auth\Contracts\UserProvider;

/**
 * Kullanıcıları Eloquent/Model kullanarak veritabanından bulur.
 */
class EloquentUserProvider implements UserProvider
{
    protected string $modelClass;

    /**
     * @param string $modelClass (Genellikle App\Models\User::class)
     */
    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
    }

    /**
     * Kullanıcıyı login bilgileri ile bulur.
     * (Sadece email ile bulma varsayılmıştır, genişletilebilir)
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials['email'])) {
            return null;
        }

        // Mevcut Model::query() yapınızı kullanıyoruz
        $user = $this->modelClass::query()
                    ->where('email', '=', $credentials['email'])
                    ->first();
        
        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * Kullanıcıyı ID ile bulur.
     */
    public function retrieveById(mixed $identifier): ?Authenticatable
    {
        $user = $this->modelClass::find($identifier);
        
        return $user instanceof Authenticatable ? $user : null;
    }
}