# Validation Katmanı

TypeScript'in Zod kütüphanesinden ilham alan, PHP 8.2+ için güçlü tipli ve akıcı bir doğrulama sistemi.

## 🌟 Özellikler

- Zod benzeri, akıcı (fluent) API ile şema tanımlama
- Tip güvenli (type-safe) validasyon
- Zengin veri tipi desteği (string, number, date, boolean, array, object vb.)
- Custom validation kuralları ekleme imkanı
- Çapraz alan doğrulama (cross-field validation)
- Koşullu doğrulama kuralları
- Özelleştirilebilir hata mesajları
- CQRS (Command ve Query) entegrasyonu
- Domain-Driven Design ile uyumlu
- Validation önbellekleme (caching) desteği
- Gelişmiş tip validasyonları (UUID, IBAN, Credit Card vb.)
- Interface bazlı genişletilebilir yapı

## 📂 Dizin Yapısı

```plaintext
Validation/
├── Contracts/
│   ├── ValidationSchemaInterface.php
│   ├── ValidationTypeInterface.php
│   └── ValidationResultInterface.php
├── SchemaType/
│   ├── AdvancedStringType.php
│   ├── ArrayType.php
│   ├── BaseType.php
│   ├── BooleanType.php
│   ├── CreditCardType.php
│   ├── DateType.php
│   ├── IbanType.php
│   ├── NumberType.php
│   ├── ObjectType.php
│   ├── StringType.php
│   └── UuidType.php
├── Traits/
│   ├── AdvancedStringValidationTrait.php
│   ├── AdvancedValidationTrait.php
│   ├── ConditionalValidationTrait.php
│   ├── IpValidationTrait.php
│   ├── PaymentValidationTrait.php
│   ├── PhoneValidationTrait.php
│   ├── SecurityFilterTrait.php
│   └── UuidValidationTrait.php
├── ValidationResult.php
└── ValidationSchema.php
```

## 🚀 Temel Kullanım

### 1. Şema Tanımlama

```php
// Basit bir şema tanımlama
$userSchema = ValidationSchema::make()
    ->shape([
        'name' => ValidationSchema::make()->string()
            ->required()
            ->min(3)
            ->max(50)
            ->setLabel('Ad Soyad'),
        
        'email' => ValidationSchema::make()->string()
            ->required()
            ->email()
            ->setLabel('E-posta'),
        
        'age' => ValidationSchema::make()->number()
            ->integer()
            ->min(18)
            ->setLabel('Yaş'),
        
        'role' => ValidationSchema::make()->string()
            ->oneOf(['user', 'admin', 'editor'])
            ->default('user')
            ->setLabel('Rol')
    ]);
```

### 2. Veri Doğrulama

```php
// Veriyi doğrulama
$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25,
    'role' => 'admin'
];

$result = $userSchema->validate($data);

if ($result->hasErrors()) {
    // Hata durumu
    $errors = $result->getErrors();
    foreach ($errors as $field => $fieldErrors) {
        foreach ($fieldErrors as $error) {
            echo "{$field}: {$error}\n";
        }
    }
} else {
    // Başarılı
    $validData = $result->getValidData();
    // İşlemlere devam et
}
```

### 3. Önbellekleme Kullanımı

```php
// Aynı veri yapısı için tekrarlanan doğrulamalarda performans artışı
// Örneğin yüksek trafikli API'lerde
$cachedResult = $userSchema->validateWithCache($data);
```

### 4. Özel Hata Mesajları

```php
$schema = ValidationSchema::make()
    ->shape([
        'email' => ValidationSchema::make()->string()
            ->required()
            ->email()
            ->setLabel('E-posta')
            ->errorMessage('required', 'E-posta alanı boş bırakılamaz')
            ->errorMessage('email', 'Lütfen geçerli bir e-posta adresi giriniz')
    ]);
```

### 5. Çapraz Alan Doğrulama

```php
$passwordSchema = ValidationSchema::make()
    ->shape([
        'password' => ValidationSchema::make()->string()
            ->required()
            ->min(8)
            ->password(),
        
        'password_confirm' => ValidationSchema::make()->string()
            ->required()
    ]);

// İki şifre alanının eşleşmesi için çapraz alan doğrulama
$passwordSchema->crossValidate(function ($data) {
    if ($data['password'] !== $data['password_confirm']) {
        throw new \Exception('Şifreler eşleşmiyor');
    }
});
```

### 6. Koşullu Doğrulama

