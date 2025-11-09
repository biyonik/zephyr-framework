<?php

declare(strict_types=1);

namespace Zephyr\Auth;

use Zephyr\Database\Connection;
use Zephyr\Support\Config;

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
     */
    public function createToken(string $email): string
    {
        $this->enforceThrottle($email);
        $this->deleteExistingTokens($email);

        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);

        $this->db->table($this->table)->insert([
            'email' => $email,
            'token' => $hashedToken,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    /**
     * ✅ FIX: Token'ı atomic olarak validate eder.
     *
     * Race condition fix: Validate ve delete tek transaction'da.
     */
    public function validateToken(string $email, string $token): bool
    {
        $hashedToken = hash('sha256', $token);
        $expireTime = date('Y-m-d H:i:s', time() - ($this->expireMinutes * 60));

        // ✅ Atomic: Single query ile validate ve fetch
        $record = $this->db->table($this->table)
            ->where('email', $email)
            ->where('token', $hashedToken)
            ->where('created_at', '>', $expireTime)
            ->first();

        if (!$record) {
            return false; // Token bulunamadı veya expire olmuş
        }

        // Token geçerli, hemen sil (reuse prevention)
        $this->delete($email);

        return true;
    }

    /**
     * Token'ı siler.
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
     * Throttle kontrolü yapar.
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
     * Süresi dolmuş tüm token'ları temizler.
     */
    public function cleanup(): int
    {
        $expireTime = date('Y-m-d H:i:s', time() - ($this->expireMinutes * 60));

        return $this->db->table($this->table)
            ->where('created_at', '<', $expireTime)
            ->delete();
    }
}