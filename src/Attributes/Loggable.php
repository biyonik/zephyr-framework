<?php

declare(strict_types=1);

namespace Zephyr\Attributes;

use Attribute;

/**
 * Loggable Attribute
 *
 * Bir metotun başlangıcını, bitişini veya hatasını otomatik olarak loglar.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Loggable
{
    /**
     * @param string $level 'info', 'debug', 'warning'
     * @param string|null $message Log mesajı. Null ise, otomatik oluşturulur.
     */
    public function __construct(
        public string $level = 'info',
        public ?string $message = null
    ) {
    }
}