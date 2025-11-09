<?php

declare(strict_types=1);

namespace Zephyr\Database;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Exception\MassAssignmentException;
use Zephyr\Database\Exception\ModelNotFoundException;
use Zephyr\Database\Concerns\HasAttributes;
use Zephyr\Database\Concerns\HasTimestamps;
use Zephyr\Database\Concerns\HasRelationships;
use Zephyr\Support\Collection;

/**
 * Active Record Base Model
 *
 * Tüm model sınıflarının miras alacağı temel sınıf.
 * Active Record pattern implementasyonu.
 *
 * Özellikler:
 * - Mass assignment koruması (fillable/guarded)
 * - Attribute casting (int, bool, json, date vs.)
 * - Dirty tracking (değişiklik takibi)
 * - Otomatik timestamps (created_at, updated_at)
 * - İlişkiler (hasMany, belongsTo, hasOne, belongsToMany)
 * - Query builder entegrasyonu
 * - Global scopes
 * - Soft deletes (trait ile)
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
abstract class Model
{
    use HasAttributes;
    use HasTimestamps;
    use HasRelationships;

    /**
     * Veritabanı bağlantısı
     */
    protected ?Connection $connection = null;

    /**
     * Tablo adı (alt sınıflarda override edilebilir)
     * Belirtilmezse class adından otomatik türetilir
     */
    protected string $table = '';

    /**
     * Primary key sütun adı
     */
    protected string $primaryKey = 'id';

    /**
     * Primary key tipi
     */
    protected string $keyType = 'int';

    /**
     * Primary key auto-increment mi?
     */
    public bool $incrementing = true;

    /**
     * Model veritabanında var mı?
     */
    public bool $exists = false;

    /**
     * Model yeni mi oluşturuldu?
     */
    public bool $wasRecentlyCreated = false;

    /**
     * Mass assignment whitelist (izin verilen alanlar)
     *
     * @example ['name', 'email', 'password']
     */
    protected array $fillable = [];

    /**
     * Mass assignment blacklist (yasak alanlar)
     *
     * @example ['id', 'is_admin', 'role']
     */
    protected array $guarded = ['*'];

    /**
     * Array/JSON'a çevirirken gizlenecek alanlar
     *
     * @example ['password', 'remember_token']
     */
    protected array $hidden = [];

    /**
     * Array/JSON'a çevirirken her zaman gösterilecek alanlar
     */
    protected array $visible = [];

    /**
     * Attribute type casting
     *
     * @example ['is_admin' => 'bool', 'metadata' => 'json']
     */
    protected array $casts = [];

    /**
     * Date olarak işlenecek alanlar
     */
    protected array $dates = [];

    /**
     * Varsayılan attribute değerleri
     */
    protected array $attributes = [];

    /**
     * Veritabanından gelen orjinal değerler
     */
    protected array $original = [];

    /**
     * Model global scope'ları
     * @var array<string, \Zephyr\Database\ScopeInterface>
     */
    protected static array $globalScopes = [];

    /**
     * Model boot edildi mi?
     */
    protected static array $booted = [];

    /**
     * Constructor
     *
     * @param array $attributes Başlangıç attribute'ları
     */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->syncOriginal();
        $this->fill($attributes);
    }

    /**
     * Model henüz boot edilmediyse boot eder
     *
     * Her model sınıfı için bir kez çalışır.
     */
    protected function bootIfNotBooted(): void
    {
        $class = static::class;

        if (!isset(static::$booted[$class])) {
            static::$booted[$class] = true;
            $this->bootTraits();
        }
    }

    /**
     * Trait boot metotlarını çalıştırır
     *
     * Örnek: HasSoftDeletes trait'i kullanılıyorsa bootHasSoftDeletes() çağrılır
     */
    protected function bootTraits(): void
    {
        $class = static::class;

        foreach (class_uses_recursive($class) as $trait) {
            $method = 'boot' . class_basename($trait);

            if (method_exists($class, $method) && !method_exists(__CLASS__, $method)) {
                static::{$method}();
            }
        }
    }

    /**
     * Global scope ekler
     *
     * @param \Zephyr\Database\ScopeInterface $scope
     */
    public static function addGlobalScope(\Zephyr\Database\ScopeInterface $scope): void
    {
        $class = static::class;
        static::$globalScopes[$class][get_class($scope)] = $scope;
    }

    /**
     * Global scope'ları query builder'a uygular
     *
     * @param Builder $builder
     * @return Builder
     */
    protected function applyGlobalScopes(Builder $builder): Builder
    {
        $class = static::class;

        if (!isset(static::$globalScopes[$class])) {
            return $builder;
        }

        foreach (static::$globalScopes[$class] as $scope) {
            $scope->apply($builder, $this);
        }

        return $builder;
    }

    /**
     * Tablo adını döndürür
     *
     * @return string
     */
    public function getTable(): string
    {
        if (!empty($this->table)) {
            return $this->table;
        }

        // Class adından otomatik türet: User -> users
        $className = class_basename(static::class);
        return strtolower($className) . 's';
    }

    /**
     * Primary key sütun adını döndürür
     *
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Primary key değerini döndürür
     *
     * @return mixed
     */
    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Primary key değerini set eder
     *
     * @param mixed $value
     * @return self
     */
    public function setKey(mixed $value): self
    {
        $this->setAttribute($this->getKeyName(), $value);
        return $this;
    }

    /**
     * Database bağlantısını döndürür
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        if (is_null($this->connection)) {
            $this->connection = Connection::getInstance();
        }

        return $this->connection;
    }

    /**
     * Database bağlantısını set eder
     *
     * @param Connection $connection
     * @return self
     */
    public function setConnection(Connection $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Yeni query builder oluşturur (global scope'larla)
     *
     * @return Builder
     */
    public function newQuery(): Builder
    {
        $builder = (new Builder($this->getConnection()))
            ->setModel($this)
            ->from($this->getTable());

        return $this->applyGlobalScopes($builder);
    }

    /**
     * Yeni query builder oluşturur (global scope'suz)
     *
     * @return Builder
     */
    public function newQueryWithoutScopes(): Builder
    {
        return (new Builder($this->getConnection()))
            ->setModel($this)
            ->from($this->getTable());
    }

    /**
     * Model array'ini Collection'a çevirir
     *
     * Alt sınıflar override edebilir (örn: EloquentCollection)
     *
     * @param array $models
     * @return Collection
     */
    public function newCollection(array $models): Collection
    {
        return new Collection($models);
    }

    /**
     * Model'i attribute'larla doldurur (mass assignment)
     *
     * @param array $attributes
     * @return self
     * @throws MassAssignmentException
     */
    public function fill(array $attributes): self
    {
        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Mass assignment için fillable attribute'ları döndürür
     *
     * @param array $attributes
     * @return array
     * @throws MassAssignmentException
     */
    protected function fillableFromArray(array $attributes): array
    {
        // Tamamen guarded mı kontrol et
        if (count($this->fillable) === 0 && $this->guarded === ['*']) {
            throw MassAssignmentException::fillableNotSet(static::class);
        }

        // Whitelist modu (fillable)
        if (count($this->fillable) > 0) {
            return array_intersect_key(
                $attributes,
                array_flip($this->fillable)
            );
        }

        // Blacklist modu (guarded)
        return array_diff_key(
            $attributes,
            array_flip($this->guarded)
        );
    }

    /**
     * Attribute fillable mı kontrol eder
     *
     * @param string $key
     * @return bool
     */
    public function isFillable(string $key): bool
    {
        // Fillable whitelist'te mi?
        if (in_array($key, $this->fillable, true)) {
            return true;
        }

        // Guarded blacklist'te mi?
        if ($this->isGuarded($key)) {
            return false;
        }

        // Default: fillable boşsa ve guarded değilse OK
        return empty($this->fillable) && !$this->isGuarded($key);
    }

    /**
     * Attribute guarded mı kontrol eder
     *
     * @param string $key
     * @return bool
     */
    public function isGuarded(string $key): bool
    {
        return in_array($key, $this->guarded, true) || $this->guarded === ['*'];
    }

    /**
     * Orjinal attribute'ları senkronize eder
     *
     * @return self
     */
    public function syncOriginal(): self
    {
        $this->original = $this->attributes;
        $this->clearAttributeCache();
        return $this;
    }

    /**
     * Tek bir attribute'u senkronize eder
     *
     * @param string $key
     * @return self
     */
    public function syncOriginalAttribute(string $key): self
    {
        $this->original[$key] = $this->attributes[$key] ?? null;
        return $this;
    }

    /**
     * Model veya belirli attribute'lar değişti mi?
     *
     * @param array|string|null $attributes
     * @return bool
     */
    public function isDirty(array|string|null $attributes = null): bool
    {
        return $this->hasChanges(
            $this->getDirty(),
            is_array($attributes) ? $attributes : func_get_args()
        );
    }

    /**
     * Model veya attribute'lar temiz mi (değişmemiş mi)?
     *
     * @param array|string|null $attributes
     * @return bool
     */
    public function isClean(array|string|null $attributes = null): bool
    {
        return !$this->isDirty(...func_get_args());
    }

    /**
     * Değişen attribute'ları döndürür
     *
     * @return array
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original)) {
                $dirty[$key] = $value;
            } elseif ($value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Son save'den sonraki değişiklikleri döndürür
     *
     * @return array
     */
    public function getChanges(): array
    {
        return $this->getDirty();
    }

    /**
     * Belirli attribute'larda değişiklik var mı kontrol eder
     *
     * @param array $changes
     * @param array $attributes
     * @return bool
     */
    protected function hasChanges(array $changes, array $attributes = []): bool
    {
        // Tüm attribute'ları kontrol et
        if (empty($attributes)) {
            return count($changes) > 0;
        }

        // Belirli attribute'ları kontrol et
        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $changes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Model'i veritabanına kaydeder
     *
     * @return bool Başarı durumu
     */
    public function save(): bool
    {
        // Timestamps'i güncelle
        if ($this->usesTimestamps() && ($this->isDirty() || !$this->exists)) {
            $this->updateTimestamps();
        }

        // INSERT veya UPDATE
        if ($this->exists) {
            $saved = $this->performUpdate();
        } else {
            $saved = $this->performInsert();
        }

        // Başarılı ise orjinal'i senkronize et
        if ($saved) {
            $this->syncOriginal();
        }

        return $saved;
    }

    /**
     * INSERT işlemi yapar
     *
     * @return bool
     */
    protected function performInsert(): bool
    {
        $attributes = $this->getAttributesForInsert();

        if (empty($attributes)) {
            return true;
        }

        $id = $this->newQuery()->insertGetId($attributes);

        if ($this->incrementing) {
            $this->setAttribute($this->getKeyName(), $id);
        }

        $this->exists = true;
        $this->wasRecentlyCreated = true;

        return true;
    }

    /**
     * UPDATE işlemi yapar
     *
     * @return bool
     */
    protected function performUpdate(): bool
    {
        // Değişiklik var mı kontrol et
        $dirty = $this->getDirty();

        if (count($dirty) === 0) {
            return true;
        }

        $affected = $this->newQuery()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->update($dirty);

        return $affected > 0;
    }

    /**
     * INSERT için attribute'ları döndürür
     *
     * @return array
     */
    protected function getAttributesForInsert(): array
    {
        return $this->attributes;
    }

    /**
     * Model'i veritabanından siler
     *
     * Soft delete trait kullanılıyorsa soft delete yapar.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $deleted = $this->newQuery()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->delete();

        if ($deleted > 0) {
            $this->exists = false;
            return true;
        }

        return false;
    }

    /**
     * Model'i veritabanından yeniler (refresh)
     *
     * @return self
     */
    public function refresh(): self
    {
        if (!$this->exists) {
            return $this;
        }

        $fresh = $this->newQuery()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->first();

        if ($fresh) {
            $this->setRawAttributes($fresh->getAttributes(), true);
            $this->relations = [];
        }

        return $this;
    }

    /**
     * Model'i çoğaltır (replicate)
     *
     * @param array $except Hariç tutulacak alanlar
     * @return static
     */
    public function replicate(array $except = []): static
    {
        // Otomatik exclude: primary key + timestamps
        $defaults = [$this->getKeyName()];

        if ($this->usesTimestamps()) {
            $defaults[] = $this->getCreatedAtColumn();
            $defaults[] = $this->getUpdatedAtColumn();
        }

        $except = array_merge($defaults, $except);

        $attributes = array_diff_key(
            $this->attributes,
            array_flip($except)
        );

        $instance = new static;
        $instance->fill($attributes);
        $instance->exists = false;

        return $instance;
    }

    /**
     * Model'i array'e çevirir
     *
     * @return array
     */
    public function toArray(): array
    {
        $attributes = $this->attributesToArray();

        // visible/hidden uygula
        if (!empty($this->visible)) {
            $attributes = array_intersect_key($attributes, array_flip($this->visible));
        }

        if (!empty($this->hidden)) {
            $attributes = array_diff_key($attributes, array_flip($this->hidden));
        }

        return $attributes;
    }

    /**
     * Model'i JSON'a çevirir
     *
     * @param int $options JSON encode seçenekleri
     * @return string
     * @throws \JsonException
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | $options);
    }

    /**
     * Database satırından yeni model oluşturur
     *
     * @param array $attributes
     * @return static
     */
    public function newFromBuilder(array $attributes): static
    {
        $instance = new static;
        $instance->exists = true;
        $instance->attributes = $attributes;
        $instance->syncOriginal();

        return $instance;
    }

    /**
     * Static: Yeni model oluşturur ve kaydeder
     *
     * @param array $attributes
     * @return static
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    /**
     * Static: Primary key ile model bulur
     *
     * @param mixed $id
     * @return static|null
     */
    public static function find(mixed $id): ?static
    {
        return static::query()
            ->where((new static)->getKeyName(), '=', $id)
            ->first();
    }

    /**
     * Static: Model bulamazsa exception fırlatır
     *
     * @param mixed $id
     * @return static
     * @throws ModelNotFoundException
     */
    public static function findOrFail(mixed $id): static
    {
        $model = static::find($id);

        if (is_null($model)) {
            throw (new ModelNotFoundException)
                ->setModel(static::class)
                ->setIds([$id]);
        }

        return $model;
    }

    /**
     * Static: Tüm modelleri döndürür
     *
     * @param array $columns
     * @return Collection
     */
    public static function all(array $columns = ['*']): Collection
    {
        return static::query()->select(...$columns)->get();
    }

    /**
     * Static: Query builder oluşturur
     *
     * @return Builder
     */
    public static function query(): Builder
    {
        return (new static)->newQuery();
    }

    /**
     * deleted_at null olan sütun adını döndürür
     * @return string
     */
    public function getDeletedAtColumn(): string
    {
        return 'deleted_at';
    }

    /**
     * Magic static method forwarding
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Magic instance method forwarding (query builder'a)
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->newQuery()->$method(...$parameters);
    }

    /**
     * Magic getter
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Magic setter
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Magic isset
     */
    public function __isset(string $key): bool
    {
        return !is_null($this->getAttribute($key));
    }

    /**
     * Magic unset
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * String representation
     * @throws \JsonException
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}