<?php

declare(strict_types=1);

namespace Zephyr\Auth;

use Zephyr\Auth\Contracts\Authenticatable;
use Zephyr\Exceptions\Http\ForbiddenException;
use Zephyr\Support\Config;

/**
 * Authorization Gate (Yetkilendirme Geçidi)
 *
 * Bir kullanıcının belirli bir eylemi gerçekleştirip gerçekleştiremeyeceğini
 * (yetkisi olup olmadığını) belirler.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class Gate
{
    /**
     * Tanımlanan tüm yetkiler (abilities).
     * @var array<string, callable>
     */
    protected array $abilities = [];

    /**
     * Oturumu açmış olan ve veritabanından çekilmiş
     * User Model'ini önbelleğe alır (istek başına).
     */
    protected ?Authenticatable $user = null;

    /**
     * @param AuthManager $auth Kimlik doğrulama yöneticisi
     * @param class-string $userModel Yetkilendirilecek User modelinin sınıf adı
     */
    public function __construct(
        private AuthManager $auth,
        private string $userModel
    ) {
    }

    /**
     * Yeni bir yetki (ability) tanımlar.
     *
     * @param string $ability Yetkinin adı (örn: 'update-post')
     * @param callable $callback (User $user, mixed ...$args) => bool
     */
    public function define(string $ability, callable $callback): void
    {
        $this->abilities[$ability] = $callback;
    }

    /**
     * Kullanıcının bu yetkiye sahip olup olmadığını kontrol eder.
     *
     * @param string $ability Kontrol edilecek yetki
     * @param array $arguments Kontrol callback'ine gönderilecek ekstra argümanlar (örn: Post modeli)
     * @return bool
     */
    public function allows(string $ability, array $arguments = []): bool
    {
        // 1. Oturumu açmış kullanıcı modelini al
        $user = $this->getUser();
        if (!$user) {
            return false; // Misafir (guest) kullanıcıların hiçbir yetkisi yoktur
        }

        // 2. Yetki (ability) tanımlanmış mı?
        if (!isset($this->abilities[$ability])) {
            // Tanımlanmamış bir yetki, otomatik olarak reddedilir
            return false;
        }

        // 3. Callback'i (fonksiyonu) çağır
        $callback = $this->abilities[$ability];

        // Argümanların başına $user nesnesini ekle
        $arguments = array_merge([$user], $arguments);

        // Callback'ten dönen sonucu (true/false) döndür
        return (bool) $callback(...$arguments);
    }

    /**
     * Kullanıcının bu yetkiye sahip *olmadığını* kontrol eder.
     */
    public function denies(string $ability, array $arguments = []): bool
    {
        return !$this->allows($ability, $arguments);
    }

    /**
     * Kullanıcının bu yetkiye sahip olup olmadığını kontrol eder.
     * Eğer sahip değilse, otomatik olarak 403 ForbiddenException fırlatır.
     *
     * Bu, Controller'lar için ana kullanım metodudur.
     *
     * @param string $ability
     * @param mixed $arguments (Tekil bir model veya argüman dizisi olabilir)
     * @throws ForbiddenException
     */
    public function authorize(string $ability, mixed $arguments = []): void
    {
        // Argümanları her zaman dizi haline getir
        if (!is_array($arguments)) {
            $arguments = [$arguments];
        }

        if ($this->denies($ability, $arguments)) {
            // Standart 403 HTTP Hatası fırlat
            throw new ForbiddenException('Bu eylemi gerçekleştirmek için yetkiniz yok.');
        }
    }

    /**
     * Oturumu açmış kullanıcının tam Model nesnesini
     * veritabanından alır ve önbelleğe alır.
     *
     * @return Authenticatable|null
     */
    protected function getUser(): ?Authenticatable
    {
        // 1. İstek (request) içinde zaten önbelleğe alındıysa döndür
        if ($this->user) {
            return $this->user;
        }

        // 2. AuthManager'dan JWT payload'ındaki 'sub' (ID) bilgisini al
        $userId = $this->auth->id();
        if (!$userId) {
            return null; // Kullanıcı giriş yapmamış
        }

        // 3. ID ile User modelini veritabanından çek
        // (config('auth.provider.model') -> \App\Models\User::class)
        $user = $this->userModel::find($userId);

        if (!$user instanceof Authenticatable) {
            return null;
        }

        // 4. Sonraki kontroller için (örn: authorize 2. kez çağrılırsa) önbelleğe al
        return $this->user = $user;
    }
}