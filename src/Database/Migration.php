<?php

declare(strict_types=1);

namespace Zephyr\Database;

use Zephyr\Database\Connection;

/**
 * Temel Veritabanı Geçiş Sınıfı
 *
 * Tüm migration'lar bu sınıfı miras alır.
 * Şema değişiklikleri için 'up' ve 'down' metotlarını sağlar.
 */
abstract class Migration
{
    /**
     * Veritabanı bağlantısı
     */
    protected \PDO $pdo;

    /**
     * Constructor.
     * Container üzerinden veritabanı bağlantısını alır.
     */
    public function __construct()
    {
        // Connection sınıfından ham PDO nesnesini alıyoruz
        // çünkü DDL (CREATE, ALTER) işlemleri için bu daha uygundur.
        $this->pdo = app(Connection::class)->getPdo();
    }

    /**
     * Geçişi uygular (Veritabanını ileri alır).
     *
     * @return void
     */
    abstract public function up(): void;

    /**
     * Geçişi geri alır (Veritabanını geri alır).
     *
     * @return void
     */
    abstract public function down(): void;
}