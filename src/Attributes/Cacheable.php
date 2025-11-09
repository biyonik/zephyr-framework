<?php

declare(strict_types=1);

namespace Zephyr\Attributes;

use Attribute;

/**
 * Cacheable Attribute
 *
 * Bir metotun çıktısını otomatik olarak önbelleğe alır.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Cacheable
{
    /**
     * @param int $ttl Önbellek süresi (saniye)
     * @param string|null $key Önbellek anahtarı. Null ise, otomatik oluşturulur.
     */
    public function __construct(
        public int $ttl = 3600,
        public ?string $key = null
    ) {
    }
}