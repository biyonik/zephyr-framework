<?php

declare(strict_types=1);

namespace Zephyr\Validation\Traits;

/**
 * Kredi kartı ve IBAN doğrulama özellikleri için trait
 *
 * @package Framework\Core\Validation
 * @subpackage Traits
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
trait PaymentValidationTrait
{
    /**
     * Kredi kartı numarasını Luhn algoritması ile doğrular
     *
     * @param string $cardNumber Kredi kartı numarası
     * @param string|null $cardType Kart tipi (optional)
     * @return bool
     */
    public function isValidCreditCard(string $cardNumber, ?string $cardType = null): bool
    {
        // Boşluk ve tire karakterlerini kaldır
        $number = preg_replace('/\D/', '', $cardNumber);

        // Kart tipi desenleri
        $patterns = [
            'visa'       => '/^4[0-9]{12}(?:[0-9]{3})?$/',
            'mastercard' => '/^5[1-5][0-9]{14}$/',
            'amex'       => '/^3[47][0-9]{13}$/',
            'discover'   => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
        ];

        // Kart tipi kontrolü
        if ($cardType && isset($patterns[$cardType])) {
            if (!preg_match($patterns[$cardType], $number)) {
                return false;
            }
        }

        // Luhn algoritması kontrolü
        $sum = 0;
        $length = strlen($number);
        $isEvenIndex = false;

        for ($i = $length - 1; $i >= 0; $i--) {
            $digit = (int)$number[$i];

            if ($isEvenIndex) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $isEvenIndex = !$isEvenIndex;
        }

        return ($sum % 10 == 0);
    }

    /**
     * IBAN numarasını doğrular
     *
     * @param string $iban IBAN numarası
     * @param string|null $countryCode Ülke kodu (optional)
     * @return bool
     */
    public function isValidIban(string $iban, ?string $countryCode = null): bool
    {
        // Boşluk ve tire karakterlerini kaldır
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));

        // Ülke bazlı IBAN uzunluk kontrolleri
        $countryLengths = [
            'TR' => 26, // Türkiye
            'DE' => 22, // Almanya
            'GB' => 22, // İngiltere
            'FR' => 27, // Fransa
            'IT' => 27, // İtalya
            'NL' => 18, // Hollanda
        ];

        // Ülke kodu kontrolü
        if ($countryCode) {
            if (!isset($countryLengths[$countryCode]) ||
                strlen($iban) !== $countryLengths[$countryCode]) {
                return false;
            }
        }

        // IBAN regex deseni
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{4,}$/', $iban)) {
            return false;
        }

        // IBAN doğrulama algoritması
        // 1. İlk 4 karakteri sona taşı
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);

        // 2. Harfleri sayılara çevir (A=10, B=11, ...)
        $converted = '';
        foreach (str_split($rearranged) as $char) {
            $converted .= ctype_alpha($char)
                ? (string)(ord($char) - 55)
                : $char;
        }

        // 3. Modulo 97 kontrolü
        return bcmod($converted, '97') === '1';
    }

    /**
     * Kredi kartı tipini tespit eder
     *
     * @param string $cardNumber Kredi kartı numarası
     * @return string|null Kart tipi
     */
    public function detectCreditCardType(string $cardNumber): ?string
    {
        $number = preg_replace('/\D/', '', $cardNumber);

        $patterns = [
            'visa'       => '/^4[0-9]{12}(?:[0-9]{3})?$/',
            'mastercard' => '/^5[1-5][0-9]{14}$/',
            'amex'       => '/^3[47][0-9]{13}$/',
            'discover'   => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $number)) {
                return $type;
            }
        }

        return null;
    }
}