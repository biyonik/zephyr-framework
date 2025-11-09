<?php

declare(strict_types=1);

namespace Zephyr\Database\Concerns;

use Zephyr\Database\ScopeInterface;
use Zephyr\Database\Query\Builder;
use Zephyr\Database\Model;

/**
 * Modeller için Global Scope (Evrensel Kapsam) yönetimi.
 */
trait HasGlobalScopes
{
    /**
     * Modele kayıtlı global scope'lar.
     * @var array<string, ScopeInterface|callable>
     */
    protected static array $globalScopes = [];

    /**
     * Model "boot" (başlatma) edildi mi?
     */
    protected static bool $booted = false;

    /**
     * Trait'leri ve global scope'ları başlatır (boot eder).
     */
    protected function bootIfNotBooted(): void
    {
        if (!static::$booted) {
            static::$booted = true;

            // Bu, SoftDeletes gibi trait'lerin kendi 'boot'
            // metotlarını çağırmasını sağlar (örn: bootHasSoftDeletes)
            $this->bootTraits();
        }
    }

    /**
     * Sınıftaki tüm "boot" ile başlayan trait metotlarını çalıştırır.
     */
    protected function bootTraits(): void
    {
        $class = static::class;

        foreach (class_uses_recursive($class) as $trait) {
            $method = 'boot' . class_basename($trait);
            if (method_exists($class, $method)) {
                static::{$method}();
            }
        }
    }

    /**
     * Modele yeni bir global scope ekler.
     */
    public static function addGlobalScope(ScopeInterface $scope): void
    {
        static::$globalScopes[get_class($scope)] = $scope;
    }

    /**
     * Bir sorgu oluşturucuya (builder) tüm global scope'ları uygular.
     */
    public function applyGlobalScopes(Builder $builder): Builder
    {
        foreach (static::$globalScopes as $scope) {
            $scope->apply($builder, $this);
        }
        return $builder;
    }

    /**
     * Yeni bir sorgu oluşturucuyu (query builder) global scope'lar
     * uygulanmış halde döndürür.
     */
    public function newQuery(): Builder
    {
        // 1. Temel sorgu oluşturucuyu al
        $builder = (new Builder($this->getConnection()))
            ->setModel($this)
            ->from($this->getTable());

        // 2. Global scope'ları uygula
        return $this->applyGlobalScopes($builder);
    }
}