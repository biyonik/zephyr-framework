<?php

declare(strict_types=1);

namespace Zephyr\Auth;

use Zephyr\Auth\Contracts\Authenticatable;
use Zephyr\Exceptions\Http\ForbiddenException;
use Zephyr\Support\Config;

class Gate
{
    /**
     * ✅ FIX: Static abilities (tüm instance'lar arasında paylaşılır)
     * @var array<string, callable>
     */
    protected static array $abilities = [];

    /**
     * Önbelleğe alınmış User model instance.
     */
    protected ?Authenticatable $user = null;

    public function __construct(
        private readonly AuthManager $auth,
        private string               $userModel
    ) {
    }

    /**
     * ✅ FIX: Static ability tanımlar.
     */
    public function define(string $ability, callable $callback): void
    {
        static::$abilities[$ability] = $callback;
    }

    /**
     * Kullanıcının yetkiye sahip olup olmadığını kontrol eder.
     */
    public function allows(string $ability, array $arguments = []): bool
    {
        $user = $this->getUser();
        if (!$user) {
            return false;
        }

        if (!isset(static::$abilities[$ability])) {
            return false;
        }

        $callback = static::$abilities[$ability];
        $arguments = array_merge([$user], $arguments);

        return (bool) $callback(...$arguments);
    }

    /**
     * Kullanıcının yetkiye sahip olmadığını kontrol eder.
     */
    public function denies(string $ability, array $arguments = []): bool
    {
        return !$this->allows($ability, $arguments);
    }

    /**
     * Yetki kontrolü yapar, yoksa 403 fırlatır.
     */
    public function authorize(string $ability, mixed $arguments = []): void
    {
        if (!is_array($arguments)) {
            $arguments = [$arguments];
        }

        if ($this->denies($ability, $arguments)) {
            throw new ForbiddenException('Bu eylemi gerçekleştirmek için yetkiniz yok.');
        }
    }

    /**
     * ✅ FIX: AuthManager'dan User modelini al.
     */
    protected function getUser(): ?Authenticatable
    {
        if ($this->user) {
            return $this->user;
        }

        // AuthManager zaten user'ı cache'liyor
        return $this->user = $this->auth->user();
    }
}