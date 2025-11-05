<?php

declare(strict_types=1);

namespace Zephyr\Validation\Traits;

/**
 * UUID doğrulama özellikleri için trait
 *
 * @package Framework\Core\Validation
 * @subpackage Traits
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
trait UuidValidationTrait
{
    /**
     * UUID formatını doğrular
     *
     * @param string $uuid Doğrulanacak UUID
     * @param int $version UUID versiyonu (1-5 arası, default: tüm versiyonlar)
     * @return bool
     */
    protected function isValidUuid(string $uuid, int $version = 0): bool
    {
        // UUID regex desenleri
        $patterns = [
            1 => '/^[0-9a-f]{8}-[0-9a-f]{4}-1[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            2 => '/^[0-9a-f]{8}-[0-9a-f]{4}-2[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            3 => '/^[0-9a-f]{8}-[0-9a-f]{4}-3[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            4 => '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            5 => '/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        ];

        // Genel UUID formatı
        $generalPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

        // Versiyon kontrolü
        if ($version > 0) {
            return isset($patterns[$version]) &&
                preg_match($patterns[$version], $uuid) === 1;
        }

        // Genel UUID kontrolü
        return preg_match($generalPattern, $uuid) === 1;
    }
}