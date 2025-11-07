<?php

declare(strict_types=1);

/**
 * Event/Listener (Olay/Dinleyici) Eşleşmeleri
 *
 * Burada, hangi olayın (key) hangi dinleyicileri (value)
 * tetikleyeceğini tanımlarsınız.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Örnek Event/Listener Eşleşmesi
    |--------------------------------------------------------------------------
    |
    | 'php zephyr make:event UserRegistered'
    | 'php zephyr make:listener SendWelcomeEmail'
    |
    | komutları ile sınıfları oluşturduktan sonra buraya ekleyin:
    |
    */

    // \App\Events\UserRegistered::class => [
    //     \App\Listeners\SendWelcomeEmail::class,
    //     \App\Listeners\UpdateUserStatistics::class,
    // ],

];