<?php

declare(strict_types=1);

use Zephyr\Database\Migration;
use Zephyr\Database\Schema\Blueprint; // <-- YENİ

/**
 * Migration: CreateDemoTable
 */
return new class extends Migration
{
    /**
     * Geçişi uygular.
     */
    public function up(): void
    {
        // Örnek:
        // $this->schema->create('users', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->string('email')->unique();
        //     $table->string('password');
        //     $table->timestamps();
        // });
        $this->schema->create('demo', function (Blueprint $table) {
            $table->id();
        });
    }

    /**
     * Geçişi geri alır.
     */
    public function down(): void
    {
        // Örnek:
        // $this->schema->dropIfExists('users');
    }
};
