<?php
declare(strict_types=1);

namespace Zephyr\Validation\SchemaType;

use Zephyr\Validation\ValidationResult;

/**
 * String tipinde alan için şema sınıfı
 *
 * @package Framework\Core\Validation
 * @subpackage SchemaType
 * @author [Ahmet ALTUN]
 * @version 1.0.0
 * @since 1.0.0
 */
class StringType extends BaseType
{
    protected ?string $label = null;
    /**
     * Minimum uzunluk
     *
     * @var int|null
     */
    private ?int $minLength = null;

    /**
     * Maksimum uzunluk
     *
     * @var int|null
     */
    private ?int $maxLength = null;

    /**
     * Eşleşmesi gereken regex deseni
     *
     * @var string|null
     */
    private ?string $pattern = null;

    /**
     * İzin verilen değerler listesi
     *
     * @var array|null
     */
    private ?array $allowedValues = null;

    /**
     * Varsayılan değer
     *
     * @var mixed
     */
    protected mixed $defaultValue = null;

    private ?array $passwordRules = null;

    private bool $nullable = false;

    public function setLabel($label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Şifre kurallarını ayarlar
     */
    public function password(array $rules = []): self
    {
        $this->passwordRules = array_merge([
            'min_length' => 8,
            'max_length' => 72,  // Bcrypt için güvenli maksimum uzunluk
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_numeric' => true,
            'require_special' => true,
            'special_chars' => '!@#$%^&*(),.?":{}|<>+-',
            'min_unique_chars' => 6,
            'max_repeating_chars' => 3,  // Maksimum tekrar eden karakter
            'disallow_common' => true,
            'disallow_keyboard_patterns' => true,
            'min_entropy' => 50,  // Minimum entropy (şifre karmaşıklığı)
            'similarity_threshold' => 0.7  // Benzerlik eşiği (0-1 arası)
        ], $rules);

        return $this;
    }

    /**
     * İki string arasındaki benzerliği hesaplar (Levenshtein mesafesi kullanarak)
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        $lev = levenshtein(strtolower($str1), strtolower($str2));
        $maxLen = max(strlen($str1), strlen($str2));
        return 1 - ($lev / $maxLen);
    }

    /**
     * Şifredeki entropy'i hesaplar
     */
    private function calculatePasswordEntropy(string $password): float
    {
        $length = strlen($password);
        $charPool = 0;

        if (preg_match('/[a-z]/', $password)) $charPool += 26;
        if (preg_match('/[A-Z]/', $password)) $charPool += 26;
        if (preg_match('/[0-9]/', $password)) $charPool += 10;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $charPool += 32;

        return $length * log($charPool, 2);
    }

    /**
     * Klavye düzeni sıralı karakterleri kontrol eder
     */
    private function hasKeyboardPattern(string $password): bool
    {
        $keyboardPatterns = [
            // QWERTY düzeni
            'qwerty', 'asdfgh', 'zxcvbn',
            // Sayısal sıra
            '123456', '654321',
            // Yaygın patterns
            'abc', 'cba', 'xyz'
        ];

        $loweredPass = strtolower($password);
        foreach ($keyboardPatterns as $pattern) {
            if (str_contains($loweredPass, $pattern) ||
                str_contains($loweredPass, strrev($pattern))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Karakterlerin tekrar sayısını kontrol eder
     */
    private function hasRepeatingChars(string $password, int $maxRepeats): bool
    {
        $chars = str_split($password);
        $consecutive = 1;
        $lastChar = null;

        foreach ($chars as $char) {
            if ($char === $lastChar) {
                $consecutive++;
                if ($consecutive > $maxRepeats) {
                    return true;
                }
            } else {
                $consecutive = 1;
            }
            $lastChar = $char;
        }
        return false;
    }

    /**
     * Minimum uzunluk belirler
     *
     * @param int $length Minimum uzunluk
     * @return $this Mevcut şema tipi
     */
    public function min(int $length): self
    {
        $this->minLength = $length;
        return $this;
    }

    /**
     * Maksimum uzunluk belirler
     *
     * @param int $length Maksimum uzunluk
     * @return $this Mevcut şema tipi
     */
    public function max(int $length): self
    {
        $this->maxLength = $length;
        return $this;
    }

    /**
     * Regex desenine uygunluk kontrolü
     *
     * @param string $pattern Regex deseni
     * @return $this Mevcut şema tipi
     */
    public function regex(string $pattern): self
    {
        $this->pattern = $pattern;
        return $this;
    }

    /**
     * E-posta formatı kontrolü
     *
     * @return $this Mevcut şema tipi
     */
    public function email(): self
    {
        $this->pattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
        return $this;
    }

    /**
     * URL formatı kontrolü
     *
     * @return $this Mevcut şema tipi
     */
    public function url(): self
    {
        $this->pattern = '/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/';
        return $this;
    }

    /**
     * İzin verilen değerler listesini belirler
     *
     * @param array $values İzin verilen değerler
     * @return $this
     */
    public function oneOf(array $values): self
    {
        $this->allowedValues = $values;
        return $this;
    }

    /**
     * Varsayılan değer atar
     *
     * @param mixed $value Varsayılan değer
     * @return $this
     */
    public function default(mixed $value): self
    {
        $this->defaultValue = $value;
        return $this;
    }

    public function nullable(): self
    {
        $this->nullable = true;
        return $this;
    }

    /**
     * String alanını doğrular
     *
     * @param string $field Alan adı
     * @param mixed $value Alan değeri
     * @param ValidationResult $result Doğrulama sonucu
     */
    public function validate(string $field, mixed $value, ValidationResult $result): void
    {
        // 1. Varsayılan değeri uygula
        if ($value === null && $this->defaultValue !== null) {
            $value = $this->defaultValue;
        }

        // 2. Zorunlu alan kontrolü (null VEYA boş string ise)
        if ($this->required && ($value === null || $value === '')) {
            $result->addError($field, ($this->label ?? $field) . " alanı zorunludur");
            return;
        }

        // 3. GÜVENLİK YAMASI: (report.md - Güvenlik Açığı #3)
        // Nullable (boş geçilebilir) kontrolü
        // Eğer değer null ise (ve zorunlu değilse, ki o kontrolü geçtik),
        // ve ->nullable() metodu çağrılmışsa, doğrulamayı burada durdur.
        if ($value === null && $this->nullable) {
            return;
        }

        // 4. Nullable değil ama hala null (zorunlu değil ve default yok)
        // StringType, null değerleri (eğer nullable değilse) kabul etmez.
        if ($value === null) {
            $result->addError($field, ($this->label ?? $field) . " alanı metin tipinde olmalıdır (null olamaz)");
            return;
        }
        
        // 5. Tip kontrolü (Artık null değil, string olmalı)
        if (!is_string($value)) {
            $result->addError($field, ($this->label ?? $field) . " alanı metin tipinde olmalıdır");
            return;
        }

        // --- Buradan sonra $value'nun 'string' olduğu garanti ---

        // Minimum uzunluk kontrolü
        if ($this->minLength !== null && strlen($value) < $this->minLength) {
            $label = $this->label ?? $field;
            $result->addError($field, "$label alanı en az {$this->minLength} karakter olmalıdır");
        }

        // Maksimum uzunluk kontrolü
        if ($this->maxLength !== null && strlen($value) > $this->maxLength) {
            $label = $this->label ?? $field;
            $result->addError($field, "$label alanı en fazla {$this->maxLength} karakter olmalıdır");
        }

        // Regex desenine uygunluk kontrolü
        if ($this->pattern !== null && !preg_match($this->pattern, $value)) {
            $label = $this->label ?? $field;
            $result->addError($field, "$label alanı belirtilen formatta değil");
        }

        // İzin verilen değerler kontrolü
        if ($this->allowedValues !== null && !in_array($value, $this->allowedValues)) {
            $result->addError($field, sprintf(
                "%s alanı için izin verilen değerler: %s",
                $this->label ?? $field,
                implode(', ', $this->allowedValues)
            ));
        }

        // Şifre kuralları kontrolü (sadece string doluysa çalışır)
        if ($this->passwordRules && $value !== '') {
            $this->validatePassword($value, $field, $result);
        }
    }

    private function validatePassword(string $value, string $field, ValidationResult $result): void
    {
        if (!$this->passwordRules) {
            return;
        }

        $rules = $this->passwordRules;
        $label = $this->label ?? $field;
        $errors = [];

        // Temel kontroller
        if (strlen($value) < $rules['min_length']) {
            $errors[] = "en az {$rules['min_length']} karakter uzunluğunda olmalıdır";
        }

        if (strlen($value) > $rules['max_length']) {
            $errors[] = "en fazla {$rules['max_length']} karakter uzunluğunda olmalıdır";
        }

        if ($rules['require_uppercase'] && !preg_match('/[A-Z]/', $value)) {
            $errors[] = "en az bir büyük harf içermelidir";
        }

        if ($rules['require_lowercase'] && !preg_match('/[a-z]/', $value)) {
            $errors[] = "en az bir küçük harf içermelidir";
        }

        if ($rules['require_numeric'] && !preg_match('/[0-9]/', $value)) {
            $errors[] = "en az bir rakam içermelidir";
        }

        if ($rules['require_special']) {
            $special_chars = preg_quote($rules['special_chars'], '/');
            if (!preg_match("/[$special_chars]/", $value)) {
                $errors[] = "en az bir özel karakter içermelidir ({$rules['special_chars']})";
            }
        }

        // Gelişmiş kontroller
        $unique_chars = count(array_unique(str_split($value)));
        if ($unique_chars < $rules['min_unique_chars']) {
            $errors[] = "en az {$rules['min_unique_chars']} farklı karakter içermelidir";
        }

        if ($rules['disallow_keyboard_patterns'] && $this->hasKeyboardPattern($value)) {
            $errors[] = "klavye düzeninde sıralı karakterler içeremez";
        }

        if ($this->hasRepeatingChars($value, $rules['max_repeating_chars'])) {
            $errors[] = "en fazla {$rules['max_repeating_chars']} adet tekrar eden karakter içerebilir";
        }

        // Entropy kontrolü
        $entropy = $this->calculatePasswordEntropy($value);
        if ($entropy < $rules['min_entropy']) {
            $errors[] = "yeterince karmaşık değil, lütfen daha güçlü bir şifre seçin";
        }

        // Yaygın şifreler kontrolü
        if ($rules['disallow_common']) {
            $common_passwords = [
                'password', '123456', 'qwerty', '111111', 'abc123',
                'letmein', 'admin', 'welcome', 'monkey', 'dragon'
            ];
            if (in_array(strtolower($value), $common_passwords)) {
                $errors[] = "çok yaygın bir şifre, lütfen daha güvenli bir şifre seçin";
            }
        }

        // Hataları ekle
        foreach ($errors as $error) {
            $result->addError($field, ($this->label ?? $field)." $error");
        }
    }
}