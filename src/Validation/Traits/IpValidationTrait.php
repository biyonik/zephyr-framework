<?php

declare(strict_types=1);

namespace Zephyr\Validation\Traits;

/**
 * IP adresi doğrulama özellikleri için trait
 *
 * @package Framework\Core\Validation
 * @subpackage Traits
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
trait IpValidationTrait
{
    /**
     * IP adresini doğrular
     *
     * @param string $ip Doğrulanacak IP adresi
     * @param string $type IP tipi (v4, v6, veya her ikisi)
     * @return bool
     */
    public function isValidIp(string $ip, string $type = 'both'): bool
    {
        return match($type) {
            'v4' => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
            'v6' => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            'both' => filter_var($ip, FILTER_VALIDATE_IP) !== false,
            default => false
        };
    }

    /**
     * Private IP adresi kontrolü
     *
     * @param string $ip Kontrol edilecek IP adresi
     * @return bool
     */
    public function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}