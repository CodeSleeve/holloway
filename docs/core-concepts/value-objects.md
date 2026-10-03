# Working with Value Objects

Value Objects are small,immutable objects that represent a conceptual whole defined by their values rather than an identity. In domain-driven design, they're fundamental building blocks that make your domain model more expressive and type-safe.

## Table of Contents

- [What is a Value Object?](#what-is-a-value-object)
- [Reference Implementation Value Objects](#reference-implementation-value-objects)
  - [Email Value Object](#email-value-object)
  - [Money Value Object](#money-value-object-using-moneyphpmoney)
- [Integrating with Holloway](#integrating-with-holloway)

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

## Next Steps

- **[Type Transformations](./type-transformations.md)** - Integrate value objects with mappers
- **[Entity Hydration](./entity-hydration.md)** - How value objects fit into hydration
