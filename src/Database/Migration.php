<?php

declare(strict_types=1);

namespace Zephyr\Database;

use Zephyr\Database\Schema\Builder as SchemaBuilder;

/**
 * Migration Base Class
 *
 * Tüm migration'ların miras aldığı temel sınıf.
 * Database şeması değişikliklerini yönetir.
 *
 * Her migration iki metot içerir:
 * - up(): İleriye doğru değişiklik (tablo oluştur, sütun ekle)
 * - down(): Geriye doğru değişiklik (rollback)
 *
 * Kullanım:
 * class CreateUsersTable extends Migration {
 *     public function up(): void {
 *         $this->schema->create('users', function (Blueprint $table) {
 *             $table->id();
 *             $table->string('name');
 *             $table->timestamps();
 *         });
 *     }
 *
 *     public function down(): void {
 *         $this->schema->dropIfExists('users');
 *     }
 * }
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
abstract class Migration
{
    /**
     * PDO connection
     */
    protected \PDO $pdo;

    /**
     * Schema builder
     */
    protected SchemaBuilder $schema;

    /**
     * Constructor
     *
     * Database bağlantısını ve schema builder'ı hazırlar.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $connection = app(Connection::class);
        $this->pdo = $connection->getPdo();
        $this->schema = new SchemaBuilder($connection);
    }

    /**
     * Migration'ı uygular (ileri)
     *
     * Tablo oluşturma, sütun ekleme gibi işlemler.
     *
     * @return void
     */
    abstract public function up(): void;

    /**
     * Migration'ı geri alır (rollback)
     *
     * up() metodunun tersini yapar.
     *
     * @return void
     */
    abstract public function down(): void;
}