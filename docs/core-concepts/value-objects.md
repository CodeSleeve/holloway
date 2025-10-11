# Working with Value Objects

Value Objects are small,immutable objects that represent a conceptual whole defined by their values rather than an identity. In domain-driven design, they're fundamental building blocks that make your domain model more expressive and type-safe.

## Table of Contents

- [What is a Value Object?](#what-is-a-value-object)
- [Why Use Value Objects?](#why-use-value-objects)
- [Reference Implementation Value Objects](#reference-implementation-value-objects)
  - [Email Value Object](#email-value-object)
  - [Money Value Object](#money-value-object-using-moneyphpmoney)
  - [Address Value Object](#address-value-object)
  - [Date/DateTime Value Objects](#datetime-value-objects)
  - [Custom Notification Settings](#custom-notification-settings)
- [Integrating with Holloway](#integrating-with-holloway)
- [Common Patterns](#common-patterns)
- [Testing Value Objects](#testing-value-objects)
- [Benefits Summary](#benefits-summary)
- [Common Value Objects](#common-value-objects)

## What is a Value Object?

Consider the difference:

```php
// Primitive obsession - what does this string mean?
$email = "john@example.com";  // Could be anything!

// Value Object - explicit, validated, type-safe
$email = new Email("john@example.com");  // Can ONLY be a valid email
```

**Characteristics of Value Objects:**

- **Defined by their values** (not identity) - two Email("john@example.com") are identical
- **Immutable** - once created, cannot change
- **Self-validating** - throw exceptions for invalid values
- **No side effects** - pure functions only
- **Replaceable** - change by creating new instance, not modifying existing

## Why Use Value Objects?

### 1. Type Safety

```php
// Before: Anything goes
function sendEmail(string $to, string $from) { ... }
sendEmail("not an email", "also not an email"); // Compiles fine, fails at runtime

// After: Compiler enforces correctness
function sendEmail(Email $to, Email $from) { ... }
sendEmail($invalidString, $otherString); // Compiler error!
```

### 2. Validation Once, Trust Everywhere

```php
class Email
{
    public function __construct(string $value)
    {
        // Validate ONCE on construction
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Invalid email');
        }
        $this->value = $value;
    }
}

// Now you can trust it everywhere
function sendEmail(Email $email) {
    // No need to validate - if you have an Email object, it's valid!
    $this->mailer->send((string) $email);
}
```

### 3. Rich Domain Behavior

```php
$email = new Email("john@example.com");
$domain = $email->getDomain(); // "example.com"
$isGmail = $email->isGmail(); // false

$amount = Money::USD(100);
$doubled = $amount->multiply(2);
$formatted = $amount->format(); // "$100.00"
```

### 4. Expressive Code

```php
// Before: Unclear
$client->total_revenue = 50000; // Cents? Dollars? Currency?

// After: Crystal clear
$client->total_revenue = Money::USD(500); // Obviously $500 USD
```

## Reference Implementation Value Objects

### Email Value Object

```php
<?php

namespace App\ValueObjects;

use DomainException;
use JsonSerializable;

class Email implements JsonSerializable
{
    public readonly string $value;

    /**
     * @throws DomainException
     */
    public function __construct(string $value)
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('Invalid Email Address');
        }

        $this->value = $value;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function jsonSerialize(): mixed
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->jsonSerialize());
    }
    
    /**
     * Get email domain
     */
    public function getDomain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
    }
    
    /**
     * Check if Gmail address
     */
    public function isGmail(): bool
    {
        return $this->getDomain() === 'gmail.com';
    }
}
```

**Key features:**

- `readonly` property (PHP 8.1+) enforces immutability
- Validation in constructor
- `JsonSerializable` for easy JSON encoding
- `__toString()` for string casting
- Domain-specific methods (getDomain, isGmail)

### Money Value Object (using moneyphp/money)

The reference implementation uses the battle-tested `moneyphp/money` library:

```bash
composer require moneyphp/money
```

```php
use Money\Money;
use Money\Currency;

// Creating Money objects
$amount = new Money(50000, new Currency('USD')); // $500.00
$amount = Money::USD(50000); // Shorthand

// Arithmetic operations
$doubled = $amount->multiply(2);
$sum = $amount->add(Money::USD(10000));
$difference = $amount->subtract(Money::USD(5000));

// Comparisons
$isGreater = $amount->greaterThan(Money::USD(40000)); // true
$isEqual = $amount->equals(Money::USD(50000)); // true

// Formatting
$formatted = format_money($amount); // "$500.00"
```

**Helper function for hydration:**

```php
// App/Functions/ValueObject.php

function money(string|array|object|null $money): ?Money
{
    if (!$money) {
        return null;
    }

    if (is_string($money)) {
        $money = json_decode($money, true);
    }

    return new Money($money['amount'], new Currency($money['currency']));
}

function format_money(Money $money): string
{
    $amount = number_format($money->getAmount() / 100, 2);
    $currencyCode = $money->getCurrency()->getCode();

    $symbols = [
        'USD' => '$',
        'CAD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'CNY' => '¥',
    ];

    $symbol = $symbols[$currencyCode] ?? $currencyCode;

    return $symbol . $amount;
}
```

### Address Value Object

```php
<?php

namespace App\ValueObjects;

use JsonSerializable;

class Address extends ValueObject implements JsonSerializable
{
    public readonly string $full_address;
    
    public function __construct(
        public readonly Country $country,
        public readonly State $state,
        public readonly string $city,
        public readonly string $postal_code,
        public readonly string $street_address,
        public readonly ?string $latitude = null,
        public readonly ?string $longitude = null
    ) {
        // Computed property
        $this->full_address = "$this->street_address $this->city, $this->state $this->postal_code $this->country";
    }

    public function toArray(): array
    {
        return [
            'country'        => (string) $this->country,
            'state'          => (string) $this->state,
            'city'           => $this->city,
            'postal_code'    => $this->postal_code,
            'street_address' => $this->street_address,
            'latitude'       => $this->latitude,
            'longitude'      => $this->longitude,
            'full_address'   => $this->full_address
        ];
    }

    public function __toString(): string
    {
        return $this->full_address;
    }

    /**
     * Named constructor
     */
    public static function create(
        string $country, 
        string $state, 
        string $city, 
        string $postal_code, 
        string $street_address,
        ?string $latitude = null, 
        ?string $longitude = null
    ): self {
        $country = $country instanceof Country ? $country : Country::create($country);
        $state = $state instanceof State ? $state : State::create($state);

        return new static($country, $state, $city, $postal_code, $street_address, $latitude, $longitude);
    }
}
```

**Helper function for hydration:**

```php
function address(string|array|null $address): ?ValueObjects\Address
{
    if (!$address) {
        return null;
    }

    if (is_string($address)) {
        $address = json_decode($address, true);
    }

    if (
        !$address['country'] 
        || !$address['state'] 
        || !$address['city'] 
        || !$address['postal_code'] 
        || !$address['street_address']
    ) {
        return null;
    }

    return ValueObjects\Address::create(...Arr::except($address, ['full_address']));
}
```

### Date/DateTime Value Objects

The reference implementation uses `cakephp/chronos` for immutable date/time objects:

```bash
composer require cakephp/chronos
```

```php
use Cake\Chronos\Chronos;
use Cake\Chronos\Date;

// Creating dates
$date = Date::parse('2024-01-15');
$dateTime = Chronos::parse('2024-01-15 14:30:00');

// Immutable operations
$tomorrow = $date->addDay();
$nextWeek = $date->addWeek();
$formatted = $date->format('Y-m-d'); // "2024-01-15"

// Comparisons
$isPast = $date->isPast();
$isFuture = $date->isFuture();
$isToday = $date->isToday();

// Differences
$age = $birthDate->diffInYears(Chronos::now());
$daysUntil = Chronos::now()->diffInDays($futureDate);
```

**Helper functions:**

```php
function date(?string $date): ?Date
{
    return $date ? Date::parse($date) : null;
}

function date_time(null|string|DateTime $date): ?Chronos
{
    return $date ? Chronos::parse($date) : null;
}

function base_utc_date_time(?string $date): ?Chronos
{
    $timeZone = new DateTimeZone(date_default_timezone_get());
    return $date ? Chronos::parse($date, 'UTC')->setTimeZone($timeZone) : null;
}
```

### Custom Notification Settings

```php
class UserNotificationSettings
{
    public function __construct(
        public readonly bool $emailEnabled,
        public readonly bool $smsEnabled,
        public readonly bool $pushEnabled,
        public readonly array $channels,
        public readonly ?string $timezone
    ) {}
    
    public static function fromArray(array $data): self
    {
        return new self(
            emailEnabled: $data['email_enabled'] ?? true,
            smsEnabled: $data['sms_enabled'] ?? false,
            pushEnabled: $data['push_enabled'] ?? true,
            channels: $data['channels'] ?? ['email'],
            timezone: $data['timezone'] ?? null
        );
    }
    
    public function toArray(): array
    {
        return [
            'email_enabled' => $this->emailEnabled,
            'sms_enabled' => $this->smsEnabled,
            'push_enabled' => $this->pushEnabled,
            'channels' => $this->channels,
            'timezone' => $this->timezone,
        ];
    }
    
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
    
    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->channels);
    }
    
    public function withEmail(bool $enabled): self
    {
        return new self(
            emailEnabled: $enabled,
            smsEnabled: $this->smsEnabled,
            pushEnabled: $this->pushEnabled,
            channels: $this->channels,
            timezone: $this->timezone
        );
    }
}
```

**Helper function:**

```php
function user_notification_settings($settings): ValueObjects\UserNotificationSettings
{
    if (is_string($settings)) {
        $settings = json_decode($settings, true);
    }

    return ValueObjects\UserNotificationSettings::fromArray($settings);
}
```

## Integrating with Holloway

### Step 1: Register Transformation

```php
// In DataMapperServiceProvider

Mapper::setMaps([
    'email' => [
        'hydrate'   => fn($value) => email($value),
        'dehydrate' => fn($value) => (string) $value,
    ],
    
    'money' => [
        'hydrate'   => fn($value) => money($value),
        'dehydrate' => fn($value) => json($value),
    ],
    
    'address' => [
        'hydrate'   => fn($value) => address($value),
        'dehydrate' => fn($value) => json($value),
    ],
    
    'date' => [
        'hydrate'   => fn($value) => date($value),
        'dehydrate' => fn($value) => $value ? (string) $value : null,
    ],
    
    'user_notification_settings' => [
        'hydrate'   => fn($value) => user_notification_settings($value),
        'dehydrate' => fn($value) => json($value),
    ],
]);
```

### Step 2: Declare in Mapper

```php
class ClientMapper extends Mapper
{
    protected array $mappings = [
        'email' => 'email',
        'billing_address' => 'address',
        'total_revenue' => 'money',
        'outstanding_balance' => 'money',
        'date_of_birth' => 'date',
        'last_service_date' => 'date',
        'notification_settings' => 'user_notification_settings',
    ];
}
```

### Step 3: Use in Entity

```php
class Client extends Entity
{
    protected Email $email;
    protected ?Address $billing_address = null;
    protected Money $total_revenue;
    protected Money $outstanding_balance;
    protected ?Date $date_of_birth = null;
    protected ?Date $last_service_date = null;
    protected UserNotificationSettings $notification_settings;
    
    /**
     * Constructor for creating NEW clients
     */
    public function __construct(
        ClientCompany $company,
        string $first_name,
        string $last_name,
        Email $email
    ) {
        $this->tenant_id = $company->tenant_id;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->email = $email;
        
        // Sensible defaults
        $this->total_revenue = Money::USD(0);
        $this->outstanding_balance = Money::USD(0);
        $this->notification_settings = UserNotificationSettings::defaults();
    }
    
    /**
     * Domain methods using value objects
     */
    public function changeEmail(Email $newEmail): void
    {
        $this->email = $newEmail;
    }
    
    public function addRevenue(Money $amount): void
    {
        $this->total_revenue = $this->total_revenue->add($amount);
    }
    
    public function updateBillingAddress(Address $address): void
    {
        $this->billing_address = $address;
    }
    
    public function getAge(): ?int
    {
        if (!$this->date_of_birth) {
            return null;
        }
        
        return $this->date_of_birth->diffInYears(Date::now());
    }
    
    public function enableEmailNotifications(): void
    {
        $this->notification_settings = $this->notification_settings->withEmail(true);
    }
}
```

## Common Patterns

### Pattern 1: Readonly Properties (PHP 8.1+)

```php
class Email
{
    public readonly string $value;
    
    public function __construct(string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Invalid email');
        }
        
        $this->value = $value;
    }
}
```

**Benefits:**
- Compiler enforces immutability
- No need for private + getter
- Clear intent

### Pattern 2: Named Constructors

```php
class Money
{
    private function __construct(
        private int $cents,
        private string $currency
    ) {}
    
    public static function USD(int $dollars): self
    {
        return new self($dollars * 100, 'USD');
    }
    
    public static function fromCents(int $cents, string $currency): self
    {
        return new self($cents, $currency);
    }
    
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        return new self($data['cents'], $data['currency']);
    }
}

// Usage is clear and expressive
$price = Money::USD(100);
$saved = Money::fromCents(50000, 'USD');
```

### Pattern 3: Immutable Updates

```php
class NotificationSettings
{
    public function __construct(
        public readonly bool $emailEnabled,
        public readonly bool $smsEnabled
    ) {}
    
    // Return NEW instance, don't modify existing
    public function withEmail(bool $enabled): self
    {
        return new self(
            emailEnabled: $enabled,
            smsEnabled: $this->smsEnabled
        );
    }
}

// Usage
$settings = new NotificationSettings(true, false);
$updated = $settings->withEmail(false); // New instance!
// $settings unchanged
```

### Pattern 4: JSON Serialization

```php
class Address implements JsonSerializable
{
    public function jsonSerialize(): mixed
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
        ];
    }
    
    public function toJson(): string
    {
        return json_encode($this->jsonSerialize());
    }
    
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        return new self(
            $data['street'],
            $data['city'],
            $data['state'],
            $data['zip']
        );
    }
}

// Works seamlessly with json_encode/decode
$json = json_encode($address);
$restored = Address::fromJson($json);
```

### Pattern 5: Validation in Constructor

```php
class Email
{
    public function __construct(public readonly string $value)
    {
        // Validate immediately
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Invalid email address');
        }
        
        // Value object is ALWAYS valid after construction
    }
}

// Can never have invalid Email object
try {
    $email = new Email('not-an-email');
} catch (DomainException $e) {
    // Handle invalid input
}
```

## Testing Value Objects

```php
class EmailTest extends TestCase
{
    public function test_creates_valid_email()
    {
        $email = new Email('john@example.com');
        
        $this->assertEquals('john@example.com', $email->value);
        $this->assertEquals('john@example.com', (string) $email);
    }
    
    public function test_rejects_invalid_email()
    {
        $this->expectException(DomainException::class);
        
        new Email('not-an-email');
    }
    
    public function test_extracts_domain()
    {
        $email = new Email('john@example.com');
        
        $this->assertEquals('example.com', $email->getDomain());
    }
    
    public function test_json_serialization()
    {
        $email = new Email('john@example.com');
        
        $json = json_encode($email);
        $this->assertEquals('"john@example.com"', $json);
    }
}
```

```php
class MoneyTest extends TestCase
{
    public function test_creates_money()
    {
        $amount = Money::USD(100);
        
        $this->assertEquals(10000, $amount->getAmount()); // Cents
        $this->assertEquals('USD', $amount->getCurrency()->getCode());
    }
    
    public function test_arithmetic_operations()
    {
        $amount = Money::USD(100);
        
        $doubled = $amount->multiply(2);
        $this->assertEquals(Money::USD(200), $doubled);
        
        $sum = $amount->add(Money::USD(50));
        $this->assertEquals(Money::USD(150), $sum);
    }
    
    public function test_formatting()
    {
        $amount = Money::USD(12345); // $123.45
        
        $formatted = format_money($amount);
        $this->assertEquals('$123.45', $formatted);
    }
}
```

## Benefits Summary

### For Your Domain Model

- ✅ **Type safety** - compiler catches errors
- ✅ **Expressiveness** - code reads like business language
- ✅ **Encapsulation** - validation and behavior bundled together
- ✅ **Immutability** - no unexpected mutations

### For Your Team

- ✅ **Self-documenting** - `Email` is clearer than `string`
- ✅ **Reduced bugs** - validation happens once, trusted everywhere
- ✅ **Consistency** - same behavior everywhere
- ✅ **Testability** - easy to unit test in isolation

### For Your Application

- ✅ **Performance** - validate once, not on every use
- ✅ **Maintainability** - change in one place affects everywhere
- ✅ **Reliability** - impossible states are impossible to represent

## Common Value Objects

Here's a starter list of common value objects:

- `Email` - Email addresses
- `Money` - Currency amounts
- `Address` - Physical addresses
- `PhoneNumber` - Phone numbers
- `Date` / `DateTime` - Dates and times
- `Url` - URLs
- `Color` - RGB/Hex colors
- `Coordinates` - Latitude/Longitude
- `Range` - Min/Max ranges
- `Percentage` - Percentage values
- `Duration` - Time durations
- `Weight` / `Distance` - Measurements

## Next Steps

- **[Type Transformations](./type-transformations.md)** - Integrate value objects with mappers
- **[Entity Hydration](./entity-hydration.md)** - How value objects fit into hydration

## Further Reading

- Martin Fowler: [Value Object Pattern](https://martinfowler.com/bliki/ValueObject.html)
- Eric Evans: Domain-Driven Design (Value Objects chapter)
- MoneyPHP Library: [moneyphp/money](https://github.com/moneyphp/money)
- CakePHP Chronos: [cakephp/chronos](https://github.com/cakephp/chronos)
