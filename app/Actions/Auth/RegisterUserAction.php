<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
//use App\Events\UserRegistered; // <-- Olay sistemimizi kullanıyoruz
use Zephyr\Logging\LogManager;
use Zephyr\Events\EventDispatcher;

/**
 * Action: RegisterUserAction
 *
 * Bu sınıfın SADECE TEK BİR görevi vardır:
 * Yeni bir kullanıcıyı kaydetmek.
 */
class RegisterUserAction
{
    // Bağımlılıkları (DI) constructor ile alıyoruz
    public function __construct(
        private readonly LogManager $log,
        private EventDispatcher     $events
    ) {
    }

    /**
     * Eylemi çalıştırır.
     *
     * @param array $validatedData Controller'dan gelen doğrulanmış veri
     * @return User Oluşturulan kullanıcı modeli
     * @throws \Exception
     */
    public function execute(array $validatedData): User
    {
        try {
            $this->log->info('RegisterUserAction: Çalıştırılıyor...');

            // 1. İş Mantığı (Model Oluşturma)
            // 'password' alanı User modelindeki setPasswordAttribute
            // mutator'ı tarafından otomatik hash'lenecektir.
            $user = User::create($validatedData);

            // 2. İş Mantığı (Olay Tetikleme)
            // Artık e-posta gönderme vb. işleri burası BİLMİYOR.
            // Sadece olay fırlatıyor.
            // $this->events->dispatch(new UserRegistered($user));
            // Not: UserRegistered event'i henüz oluşturulmadı, bu yüzden şimdilik yorum satırı.
            // Önce `php zephyr make:event UserRegistered` çalıştırılmalı.

            $this->log->info('RegisterUserAction: Başarılı', ['user_id' => $user->id]);

            return $user;

        } catch (\Throwable $e) {
            $this->log->error('RegisterUserAction: Hata', [
                'error' => $e->getMessage(),
                'data' => $validatedData
            ]);

            // Controller'ın yakalaması için hatayı tekrar fırlat
            throw new \RuntimeException('Kayıt eylemi sırasında bir hata oluştu.');
        }
    }
}