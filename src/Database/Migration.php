<?php

declare(strict_types=1);

namespace Zephyr\Database;

use Zephyr\Database\Schema\Builder as SchemaBuilder;

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
     * @throws \Exception
     */
    public function __construct()
    {
        $connection = app(Connection::class); // <-- Bağlantıyı al
        $this->pdo = $connection->getPdo();

        // YENİ: Schema Builder'ı migration sınıfı için hazırla
        $this->schema = new SchemaBuilder($connection);
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