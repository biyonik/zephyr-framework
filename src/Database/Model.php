<?php

declare(strict_types=1);

namespace Zephyr\Database;

use Zephyr\Database\Query\Builder;
use Zephyr\Database\Exception\MassAssignmentException;
use Zephyr\Database\Exception\ModelNotFoundException;
use Zephyr\Database\Concerns\HasAttributes;
use Zephyr\Database\Concerns\HasTimestamps;
use Zephyr\Database\Concerns\HasRelationships;
use Zephyr\Database\Events\HasModelEvents;
use Zephyr\Support\Collection;

/**
 * Active Record Temel Model
 *
 * ✅ YENİ: Model Event System entegrasyonu!
 * ✅ YENİ: Advanced mass assignment validation!
 * ✅ YENİ: Improved error handling!
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
    use HasModelEvents; // ✅ YENİ: Event system

    /**
     * Timestamp sütun adları (override edilebilir)
     */
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    public const DELETED_AT = 'deleted_at';

    /**
     * Veritabanı bağlantısı
     */
    protected ?Connection $connection = null;

    /**
     * Tablo adı (belirtilmezse class adından türetilir)
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
     * Mass assignment whitelist
     */
    protected array $fillable = [];

    /**
     * Mass assignment blacklist
     */
    protected array $guarded = ['*'];

    /**
     * Array/JSON'a çevirirken gizlenecek alanlar
     */
    protected array $hidden = [];

    /**
     * Array/JSON'a çevirirken gösterilecek alanlar
     */
    protected array $visible = [];

    /**
     * Attribute type casting
     */
    protected array $casts = [];

    /**
     * Date olarak işlenecek alanlar
     */
    protected array $dates = [];

    /**
     * ✅ YENİ: Validation rules (advanced mass assignment)
     */
    protected array $rules = [];

    /**
     * ✅ YENİ: Custom validation messages
     */
    protected array $messages = [];

    /**
     * Varsayılan attribute değerleri
     */
    protected array $attributes = [];

    /**
     * Veritabanından gelen orjinal değerler
     */
    protected array $original = [];

    /**
     * Son save'deki değişiklikler
     */
    protected array $changes = [];

    /**
     * Model başına global scope'lar
     */
    protected static array $globalScopes = [];

    /**
     * Model başına boot edildi mi?
     */
    protected static array $booted = [];

    /**
     * Mass assignment guard aktif mi?
     */
    protected static bool $unguarded = false;

    /**
     * Constructor
     */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->syncOriginal();
        $this->fill($attributes);
    }

    /**
     * Model sınıfı başına boot kontrolü
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
     * Sınıf başına global scope ekleme
     */
    public static function addGlobalScope(ScopeInterface $scope): void
    {
        $class = static::class;
        static::$globalScopes[$class][get_class($scope)] = $scope;
    }

    /**
     * Global scope'ları query builder'a uygular
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
     */
    public function getTable(): string
    {
        if (!empty($this->table)) {
            return $this->table;
        }

        $className = class_basename(static::class);
        return strtolower($className) . 's';
    }

    /**
     * Primary key sütun adını döndürür
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Primary key değerini döndürür
     */
    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Primary key değerini set eder
     */
    public function setKey(mixed $value): self
    {
        $this->setAttribute($this->getKeyName(), $value);
        return $this;
    }

    /**
     * Database bağlantısını döndürür
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
     */
    public function setConnection(Connection $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Query builder'ı Model-aware yapıyor
     */
    public function newQuery(): Builder
    {
        $builder = new Builder($this->getConnection());
        $builder->setModel($this)->from($this->getTable());

        return $this->applyGlobalScopes($builder);
    }

    /**
     * Yeni query builder oluşturur (global scope'suz)
     */
    public function newQueryWithoutScopes(): Builder
    {
        $builder = new Builder($this->getConnection());
        return $builder->setModel($this)->from($this->getTable());
    }

    /**
     * Yeni model instance oluşturur
     */
    public function newInstance(array $attributes = [], bool $exists = false): static
    {
        $model = new static($attributes);
        $model->exists = $exists;
        $model->setConnection($this->getConnection());
        
        return $model;
    }

    /**
     * Model array'ini Collection'a çevirir
     */
    public function newCollection(array $models): Collection
    {
        return new Collection($models);
    }

    /**
     * ✅ İYİLEŞTİRİLDİ: Model'i attribute'larla doldurur (advanced validation)
     */
    public function fill(array $attributes): self
    {
        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            // ✅ YENİ: Advanced validation
            if (!empty($this->rules) && isset($this->rules[$key])) {
                $this->validateAttribute($key, $value);
            }
            
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * ✅ YENİ: Single attribute validation
     */
    protected function validateAttribute(string $key, mixed $value): void
    {
        $rules = $this->rules[$key] ?? [];
        
        if (empty($rules)) {
            return;
        }

        foreach ((array) $rules as $rule) {
            if (!$this->validateRule($key, $value, $rule)) {
                $message = $this->messages["{$key}.{$rule}"] ?? "Invalid value for {$key}";
                throw new \InvalidArgumentException($message);
            }
        }
    }

    /**
     * ✅ YENİ: Rule validation
     */
    protected function validateRule(string $key, mixed $value, string $rule): bool
    {
        return match ($rule) {
            'required' => !is_null($value) && $value !== '',
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'numeric' => is_numeric($value),
            'integer' => is_int($value) || (is_string($value) && ctype_digit($value)),
            'string' => is_string($value),
            'boolean' => is_bool($value),
            default => $this->validateComplexRule($key, $value, $rule),
        };
    }

    /**
     * ✅ YENİ: Complex rule validation (min:8, max:50, etc.)
     */
    protected function validateComplexRule(string $key, mixed $value, string $rule): bool
    {
        if (str_contains($rule, ':')) {
            [$ruleName, $parameter] = explode(':', $rule, 2);
            
            return match ($ruleName) {
                'min' => is_string($value) && strlen($value) >= (int) $parameter,
                'max' => is_string($value) && strlen($value) <= (int) $parameter,
                'in' => in_array($value, explode(',', $parameter), true),
                'unique' => $this->validateUniqueRule($key, $value, $parameter),
                default => true,
            };
        }
        
        return true;
    }

    /**
     * ✅ YENİ: Unique rule validation
     */
    protected function validateUniqueRule(string $key, mixed $value, string $table): bool
    {
        $query = $this->newQueryWithoutScopes()
            ->where($key, '=', $value);
        
        // Exclude current model if updating
        if ($this->exists) {
            $query->where($this->getKeyName(), '!=', $this->getKey());
        }
        
        return !$query->exists();
    }

    /**
     * Guard bypass ederek doldurur
     */
    public function forceFill(array $attributes): self
    {
        return static::unguarded(function () use ($attributes) {
            return $this->fill($attributes);
        });
    }

    /**
     * Mass assignment için fillable attribute'ları döndürür
     */
    protected function fillableFromArray(array $attributes): array
    {
        // Unguarded ise hepsini al
        if (static::$unguarded) {
            return $attributes;
        }

        // Fillable ve guarded boşsa hata fırlat
        if (count($this->fillable) === 0 && $this->guarded === ['*']) {
            throw MassAssignmentException::fillableNotSet(static::class);
        }

        // Whitelist modu
        if (count($this->fillable) > 0) {
            return array_intersect_key(
                $attributes,
                array_flip($this->fillable)
            );
        }

        // Blacklist modu
        return array_diff_key(
            $attributes,
            array_flip($this->guarded)
        );
    }

    /**
     * Attribute fillable mı kontrol eder
     */
    public function isFillable(string $key): bool
    {
        if (static::$unguarded) {
            return true;
        }

        if (in_array($key, $this->fillable, true)) {
            return true;
        }

        if ($this->isGuarded($key)) {
            return false;
        }

        return empty($this->fillable) && !$this->isGuarded($key);
    }

    /**
     * Attribute guarded mı kontrol eder
     */
    public function isGuarded(string $key): bool
    {
        return in_array($key, $this->guarded, true) || $this->guarded === ['*'];
    }

    /**
     * Mass assignment guard'ı geçici olarak devre dışı bırakır
     */
    public static function unguard(bool $state = true): void
    {
        static::$unguarded = $state;
    }

    /**
     * Mass assignment guard'ı yeniden aktif eder
     */
    public static function reguard(): void
    {
        static::$unguarded = false;
    }

    /**
     * Guard bypass ederek callback çalıştırır
     */
    public static function unguarded(callable $callback): mixed
    {
        $previous = static::$unguarded;
        static::unguard();

        try {
            return $callback();
        } finally {
            static::$unguarded = $previous;
        }
    }

    /**
     * Orjinal attribute'ları senkronize eder
     */
    public function syncOriginal(): self
    {
        $this->original = $this->attributes;
        $this->clearAttributeCache();
        return $this;
    }

    /**
     * Tek bir attribute'u senkronize eder
     */
    public function syncOriginalAttribute(string $key): self
    {
        $this->original[$key] = $this->attributes[$key] ?? null;
        return $this;
    }

    /**
     * Model veya belirli attribute'lar değişti mi?
     */
    public function isDirty(array|string|null $attributes = null): bool
    {
        return $this->hasChanges(
            $this->getDirty(),
            is_array($attributes) ? $attributes : func_get_args()
        );
    }

    /**
     * Model veya attribute'lar temiz mi?
     */
    public function isClean(array|string|null $attributes = null): bool
    {
        return !$this->isDirty(...func_get_args());
    }

    /**
     * Son save'den sonra değişti mi?
     */
    public function wasChanged(array|string|null $attributes = null): bool
    {
        return $this->hasChanges(
            $this->changes,
            is_array($attributes) ? $attributes : func_get_args()
        );
    }

    /**
     * Değişen attribute'ları döndürür
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original)) {
                $dirty[$key] = $value;
            } elseif ($value !== $this->original[$key] &&
                      !$this->originalIsEquivalent($key, $value)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Son save'deki değişiklikleri döndürür
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Original değer eşdeğer mi kontrol eder
     */
    protected function originalIsEquivalent(string $key, mixed $current): bool
    {
        if (!array_key_exists($key, $this->original)) {
            return false;
        }

        $original = $this->original[$key];

        // Cast edilmiş değerleri karşılaştır
        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $original) === $current;
        }

        // Numeric karşılaştırma
        if (is_numeric($original) && is_numeric($current)) {
            return (string) $original === (string) $current;
        }

        return false;
    }

    /**
     * Belirli attribute'larda değişiklik var mı kontrol eder
     */
    protected function hasChanges(array $changes, array $attributes = []): bool
    {
        if (empty($attributes)) {
            return count($changes) > 0;
        }

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $changes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Değişiklikleri kalıcı hale getirir
     */
    protected function syncChanges(): self
    {
        $this->changes = $this->getDirty();
        return $this;
    }

    /**
     * ✅ İYİLEŞTİRİLDİ: Model'i veritabanına kaydeder (EVENT SUPPORT)
     */
    public function save(): bool
    {
        // ✅ YENİ: Fire saving event
        if (!$this->fireModelEvent('saving')) {
            return false;
        }

        // Timestamps'i güncelle
        if ($this->usesTimestamps() && ($this->isDirty() || !$this->exists)) {
            $this->updateTimestamps();
        }

        // Değişiklik yoksa direkt return
        if ($this->exists && !$this->isDirty()) {
            return true;
        }

        // INSERT veya UPDATE
        if ($this->exists) {
            // ✅ YENİ: Fire updating event
            if (!$this->fireModelEvent('updating')) {
                return false;
            }
            
            $saved = $this->performUpdate();
            
            if ($saved) {
                // ✅ YENİ: Fire updated event
                $this->fireModelEvent('updated');
            }
        } else {
            // ✅ YENİ: Fire creating event
            if (!$this->fireModelEvent('creating')) {
                return false;
            }
            
            $saved = $this->performInsert();
            
            if ($saved) {
                // ✅ YENİ: Fire created event
                $this->fireModelEvent('created');
            }
        }

        if ($saved) {
            $this->syncChanges();
            $this->syncOriginal();
            
            // ✅ YENİ: Fire saved event
            $this->fireModelEvent('saved');
        }

        return $saved;
    }

    /**
     * INSERT işlemi yapar
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
     */
    protected function performUpdate(): bool
    {
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
     */
    protected function getAttributesForInsert(): array
    {
        return $this->attributes;
    }

    /**
     * ✅ İYİLEŞTİRİLDİ: Model'i veritabanından siler (EVENT SUPPORT)
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        // ✅ YENİ: Fire deleting event
        if (!$this->fireModelEvent('deleting')) {
            return false;
        }

        $deleted = $this->newQuery()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->delete();

        if ($deleted > 0) {
            $this->exists = false;
            
            // ✅ YENİ: Fire deleted event
            $this->fireModelEvent('deleted');
            
            return true;
        }

        return false;
    }

    /**
     * Model'i veritabanından yeniler
     */
    public function refresh(): self
    {
        if (!$this->exists) {
            return $this;
        }

        $fresh = $this->newQuery()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->firstModel();

        if ($fresh) {
            $this->setRawAttributes($fresh->getAttributes(), true);
            $this->relations = [];
            $this->clearAttributeCache();
        }

        return $this;
    }

    /**
     * Veritabanından yeni instance döndürür (refresh alternatifi)
     */
    public function fresh(array|string $with = []): ?static
    {
        if (!$this->exists) {
            return null;
        }

        $query = $this->newQuery();

        if (!empty($with)) {
            $query->with(is_array($with) ? $with : func_get_args());
        }

        return $query->where($this->getKeyName(), '=', $this->getKey())->firstModel();
    }

    /**
     * Model'i çoğaltır
     */
    public function replicate(array $except = []): static
    {
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

        $instance = $this->newInstance();
        $instance->fill($attributes);
        $instance->exists = false;

        return $instance;
    }

    /**
     * Model'i array'e çevirir
     */
    public function toArray(): array
    {
        $attributes = $this->attributesToArray();

        // Relations ekle
        $attributes = array_merge($attributes, $this->relationsToArray());

        // Visible/hidden uygula
        if (!empty($this->visible)) {
            $attributes = array_intersect_key($attributes, array_flip($this->visible));
        }

        if (!empty($this->hidden)) {
            $attributes = array_diff_key($attributes, array_flip($this->hidden));
        }

        return $attributes;
    }

    /**
     * Relations'ları array'e çevirir - Collection syntax düzeltildi
     */
    protected function relationsToArray(): array
    {
        $relations = [];

        foreach ($this->relations as $key => $value) {
            if ($value instanceof Collection) {
                // Laravel-style magic property syntax kullanıyor
                $relations[$key] = $value->map->toArray;
            } elseif ($value instanceof Model) {
                $relations[$key] = $value->toArray();
            } else {
                $relations[$key] = $value;
            }
        }

        return $relations;
    }

    /**
     * Model'i JSON'a çevirir
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | $options);
    }

    /**
     * Database satırından yeni model oluşturur
     */
    public function newFromBuilder(array $attributes): static
    {
        $instance = $this->newInstance([], true);
        $instance->setRawAttributes($attributes, true);

        return $instance;
    }

    /**
     * ✅ İYİLEŞTİRİLDİ: Yeni model oluşturur ve kaydeder (EVENT SUPPORT)
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save(); // Events fire edilir
        return $model;
    }

    /**
     * Static: Primary key ile model bulur
     */
    public static function find(mixed $id, array $columns = ['*']): ?static
    {
        return static::query()
            ->where((new static)->getKeyName(), '=', $id)
            ->select(...$columns)
            ->firstModel();
    }

    /**
     * Static: Model bulamazsa exception fırlatır
     */
    public static function findOrFail(mixed $id, array $columns = ['*']): static
    {
        $model = static::find($id, $columns);

        if (is_null($model)) {
            throw (new ModelNotFoundException)
                ->setModel(static::class)
                ->setIds([$id]);
        }

        return $model;
    }

    /**
     * Static: Tüm modelleri döndürür
     */
    public static function all(array $columns = ['*']): Collection
    {
        return static::query()->select(...$columns)->getModels();
    }

    /**
     * Static: Query builder oluşturur
     */
    public static function query(): Builder
    {
        return (new static)->newQuery();
    }

    /**
     * Magic static method forwarding
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Magic instance method forwarding
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Scope kontrol et
        $scopeMethod = 'scope' . ucfirst($method);
        if (method_exists($this, $scopeMethod)) {
            return $this->newQuery()->scope($method, $parameters);
        }

        // Query builder'a forward et
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
        unset($this->original[$key]);
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}