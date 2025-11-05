<?php

namespace Zephyr\Validation\Traits;

use Zephyr\Validation\Config\SecurityConfig;

/**
 * @package Framework\Core\Validation
 * @subpackage Traits
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
trait SecurityFilterTrait
{
    /**
     * HTML etiketlerini temizler
     *
     * @param string $input Temizlenecek girdi
     * @param bool $allowedTags İzin verilen etiketler
     * @return string Temizlenmiş metin
     */
    public function stripHtmlTags(string $input, $allowedTags = []): string
    {
        // İzin verilen etiketleri string'e çevir
        $allowedTagsStr = is_array($allowedTags)
            ? implode('', array_map(fn($tag) => "<{$tag}>", $allowedTags))
            : '';

        // HTML etiketlerini temizle
        return strip_tags($input, $allowedTagsStr);
    }

    /**
     * XSS saldırılarına karşı girdiyi korur
     *
     * @param string $input Güvenli hale getirilecek girdi
     * @return string Güvenli çıktı
     */
    public function preventXss(string $input): string
    {
        // HTML özel karakterlerine dönüştür
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    /**
     * SQL Injection'ı engeller
     *
     * @param string $input Güvenli hale getirilecek girdi
     * @return string Güvenli girdi
     */
    public function preventSqlInjection(string $input): string
    {
        // Tehlikeli karakterleri temizle
        $input = preg_replace('/[\'";`()]/', '', $input);

        // SQL anahtar kelimelerini filtrele
        $sqlKeywords = [
            'SELECT', 'INSERT', 'UPDATE', 'DELETE',
            'DROP', 'UNION', 'EXEC', 'TRUNCATE'
        ];

        return preg_replace('/\b(' . implode('|', $sqlKeywords) . ')\b/i', '', $input);
    }

    /**
     * Girdiyi tamamen temizler
     *
     * @param string $input Temizlenecek girdi
     * @param array $options Temizleme seçenekleri
     * @return string Temizlenmiş girdi
     */
    public function sanitizeInput(string $input, array $options = []): string
    {
        $defaults = [
            'stripHtml' => true,
            'preventXss' => true,
            'preventSql' => true,
            'trim' => true,
            'lowercase' => false
        ];

        $settings = array_merge($defaults, $options);

        // Boşluk temizleme
        if ($settings['trim']) {
            $input = trim($input);
        }

        // HTML etiketlerini temizle
        if ($settings['stripHtml']) {
            $input = $this->stripHtmlTags($input);
        }

        // XSS önleme
        if ($settings['preventXss']) {
            $input = $this->preventXss($input);
        }

        // SQL Injection önleme
        if ($settings['preventSql']) {
            $input = $this->preventSqlInjection($input);
        }

        // Küçük harfe çevirme
        if ($settings['lowercase']) {
            $input = mb_strtolower($input, 'UTF-8');
        }

        return $input;
    }

    /**
     * Güvenli dosya adı oluşturur
     *
     * @param string $filename Orijinal dosya adı
     * @return string Güvenli dosya adı
     */
    public function sanitizeFilename(string $filename): string
    {
        // Türkçe karakterleri düzelt
        $filename = strtr($filename, [
            'ç' => 'c', 'Ç' => 'C',
            'ğ' => 'g', 'Ğ' => 'G',
            'ı' => 'i', 'İ' => 'I',
            'ö' => 'o', 'Ö' => 'O',
            'ş' => 's', 'Ş' => 'S',
            'ü' => 'u', 'Ü' => 'U'
        ]);

        // Dosya adından güvenli karakterler dışındakileri kaldır
        $filename = preg_replace([
            '/[^a-zA-Z0-9\-\_\.]/',  // Güvensiz karakterler
            '/\.{2,}/',               // Ardışık nokta
            '/^\./',                  // Başında nokta
            '/\.$/'                   // Sonunda nokta
        ], [
            '',
            '.',
            '',
            ''
        ], $filename);

        // Maksimum uzunluk
        return substr($filename, 0, 255);
    }

    /**
     * Unicode karakterlerini filtreler
     *
     * @param string $input Filtrelenecek girdi
     * @param array $allowedChars İzin verilen karakter setleri
     * @return string Filtrelenmiş girdi
     */
    public function filterUnicodeChars(string $input, array $allowedChars = []): string
    {
        // Varsayılan izinler
        $defaultAllowed = [
            'Latin' => true,
            'Cyrillic' => false,
            'Greek' => false,
            'Arabic' => false
        ];

        $allowedBlocks = array_merge($defaultAllowed, $allowedChars);

        // Unicode blok kontrolleri
        $filteredChars = preg_replace_callback('/./u', function($match) use ($allowedBlocks) {
            $char = $match[0];

            // Unicode blok tespiti
            $block = \IntlChar::getBlockCode(\IntlChar::ord($char));

            $blockNames = [
                \IntlChar::BLOCK_CODE_BASIC_LATIN => 'Latin',
                \IntlChar::BLOCK_CODE_LATIN_1_SUPPLEMENT => 'Latin',
                \IntlChar::BLOCK_CODE_CYRILLIC => 'Cyrillic',
                \IntlChar::BLOCK_CODE_GREEK => 'Greek',
                \IntlChar::BLOCK_CODE_ARABIC => 'Arabic'
            ];

            $blockName = $blockNames[$block] ?? 'Unknown';

            // Blok izni kontrolü
            return $allowedBlocks[$blockName] ? $char : '';
        }, $input);

        return $filteredChars;
    }

    /**
     * Emoji'leri filtreler
     *
     * @param string $input Filtrelenecek girdi
     * @param bool $remove Emoji'ler silinsin mi?
     * @return string Filtrelenmiş girdi
     */
    public function filterEmoji(string $input, bool $remove = true): string
    {
        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';

        return $remove
            ? preg_replace($emojiPattern, '', $input)
            : $input;
    }

    /**
     * Belirli bir karakter setine uygunluk kontrolü
     *
     * @param string $input Kontrol edilecek girdi
     * @param string $charSet Karakter seti
     * @return bool Uygunluk durumu
     */
    public function validateCharSet(string $input, string $charSet): bool
    {
        $pattern = SecurityConfig::CHAR_SETS[$charSet] ?? '/^.*$/';
        return preg_match($pattern, $input) === 1;
    }

    /**
     * Veri Maskeleme Metodları
     */

    /**
     * Kredi kartı numarasını maskeler
     *
     * @param string $cardNumber Kredi kartı numarası
     * @return string Maskelenmiş kart numarası
     */
    public function maskCreditCard(string $cardNumber): string
    {
        // Boşluk ve tireleri kaldır
        $cleanNumber = preg_replace('/\D/', '', $cardNumber);

        // Son 4 haneyi göster, geri kalanı maskele
        return str_repeat('*', strlen($cleanNumber) - 4) .
            substr($cleanNumber, -4);
    }

    /**
     * E-posta adresini maskeler
     *
     * @param string $email E-posta adresi
     * @return string Maskelenmiş e-posta
     */
    public function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;

        $username = $parts[0];
        $domain = $parts[1];

        // İlk 2 karakter ve son karakteri göster
        $maskedUsername = substr($username, 0, 2) .
            str_repeat('*', max(0, strlen($username) - 3)) .
            substr($username, -1);

        return $maskedUsername . '@' . $domain;
    }

    /**
     * Telefon numarasını maskeler
     *
     * @param string $phone Telefon numarası
     * @return string Maskelenmiş telefon
     */
    public function maskPhoneNumber(string $phone): string
    {
        // Tüm özel karakterleri kaldır
        $cleanPhone = preg_replace('/\D/', '', $phone);

        // Son 4 haneyi göster
        return str_repeat('*', max(0, strlen($cleanPhone) - 4)) .
            substr($cleanPhone, -4);
    }

    /**
     * Dosya Güvenliği Metodları
     */

    /**
     * Dosya türü doğrulaması
     *
     * @param string $filename Dosya adı
     * @param array $allowedExtensions İzin verilen uzantılar
     * @return bool Dosya güvenli mi?
     */
    public function validateFileType(string $filename, array $allowedExtensions = []): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Zararlı uzantıları engelle
        if (in_array($extension, SecurityConfig::DANGEROUS_EXTENSIONS, true)) {
            return false;
        }

        // İzin verilen uzantılar varsa kontrol et
        return empty($allowedExtensions) ||
            in_array($extension, $allowedExtensions, true);
    }

    /**
     * Dosya mime type kontrolü
     *
     * @param string $filepath Dosya yolu
     * @param array $allowedMimeTypes İzin verilen mime tipler
     * @return bool Dosya güvenli mi?
     */
    public function validateFileMimeType(string $filepath, array $allowedMimeTypes = []): bool
    {
        // Mime type tespiti
        $mimeType = mime_content_type($filepath);

        // Güvenli mime type listeleri
        $safeImageTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp'
        ];
        $safeDocTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        // Varsayılan güvenli mime type'lar
        $defaultSafeMimeTypes = array_merge($safeImageTypes, $safeDocTypes);

        // İzin verilen mime type'lar varsa onları kullan
        $safeMimeTypes = !empty($allowedMimeTypes)
            ? $allowedMimeTypes
            : $defaultSafeMimeTypes;

        return in_array($mimeType, $safeMimeTypes, true);
    }

    /**
     * Dosya boyutu kontrolü
     *
     * @param string $filepath Dosya yolu
     * @param int $maxSizeInMB Maksimum dosya boyutu (MB)
     * @return bool Dosya boyutu uygun mu?
     */
    public function validateFileSize(string $filepath, int $maxSizeInMB = 5): bool
    {
        $filesize = filesize($filepath);
        $maxSize = $maxSizeInMB * 1024 * 1024; // MB to Bytes

        return $filesize <= $maxSize;
    }
}