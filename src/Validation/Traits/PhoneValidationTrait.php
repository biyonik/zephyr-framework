<?php

declare(strict_types=1);

namespace Zephyr\Validation\Traits;

/**
 * Telefon numarası doğrulama özellikleri için trait
 *
 * @package Framework\Core\Validation
 * @subpackage Traits
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
trait PhoneValidationTrait
{
    /**
     * Telefon numarası formatını doğrular
     *
     * @param string $phoneNumber Telefon numarası
     * @param string $country Ülke kodu (default: TR)
     * @return bool
     */
    public function isValidPhoneNumber(string $phoneNumber, string $country = 'TR'): bool
    {
        // Ülke bazlı telefon numarası desenleri
        $patterns = [
            'TR' => [
                // Türkiye için GSM ve sabit hat formatları
                '/^(05|5)[0-9]{9}$/',  // Cep telefonu
                '/^0(216|212|224|242|246|256|258|262|264|266|312|322|324|332|352|354|362|364|366|368|382|392|412|416|422|424|432|442|452|462|472|482|484|486|488|504|506|552|554|555|556|561|562|563|564|565|566|567|568|569)[0-9]{7}$/' // Sabit hat
            ],
            'US' => [
                '/^(\+1|1)?[2-9]\d{2}[2-9]\d{2}\d{4}$/'
            ],
            'GB' => [
                '/^(\+44\s?7\d{3}|\(?07\d{3}\)?)\s?\d{3}\s?\d{3}$/'
            ]
        ];

        // Ülke kontrolleri
        if (!isset($patterns[$country])) {
            return false;
        }

        // Her bir desen için kontrol
        foreach ($patterns[$country] as $pattern) {
            if (preg_match($pattern, preg_replace('/\s+|-|\(|\)/', '', $phoneNumber))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Telefon numarasını standart formata dönüştürür
     *
     * @param string $phoneNumber Telefon numarası
     * @param string $country Ülke kodu (default: TR)
     * @return string Düzenlenmiş telefon numarası
     */
    public function normalizePhoneNumber(string $phoneNumber, string $country = 'TR'): string
    {
        // Ülkeye özgü normalizasyon kuralları
        $normalized = preg_replace('/\s+|-|\(|\)/', '', $phoneNumber);

        return match ($country) {
            'TR' => preg_match('/^05/', $normalized) ? $normalized : '05' . substr($normalized, -9),
            'US' => preg_match('/^\+1|^1/', $normalized)
                ? $normalized
                : '+1' . $normalized,
            default => $normalized
        };
    }
}