```php
$orderSchema = ValidationSchema::make()
    ->shape([
        'payment_type' => ValidationSchema::make()->string()
            ->oneOf(['credit_card', 'bank_transfer', 'cash'])
            ->required()
    ]);

// Ödeme tipi kredi kartı ise, kart bilgileri zorunlu olmalı
$orderSchema->when('payment_type', 'credit_card', function ($schema) {
    return $schema->shape([
        'card_number' => ValidationSchema::make()->string()->required(),
        'expiry_date' => ValidationSchema::make()->string()->required(),
        'cvv' => ValidationSchema::make()->string()->required()
    ]);
});
```

## 🧩 Veri Tipleri

ValidationSchema, çeşitli veri tipleri için özel doğrulama sınıfları sunar:

### StringType

```php
ValidationSchema::make()->string()
    ->required()
    ->min(3)           // Minimum uzunluk
    ->max(50)          // Maksimum uzunluk
    ->regex('/^\w+$/')  // Regex deseni
    ->email()          // E-posta formatı
    ->url()            // URL formatı
    ->oneOf(['a', 'b', 'c']) // İzin verilen değerler
    ->password([       // Şifre kuralları
        'min_length' => 8,
        'require_uppercase' => true,
        'require_numeric' => true
    ]);
```

### NumberType

```php
ValidationSchema::make()->number()
    ->required()
    ->min(0)       // Minimum değer
    ->max(100)     // Maksimum değer
    ->integer();   // Tamsayı kontrolü
```

### BooleanType

```php
ValidationSchema::make()->boolean()
    ->required();
```

### DateType

```php
ValidationSchema::make()->date()
    ->required()
    ->min('2020-01-01')   // Minimum tarih
    ->max('2022-12-31')   // Maksimum tarih
    ->format('Y-m-d');    // Tarih formatı
```

### ArrayType

```php
ValidationSchema::make()->array()
    ->required()
    ->min(1)        // Minimum eleman sayısı
    ->max(10)       // Maksimum eleman sayısı
    ->elements(     // Eleman şeması
        ValidationSchema::make()->string()->required()
    );
```

### ObjectType

```php
ValidationSchema::make()->object()
    ->required()
    ->shape([       // Alt alanlar
        'id' => ValidationSchema::make()->number()->required(),
        'name' => ValidationSchema::make()->string()->required()
    ]);
```

### Özel Tipler

```php
// UUID
ValidationSchema::make()->uuid()
    ->required()
    ->version(4);  // UUID versiyonu

// IBAN
ValidationSchema::make()->iban()
    ->required()
    ->country('TR');  // Ülke kodu

// Kredi Kartı
ValidationSchema::make()->creditCard()
    ->required()
    ->type('visa');  // Kart tipi

// Gelişmiş String
ValidationSchema::make()->advancedString()
    ->required()
    ->turkishChars(true)  // Türkçe karakter kontrolü
    ->domain(true);       // Domain kontrolü
```

## 🔄 CQRS Entegrasyonu

Validation katmanı, CQRS (Command Query Responsibility Segregation) pattern'i ile entegre edilmiştir.

### Command Validation

```php
// CreateUserCommand.php
class CreateUserCommand extends AbstractCommand
{
    public function __construct(
        protected string $email,
        protected string $name,
        protected string $password,
        protected ?string $role = 'user'
    ) {
        $this->initialize();
    }
    
    protected function buildValidationSchema(): ValidationSchemaInterface
    {
        return ValidationSchema::make()
            ->shape([
                'email' => ValidationSchema::make()->string()
                    ->required()
                    ->email()
                    ->setLabel('E-posta'),
                
                'name' => ValidationSchema::make()->string()
                    ->required()
                    ->min(3)
                    ->max(50)
                    ->setLabel('Ad Soyad'),
                
                'password' => ValidationSchema::make()->string()
                    ->required()
                    ->min(8)
                    ->password()
                    ->setLabel('Şifre'),
                
                'role' => ValidationSchema::make()->string()
                    ->oneOf(['user', 'admin', 'editor'])
                    ->setLabel('Rol')
            ]);
    }
}
```

### Query Validation

```php
// GetUserQuery.php
class GetUserQuery extends AbstractQuery
{
    public function __construct(
        protected int|string|null $id = null,
        protected ?string $email = null
    ) {
        if ($id === null && $email === null) {
            throw new \InvalidArgumentException('Either id or email must be provided');
        }
    }
    
    protected function buildValidationSchema(): ValidationSchemaInterface
    {
        return ValidationSchema::make()
            ->shape([
                'id' => ValidationSchema::make()->number()
                    ->integer(),
                
                'email' => ValidationSchema::make()->string()
                    ->email()
            ]);
    }
}
```

### Auto Validation in CommandBus/QueryBus

