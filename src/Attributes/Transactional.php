<?php

declare(strict_types=1);

namespace Zephyr\Attributes;

use Attribute;

/**
 * Transactional Attribute
 *
 * Bir metotu veritabanı transaction'ı içinde çalıştırır.
 * Metot başarılıysa COMMIT, hata fırlatırsa ROLLBACK yapar.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Transactional
{
    public function __construct()
    {
        // Şimdilik parametresiz
    }
}