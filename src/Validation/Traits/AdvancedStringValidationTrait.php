<?php

declare(strict_types=1);

namespace Zephyr\Validation\Traits;

/**
 * Gelişmiş String Doğrulama Özellikleri için Trait
 *
 * @package Framework\Core\Validation
 * @subpackage Traits
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
trait AdvancedStringValidationTrait
{
    /**
     * Metinde Türkçe karakter kontrolü yapar
     *
     * @param string $text Kontrol edilecek metin
     * @param bool $allowSpaces Boşluk karakterine izin verilsin mi?
     * @return bool
     */
    public function hasTurkishChars(string $text, bool $allowSpaces = true): bool
    {
        // Türkçe karakterler
        $turkishChars = ['ç', 'Ç', 'ğ', 'Ğ', 'ı', 'İ', 'ö', 'Ö', 'ş', 'Ş', 'ü', 'Ü'];

        // Boşluk kontrolü
        if ($allowSpaces) {
            $text = str_replace(' ', '', $text);
        }

        // Her bir karakteri kontrol et
        foreach (mb_str_split($text) as $char) {
            if (in_array($char, $turkishChars, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Metni Türkçe karakterlerden arındırır
     *
     * @param string $text Dönüştürülecek metin
     * @return string Türkçe karaktersiz metin
     */
    public function normalizeTurkishChars(string $text): string
    {
        $turkishChars = [
            'ç' => 'c', 'Ç' => 'C',
            'ğ' => 'g', 'Ğ' => 'G',
            'ı' => 'i', 'İ' => 'I',
            'ö' => 'o', 'Ö' => 'O',
            'ş' => 's', 'Ş' => 'S',
            'ü' => 'u', 'Ü' => 'U'
        ];

        return strtr($text, $turkishChars);
    }

    /**
     * Alan adı (domain) formatını doğrular
     *
     * @param string $domain Alan adı
     * @param bool $allowSubdomain Alt alan adına izin verilsin mi?
     * @return bool
     */
    public function isValidDomain(string $domain, bool $allowSubdomain = true): bool
    {
        // Alan adı formatı için regex desenleri
        $patterns = [
            // Alt alan adı olmadan
            'base' => '/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)\.(?!-)[A-Za-z0-9-]{1,63}(?<!-)$/',

            // Alt alan adı ile
            'subdomain' => '/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)\.[A-Za-z0-9-]{1,63}(?<!-)\.(?!-)[A-Za-z0-9-]{1,63}(?<!-)$/'
        ];

        // Küçük harfe çevir ve boşlukları temizle
        $domain = trim(strtolower($domain));

        // Protokol varsa çıkar
        $domain = preg_replace('/^(https?:\/\/)?(www\.)?/', '', $domain);

        // Port numarası varsa çıkar
        $domain = preg_replace('/:.*$/', '', $domain);

        // İzin verilen pattern'i seç
        $pattern = $allowSubdomain ? $patterns['subdomain'] : $patterns['base'];

        // DNS kontrolü
        if (preg_match($pattern, $domain)) {
            // Geçerli TLD (Top-Level Domain) kontrolleri
            $tlds = [
                'com', 'org', 'net', 'edu', 'gov', 'mil', 'int',
                'tr', 'com.tr', 'org.tr', 'net.tr', 'edu.tr', 'gov.tr',
                'info', 'biz', 'name', 'us', 'uk', 'de', 'fr', 'eu'
            ];

            // Domain sonundaki TLD'yi al
            $parts = explode('.', $domain);
            $tld = $allowSubdomain ? $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1] : $parts[count($parts) - 1];

            return in_array($tld, $tlds);
        }

        return false;
    }

    /**
     * Alan adından alt alan adını çıkarır
     *
     * @param string $domain Alan adı
     * @return string|null Alt alan adı
     */
    public function extractSubdomain(string $domain): ?string
    {
        // Protokol ve www'yi çıkar
        $domain = preg_replace('/^(https?:\/\/)?(www\.)?/', '', $domain);

        // Port numarası varsa çıkar
        $domain = preg_replace('/:.*$/', '', $domain);

        $parts = explode('.', $domain);

        // En az 3 parçalı bir domain mi?
        return count($parts) > 2 ? $parts[0] : null;
    }

    /**
     * Telefon numarası için gelişmiş format kontrolleri
     *
     * @param string $phoneNumber Telefon numarası
     * @param string $country Ülke kodu (default: TR)
     * @param bool $strict Sıkı kontrol
     * @return bool
     */
    public function advancedPhoneValidation(
        string $phoneNumber,
        string $country = 'TR',
        bool $strict = true
    ): bool {
        // Telefon numarası formatları
        $phoneFormats = [
            'TR' => [
                // Cep telefonu formatları
                '/^(05|5)[0-9]{9}$/',  // Standart GSM
                '/^\+90[5][0-9]{9}$/', // Uluslararası format
                '/^[5][0-9]{9}$/'      // Ülke kodu olmadan
            ],
            'US' => [
                '/^(\+1|1)?[2-9]\d{2}[2-9]\d{2}\d{4}$/',  // ABD formatı
                '/^\(\d{3}\)\s*\d{3}[-\s]?\d{4}$/'        // Alternatif format
            ]
        ];

        // Boşluk, tire, parantez gibi karakterleri temizle
        $cleanNumber = preg_replace('/\s+|-|\(|\)/', '', $phoneNumber);

        // Ülke destekleniyor mu?
        if (!isset($phoneFormats[$country])) {
            return false;
        }

        // Format kontrolleri
        foreach ($phoneFormats[$country] as $pattern) {
            if (preg_match($pattern, $cleanNumber)) {
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
     * @return string Normalize edilmiş telefon numarası
     */
    public function normalizePhoneNumber(string $phoneNumber, string $country = 'TR'): string
    {
        // Boşluk, tire, parantez gibi karakterleri temizle
        $cleanNumber = preg_replace('/\s+|-|\(|\)/', '', $phoneNumber);

        return match($country) {
            'TR' => str_starts_with($cleanNumber, '90')
                ? $cleanNumber
                : (str_starts_with($cleanNumber, '0')
                    ? '90' . substr($cleanNumber, 1)
                    : '90' . $cleanNumber),
            'US' => preg_match('/^\+1/', $cleanNumber)
                ? $cleanNumber
                : (str_starts_with($cleanNumber, '1')
                    ? '+' . $cleanNumber
                    : '+1' . $cleanNumber),
            default => $cleanNumber
        };
    }
}