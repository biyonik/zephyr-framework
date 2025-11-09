<?php

declare(strict_types=1);

namespace Zephyr\Auth;

use Zephyr\Database\Connection;
use Zephyr\Support\Config;

/**
 * Password Reset Manager
 *
 * Şifre sıfırlama token'larını yönetir (veritabanı tabanlı).
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class PasswordResetManager
{
    protected Connection $db;
    protected string $table = 'password_resets';
    protected int $expireMinutes;
    protected int $throttleSeconds;

    public function __construct(Connection $db)
    {
        $this->db = $db;
        $this->expireMinutes = Config::get('auth.passwords.expire', 60);
        $this->throttleSeconds = Config::get('auth.passwords.throttle', 60);
    }

    /**
     * Kullanıcı için yeni bir reset token oluşturur.
     *
     * @param string $email Kullanıcı e-posta adresi
     * @return string Token (bu e-posta ile gönderilir)
     */
    public function createToken(string $email): string
    {
        // 1. Throttle kontrolü (spam engelleme)
        $this->enforceThrottle($email);

        // 2. Eski token'ları sil
        $this->deleteExistingTokens($email);

        // 3. Yeni token oluştur (güvenli ve URL-safe)
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);

        // 4. Veritabanına kaydet
        $this->db->table($this->table)->insert([
            'email' => $email,
            'token' => $hashedToken,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 5. Plain token'ı döndür (e-posta ile gönderilecek)
        return $token;
    }

    /**
     * Token'ın geçerli olup olmadığını kontrol eder.
     *
     * @param string $email
     * @param string $token
     * @return bool
     */
    public function validateToken(string $email, string $token): bool
    {
        $hashedToken = hash('sha256', $token);

        $record = $this->db->table($this->table)
            ->where('email', $email)
            ->where('token', $hashedToken)
            ->first();

        if (!$record) {
            return false; // Token bulunamadı
        }

        // Süre kontrolü
        $createdAt = strtotime($record['created_at']);
        $expiresAt = $createdAt + ($this->expireMinutes * 60);

        if (time() > $expiresAt) {
            // Token süresi dolmuş, sil
            $this->delete($email);
            return false;
        }

        return true;
    }

    /**
     * Token'ı siler (şifre sıfırlama tamamlandığında).
     */
    public function delete(string $email): void
    {
        $this->db->table($this->table)
            ->where('email', $email)
            ->delete();
    }

    /**
     * Eski token'ları temizler.
     */
    protected function deleteExistingTokens(string $email): void
    {
        $this->delete($email);
    }

    /**
     * Throttle kontrolü yapar (spam engelleme).
     *
     * @throws \RuntimeException Çok sık istek yapılıyorsa
     */
    protected function enforceThrottle(string $email): void
    {
        $record = $this->db->table($this->table)
            ->where('email', $email)
            ->first();

        if ($record) {
            $createdAt = strtotime($record['created_at']);
            $canRequestAgainAt = $createdAt + $this->throttleSeconds;

            if (time() < $canRequestAgainAt) {
                $remainingSeconds = $canRequestAgainAt - time();
                throw new \RuntimeException(
                    "Lütfen {$remainingSeconds} saniye sonra tekrar deneyin."
                );
            }
        }
    }

    /**
     * Süresi dolmuş tüm token'ları temizler (maintenance).
     */
    public function cleanup(): int
    {
        $expireTime = date('Y-m-d H:i:s', time() - ($this->expireMinutes * 60));

        return $this->db->table($this->table)
            ->where('created_at', '<', $expireTime)
            ->delete();
    }
}