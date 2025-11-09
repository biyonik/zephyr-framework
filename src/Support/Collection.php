<?php

declare(strict_types=1);

namespace Zephyr\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Süper-Dizi (Collection) Sınıfı
 *
 * PHP dizileri için akıcı (fluent), nesne yönelimli (OOP)
 * bir arayüz sağlar (Laravel Collections'dan esinlenilmiştir).
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 */
class Collection implements ArrayAccess, IteratorAggregate, JsonSerializable, Countable
{
    /**
     * Koleksiyonun içindeki öğeler (items).
     *
     * @var array<TKey, TValue>
     */
    protected array $items = [];

    /**
     * Yeni bir koleksiyon nesnesi oluşturur.
     *
     * @param array<TKey, TValue> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Diziyi bir koleksiyona dönüştürür.
     */
    public static function make(array $items = []): self
    {
        return new self($items);
    }

    /**
     * Koleksiyondaki tüm öğeleri döndürür.
     *
     * @return array<TKey, TValue>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Koleksiyon üzerinde bir 'map' (dönüştürme) işlemi yapar.
     *
     * @param callable(TValue, TKey): mixed $callback
     * @return self
     */
    public function map(callable $callback): self
    {
        $keys = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);

        return new self(array_combine($keys, $items));
    }

    /**
     * Koleksiyonu bir 'filter' (filtreleme) işleminden geçirir.
     *
     * @param callable(TValue, TKey): bool $callback
     * @return self
     */
    public function filter(callable $callback): self
    {
        // ARRAY_FILTER_USE_BOTH ile callback'e hem değeri hem anahtarı gönder
        return new self(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Koleksiyonu tek bir değere indirger (reduce).
     *
     * @param callable(mixed, TValue, TKey): mixed $callback
     * @param mixed $initial Başlangıç değeri
     * @return mixed
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Koleksiyondaki öğelerden belirli bir anahtarın (key) değerlerini çeker.
     *
     * @param string $key Çekilecek anahtar adı
     * @return self
     */
    public function pluck(string $key): self
    {
        return $this->map(function ($item) use ($key) {
            // Model veya stdClass nesnesi olabilir
            if (is_object($item)) {
                return $item->{$key} ?? null;
            }
            // Dizi olabilir
            if (is_array($item)) {
                return $item[$key] ?? null;
            }
            return null;
        });
    }

    /**
     * Öğeleri bir anahtara göre gruplar.
     *
     * @param string $key Gruplanacak anahtar
     * @return self (iç içe Collection'lar ile)
     */
    public function groupBy(string $key): self
    {
        $grouped = [];
        foreach ($this->items as $item) {
            $value = $item->{$key} ?? 'unknown';
            if (!isset($grouped[$value])) {
                $grouped[$value] = new self();
            }
            $grouped[$value]->push($item);
        }
        return new self($grouped);
    }

    /**
     * Öğeleri bir anahtara göre anahtarlar (sözlük yapar).
     *
     * @param string $key Anahtar olarak kullanılacak sütun
     * @return self
     */
    public function keyBy(string $key): self
    {
        $keyed = [];
        foreach ($this->items as $item) {
            $value = $item->{$key} ?? null;
            if ($value !== null) {
                $keyed[$value] = $item;
            }
        }
        return new self($keyed);
    }

    /**
     * Koleksiyona bir öğe ekler.
     */
    public function push(mixed $value): self
    {
        $this->items[] = $value;
        return $this;
    }

    /**
     * Koleksiyondaki ilk öğeyi döndürür.
     *
     * @return TValue|null
     */
    public function first(): mixed
    {
        return reset($this->items) ?: null;
    }

    /**
     * Koleksiyondaki son öğeyi döndürür.
     *
     * @return TValue|null
     */
    public function last(): mixed
    {
        return end($this->items) ?: null;
    }

    /**
     * Koleksiyonun boş olup olmadığını kontrol eder.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Koleksiyonun dolu olup olmadığını kontrol eder.
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Basit 'where' koşulu (örn: ->where('status', 'active')).
     */
    public function where(string $key, mixed $value): self
    {
        return $this->filter(function($item) use ($key, $value) {
            return $item->{$key} == $value;
        });
    }

    /**
     * Belirli bir anahtara göre benzersiz (unique) öğeleri alır.
     */
    public function unique(string $key): self
    {
        $unique = [];
        $seen = [];
        foreach ($this->items as $item) {
            $value = $item->{$key} ?? null;
            if ($value !== null && !isset($seen[$value])) {
                $seen[$value] = true;
                $unique[] = $item;
            }
        }
        return new self($unique);
    }

    /**
     * Bir anahtara göre sıralar.
     */
    public function sortBy(string $key, bool $descending = false): self
    {
        $items = $this->items;
        usort($items, static function ($a, $b) use ($key) {
            return $a->{$key} <=> $b->{$key};
        });

        if ($descending) {
            $items = array_reverse($items);
        }

        return new self($items);
    }

    public function sortByDesc(string $key): self
    {
        return $this->sortBy($key, true);
    }

    /*
    |--------------------------------------------------------------------------
    | PHP Arayüz (Interface) Metotları
    |--------------------------------------------------------------------------
    */

    /**
     * Countable: `count($collection)`
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * IteratorAggregate: `foreach ($collection as $item)`
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * JsonSerializable: `json_encode($collection)`
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    /**
     * ArrayAccess: `$collection['key']` (Okuma)
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * ArrayAccess: `$collection['key'] = $value` (Yazma)
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * ArrayAccess: `unset($collection['key'])` (Silme)
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }
}