CommandBus ve QueryBus sınıfları, dispatch işlemi sırasında otomatik olarak validation yapar:

```php
// Command gönderme
try {
    $command = new CreateUserCommand(
        email: 'john@example.com',
        name: 'John Doe',
        password: 'secure-password',
        role: 'user'
    );
    
    $commandBus->dispatch($command);
} catch (CommandValidationException $e) {
    // Validation hatası
    $errors = $e->getErrors();
    // Hataları göster
}
```

## 🔧 Gelişmiş Özellikler

### Türkçe Karakter İşlemleri

```php
// Türkçe karakter validasyonu
ValidationSchema::make()->advancedString()
    ->turkishChars(true) // Türkçe karakter içermeli
    ->validate();

// Normalizasyon
$text = "Türkçe karakterler: çğıöşü";
$normalized = $validation->normalizeTurkishChars($text);
// Sonuç: "Turkce karakterler: cgiosu"
```

### Domain Doğrulama

```php
ValidationSchema::make()->advancedString()
    ->domain(true) // Geçerli domain olmalı
    ->validate();

// Alt domain çıkarma
$subdomain = $validation->extractSubdomain('blog.example.com');
// Sonuç: "blog"
```

### Telefon Numarası Doğrulama

```php
// Türkiye telefon formatı kontrolü
$isValid = $validation->advancedPhoneValidation('5301234567', 'TR');

// Telefon numarası normalizasyonu
$normalized = $validation->normalizePhoneNumber('530 123 45 67', 'TR');
// Sonuç: "905301234567"
```

## 📝 Best Practices

1. **Semantik alan etiketleri kullanın**

   ```php
   $schema->string()->setLabel('E-posta');
   ```

2. **Özel hata mesajları ekleyin**

   ```php
   $schema->string()->errorMessage('email', 'Lütfen geçerli bir e-posta adresi giriniz');
   ```

3. **Performans için önbellekleme kullanın**

   ```php
   // Yüksek trafikli API'lerde
   $result = $schema->validateWithCache($data);
   
   // Önbellek sınırını ayarlama
   $schema->setCacheLimit(100);
   ```

4. **Karmaşık nesneler için ayrı şemalar tanımlayın**

   ```php
   $addressSchema = ValidationSchema::make()
       ->shape([
           'street' => ValidationSchema::make()->string()->required(),
           'city' => ValidationSchema::make()->string()->required(),
           'country' => ValidationSchema::make()->string()->required()
       ]);
   
   $userSchema = ValidationSchema::make()
       ->shape([
           'name' => ValidationSchema::make()->string()->required(),
           'email' => ValidationSchema::make()->string()->required()->email(),
           'address' => $addressSchema
       ]);
   ```

5. **Interface bazlı genişletme**

   ```php
   // Özel validation tipi oluşturma
   class MyCustomType extends BaseType implements ValidationTypeInterface
   {
      // ...
   }
   ```

## 🧠 Teknik Detaylar

### Interface Hiyerarşisi

- `ValidationSchemaInterface`: Şema tanımlama ve doğrulama işlemleri için
- `ValidationTypeInterface`: Veri tipi doğrulama metotları için
- `ValidationResultInterface`: Doğrulama sonuçları yönetimi için

### Trait Yapıları

- `AdvancedValidationTrait`: Koşullu ve çapraz alan doğrulamaları
- `ConditionalValidationTrait`: Koşullu validasyon ve önbellekleme
- `AdvancedStringValidationTrait`: Metin işleme ve doğrulama özellikleri
- Diğer özel validasyon trait'leri (UuidValidation, PhoneValidation vb.)

### CQRS Entegrasyonu

Validation sistemi, CQRS (Command-Query Responsibility Segregation) mimarisi ile tam entegre edilmiştir:

1. Command ve Query sınıfları, validasyon şeması tanımlama yeteneğine sahiptir
2. CommandBus ve QueryBus, dispatch işlemi sırasında validasyon yapar
3. Validation hataları, CommandValidationException ve QueryValidationException olarak fırlatılır

### Hata Yönetimi

ValidationResult sınıfı, tüm hataları organize bir şekilde yönetir:

- Alan bazlı hata gruplama
- İlk hata mesajını alma
- Hataları düzleştirilmiş listede görüntüleme
- Belirli alanların hatalarını filtreleme

## 🤝 Katkıda Bulunma

1. Bu repository'yi fork edin
2. Feature branch'i oluşturun (`git checkout -b feature/amazing-validation`)
3. Değişikliklerinizi commit edin (`git commit -m 'feat: Add amazing feature'`)
4. Branch'inizi push edin (`git push origin feature/amazing-validation`)
5. Pull Request oluşturun