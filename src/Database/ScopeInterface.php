<?php

declare(strict_types=1);

namespace Zephyr\Database;

use Zephyr\Database\Query\Builder;

/**
 * Global Scope Interface
 *
 * Tüm global scope sınıflarının implement etmesi gereken arayüz.
 * Global scope'lar tüm sorgulara otomatik olarak kısıt ekler.
 *
 * Kullanım Örneği:
 * class ActiveScope implements ScopeInterface {
 *     public function apply(Builder $builder, Model $model): void {
 *         $builder->where('active', '=', true);
 *     }
 * }
 *
 * Model'e Ekleme:
 * class User extends Model {
 *     protected static function boot() {
 *         parent::boot();
 *         static::addGlobalScope(new ActiveScope);
 *     }
 * }
 *
 * Kullanım:
 * User::all(); // WHERE active = 1 otomatik eklenir
 * User::withoutGlobalScope(ActiveScope::class)->get(); // Scope'suz
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
interface ScopeInterface
{
    /**
     * Global scope'u query builder'a uygular
     *
     * Bu metot her sorgu oluşturulduğunda otomatik çağrılır.
     * WHERE, JOIN veya diğer query kısıtları eklenebilir.
     *
     * @param Builder $builder Query builder instance
     * @param Model $model Model instance
     * @return void
     *
     * @example
     * public function apply(Builder $builder, Model $model): void {
     *     $builder->where('deleted_at', '=', null);
     * }
     */
    public function apply(Builder $builder, Model $model): void;
}