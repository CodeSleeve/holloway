# Type Transformation Patterns

One of the biggest challenges in the datamapper pattern is the **impedance mismatch** between your database schema (strings, integers, JSON) and your domain model (Email objects, Money objects, Dates).

Holloway gives you complete control over these transformations through the hydrate/dehydrate cycle. You can handle this in two ways:

1. **Shared mapping registry (optional)** – centralise hydrate/dehydrate callbacks and reference them from each mapper. This mirrors the approach many production teams use and the examples below demonstrate.
2. **Manual mapper transformations** – keep casting logic inside the `hydrate`/`dehydrate` methods of each mapper. Lightweight projects often start here.

Both approaches are valid. Pick the one that matches your team’s appetite for abstraction, and remember you can migrate from manual casts to the registry later without breaking your entities.

| Use this approach… | When it shines | Trade-offs |
| --- | --- | --- |
| **Shared mapping registry** | Large codebases, lots of shared value objects, teams that favour DRY abstractions. | Slight upfront setup, centralised defaults may feel opaque to new team members. |
| **Manual mapper transformations** | Small or experimental projects, unique casting rules per entity, teams that prefer explicit code. | Repetition across mappers, easier to forget an edge-case or new value object. |

## Table of Contents

- [The Problem](#the-problem)
- [Solution: The Mappings System](#solution-the-mappings-system)
- [Reference Implementation](#reference-implementation)
- [Real-World Examples](#real-world-examples)
  - [Example 1: Email Value Object](#example-1-email-value-object)
  - [Example 2: Money Value Object](#example-2-money-value-object)
  - [Example 3: Address Value Object](#example-3-address-value-object)
  - [Example 4: Date Transformations](#example-4-date-transformations)
  - [Example 5: PHP 8.1+ Enum Support](#example-5-php-81-enum-support)
  - [Example 6: Collection Transformations](#example-6-collection-transformations)
- [Custom Complex Transformations](#custom-complex-transformations)
- [Error Handling](#error-handling)
- [Benefits of This Pattern](#benefits-of-this-pattern)
- [Alternative: Manual Transformation](#alternative-manual-transformation)
- [Key Principles](#key-principles)

## The Problem

Your database stores primitive types:

```sql
CREATE TABLE clients (
    id INT,
    email VARCHAR(255),
    billing_address JSON,
    total_revenue JSON  -- {cents: 50000, currency: "USD"}
);
```

But your domain model uses rich value objects:

```php
class Client extends Entity
{
    protected Email $email;              // Not string!
    protected Address $billing_address;  // Not JSON!
    protected Money $total_revenue;      // Not JSON!
}
```

**How do you transform between them?**

## Solution: The Mappings System

This pattern uses a declarative mapping system that automatically transforms types during hydration and dehydration.

### Step 1: Register Type Transformations

In a service provider, register how each type should transform:

```php
// App/Providers/DataMapperServiceProvider.php

use App\Mappers\Mapper;

class DataMapperServiceProvider extends ServiceProvider
{
    public function register()
    {
        Mapper::setMaps([
            // Simple types
            'email' => [
                'hydrate'   => fn($value) => new Email($value),
                'dehydrate' => fn($value) => (string) $value,
            ],
            
            // Complex types
            'money' => [
                'hydrate'   => fn($value) => Money::fromJson($value),
                'dehydrate' => fn($value) => $value->toJson(),
            ],
            
            'address' => [
                'hydrate'   => fn($value) => Address::fromJson($value),
                'dehydrate' => fn($value) => $value->toJson(),
            ],
            
            'date' => [
                'hydrate'   => fn($value) => $value ? Chronos::parse($value) : null,
                'dehydrate' => fn($value) => $value ? (string) $value : null,
            ],
            
            // Collections
            'array' => [
                'hydrate'   => fn($value) => json_decode($value, true),
                'dehydrate' => fn($value) => json_encode($value),
            ],
            
            'collection' => [
                'hydrate'   => fn($value) => collect(json_decode($value, true)),
                'dehydrate' => fn($value) => json_encode($value),
            ],
            
            // Enums
            'status_enum' => [
                'hydrate'   => fn($value) => $value ? StatusEnum::from($value) : null,
                'dehydrate' => fn($value) => $value?->value,
            ],
        ]);
    }
}
```

### Step 2: Declare Mappings in Mapper

In your mapper, declare which properties use which transformations:

```php
class ClientMapper extends Mapper
{
    protected string $table = 'clients';
    protected string $entityClassName = Client::class;
    
    // This is where the magic happens!
    protected array $mappings = [
        'email' => 'email',
        'billing_address' => 'address',
        'total_revenue' => 'money',
        'date_of_birth' => 'date',
        'notification_settings' => 'json',
    ];
}
```

### Step 3: Entity Uses Value Objects

Your entity just declares the types it wants:

```php
class Client extends Entity
{
    protected Email $email;
    protected ?Address $billing_address = null;
    protected Money $total_revenue;
    protected ?Chronos $date_of_birth = null;
    protected array $notification_settings = [];
    
    // No transformation logic needed here!
}
```

### How It Works

When hydrating (loading from database):

```php
// Database has: {email: "john@example.com"}
$client = $clientMapper->find(1);

// Mapper sees 'email' in mappings array
// Looks up 'email' transformation
// Calls hydrate function: new Email("john@example.com")
// Result: $client->email is Email object!
```

When dehydrating (saving to database):

```php
// Entity has: Email object
$clientMapper->store($client);

// Mapper sees 'email' in mappings array
// Looks up 'email' transformation
// Calls dehydrate function: (string) $email
// Result: Database gets "john@example.com" string
```

## Reference Implementation

Here's the actual base Mapper from the reference implementation:

```php
abstract class Mapper extends HollowayMapper
{
    protected static array $maps = [];
    protected array $mappings = [];
    
    /**
     * Register a type transformation
     */
    public static function addMapp(string $name, callable $hydrate, callable $dehydrate)
    {
        static::$maps[$name] = compact('hydrate', 'dehydrate');
    }
    
    /**
     * Set all transformations at once
     */
    public static function setMaps(array $maps)
    {
        static::$maps = $maps;
    }
    
    /**
     * Hydrate: Database -> Entity
     */
    public function hydrate(stdClass $record, Collection $relations)
    {
        $attributes = $this->mapValueObjects($record, $relations);
        
        $entity = $this->instantiator->instantiate($this->entityClassName);
        
        return $entity->mapperFill($attributes);
    }
    
    /**
     * Dehydrate: Entity -> Database
     */
    public function dehydrate($entity): array
    {
        $attributes = $entity->toArray();
        
        // Remove relationships
        $attributes = Arr::except($attributes, 
            array_map(fn($rel) => $rel->getName(), $this->relationships)
        );
        
        // Remove null id for inserts
        if (!$attributes['id']) {
            unset($attributes['id']);
        }
        
        // Apply transformations
        foreach($this->mappings as $propertyName => $mapName) {
            if (isset(static::$maps[$mapName])) {
                $attributes[$propertyName] = call_user_func_array(
                    static::$maps[$mapName]['dehydrate'], 
                    [$attributes[$propertyName]]
                );
            }
        }
        
        return $attributes;
    }
    
    /**
     * Apply hydration transformations
     */
    protected function mapValueObjects(stdClass $record, Collection $relations): array
    {
        $attributes = array_merge((array) $record, $relations->all());
        
        // Apply transformations
        foreach($this->mappings as $propertyName => $mapName) {
            if (isset(static::$maps[$mapName])) {
                try {
                    $attributes[$propertyName] = call_user_func_array(
                        static::$maps[$mapName]['hydrate'], 
                        [$attributes[$propertyName]]
                    );
                } catch (\Throwable $th) {
                    throw new \Exception(
                        static::class . ": Unable to hydrate property $propertyName: " 
                        . $th->getMessage()
                    );
                }
            }
        }
        
        return $attributes;
    }
}
```

## Real-World Examples

### Example 1: Email Value Object

```php
// Value Object
class Email
{
    private string $value;
    
    public function __construct(string $email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        $this->value = strtolower($email);
    }
    
    public function __toString(): string
    {
        return $this->value;
    }
    
    public function getDomain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
    }
}

// Register transformation
Mapper::setMaps([
    'email' => [
        'hydrate'   => fn($value) => new Email($value),
        'dehydrate' => fn($value) => (string) $value,
    ],
]);

// Use in mapper
class UserMapper extends Mapper
{
    protected array $mappings = [
        'email' => 'email',
    ];
}

// Use in entity
class User extends Entity
{
    protected Email $email;
    
    public function changeEmail(Email $newEmail): void
    {
        $this->email = $newEmail;
    }
    
    public function getEmailDomain(): string
    {
        return $this->email->getDomain();
    }
}
```

### Example 2: Money Value Object

```php
// Value Object
class Money
{
    private int $cents;
    private string $currency;
    
    public function __construct(int $cents, string $currency = 'USD')
    {
        $this->cents = $cents;
        $this->currency = $currency;
    }
    
    public static function USD(int $dollars): self
    {
        return new self($dollars * 100, 'USD');
    }
    
    public static function fromJson(?string $json): ?self
    {
        if (!$json) return null;
        
        $data = json_decode($json, true);
        return new self($data['cents'], $data['currency']);
    }
    
    public function toJson(): string
    {
        return json_encode([
            'cents' => $this->cents,
            'currency' => $this->currency,
        ]);
    }
    
    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch');
        }
        
        return new self($this->cents + $other->cents, $this->currency);
    }
    
    public function format(): string
    {
        return '$' . number_format($this->cents / 100, 2);
    }
}

// Register transformation
Mapper::setMaps([
    'money' => [
        'hydrate'   => fn($value) => Money::fromJson($value),
        'dehydrate' => fn($value) => $value->toJson(),
    ],
]);

// Use in mapper
class ClientMapper extends Mapper
{
    protected array $mappings = [
        'total_revenue' => 'money',
        'outstanding_balance' => 'money',
    ];
}

// Use in entity
class Client extends Entity
{
    protected Money $total_revenue;
    protected Money $outstanding_balance;
    
    public function addRevenue(Money $amount): void
    {
        $this->total_revenue = $this->total_revenue->add($amount);
    }
}
```

### Example 3: Address Value Object

```php
// Value Object
class Address
{
    public function __construct(
        public string $street,
        public string $city,
        public string $state,
        public string $zip,
        public string $country = 'US'
    ) {}
    
    public static function fromJson(?string $json): ?self
    {
        if (!$json) return null;
        
        $data = json_decode($json, true);
        return new self(
            $data['street'],
            $data['city'],
            $data['state'],
            $data['zip'],
            $data['country'] ?? 'US'
        );
    }
    
    public function toJson(): string
    {
        return json_encode([
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'country' => $this->country,
        ]);
    }
    
    public function format(): string
    {
        return "{$this->street}\n{$this->city}, {$this->state} {$this->zip}";
    }
}

// Register transformation
Mapper::setMaps([
    'address' => [
        'hydrate'   => fn($value) => Address::fromJson($value),
        'dehydrate' => fn($value) => $value->toJson(),
    ],
]);

// Use in mapper
class ClientMapper extends Mapper
{
    protected array $mappings = [
        'billing_address' => 'address',
        'shipping_address' => 'address',
    ];
}
```

### Example 4: Date Transformations

```php
use Cake\Chronos\Chronos;

// Register transformation
Mapper::setMaps([
    'date' => [
        'hydrate'   => fn($value) => $value ? Chronos::parse($value) : null,
        'dehydrate' => fn($value) => $value ? $value->toDateString() : null,
    ],
    
    'date_time' => [
        'hydrate'   => fn($value) => $value ? Chronos::parse($value) : null,
        'dehydrate' => fn($value) => $value ? $value->toIso8601String() : null,
    ],
]);

// Use in mapper
class ClientMapper extends Mapper
{
    protected array $mappings = [
        'date_of_birth' => 'date',
        'last_service_date' => 'date',
        'created_at' => 'date_time',
        'updated_at' => 'date_time',
    ];
}

// Use in entity
class Client extends Entity
{
    protected ?Chronos $date_of_birth = null;
    protected ?Chronos $last_service_date = null;
    
    public function getAge(): int
    {
        if (!$this->date_of_birth) {
            throw new \Exception('Date of birth not set');
        }
        
        return $this->date_of_birth->diffInYears(Chronos::now());
    }
}
```

### Example 5: PHP 8.1+ Enum Support

```php
enum ClientStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
}

// Register transformation
Mapper::setMaps([
    'client_status_enum' => [
        'hydrate'   => fn($value) => $value ? ClientStatus::from($value) : null,
        'dehydrate' => fn($value) => $value?->value,
    ],
]);

// Use in mapper
class ClientMapper extends Mapper
{
    protected array $mappings = [
        'status' => 'client_status_enum',
    ];
}

// Use in entity
class Client extends Entity
{
    protected ClientStatus $status;
    
    public function activate(): void
    {
        $this->status = ClientStatus::Active;
    }
    
    public function isActive(): bool
    {
        return $this->status === ClientStatus::Active;
    }
}
```

### Example 6: Collection Transformations

```php
// Register transformation
Mapper::setMaps([
    'array' => [
        'hydrate'   => fn($value) => json_decode($value, true),
        'dehydrate' => fn($value) => json_encode($value),
    ],
    
    'collection' => [
        'hydrate'   => fn($value) => collect(json_decode($value, true)),
        'dehydrate' => fn($value) => json_encode($value),
    ],
]);

// Use in mapper
class UserMapper extends Mapper
{
    protected array $mappings = [
        'preferences' => 'array',
        'tags' => 'collection',
    ];
}

// Use in entity
class User extends Entity
{
    protected array $preferences = [];
    protected Collection $tags;
    
    public function setPreference(string $key, mixed $value): void
    {
        $this->preferences[$key] = $value;
    }
    
    public function addTag(string $tag): void
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->push($tag);
        }
    }
}
```

## Custom Complex Transformations

For complex entities with custom logic:

```php
// Complex value object
class NotificationSettings
{
    public function __construct(
        public bool $emailEnabled,
        public bool $smsEnabled,
        public array $channels,
        public ?string $timezone
    ) {}
    
    public static function fromJson(?string $json): ?self
    {
        if (!$json) {
            return self::defaults();
        }
        
        $data = json_decode($json, true);
        return new self(
            $data['email_enabled'] ?? true,
            $data['sms_enabled'] ?? false,
            $data['channels'] ?? [],
            $data['timezone'] ?? null
        );
    }
    
    public function toJson(): string
    {
        return json_encode([
            'email_enabled' => $this->emailEnabled,
            'sms_enabled' => $this->smsEnabled,
            'channels' => $this->channels,
            'timezone' => $this->timezone,
        ]);
    }
    
    public static function defaults(): self
    {
        return new self(
            emailEnabled: true,
            smsEnabled: false,
            channels: ['email'],
            timezone: null
        );
    }
}

// Register transformation
Mapper::setMaps([
    'notification_settings' => [
        'hydrate'   => fn($value) => NotificationSettings::fromJson($value),
        'dehydrate' => fn($value) => $value->toJson(),
    ],
]);
```

## Error Handling

The reference implementation wraps transformation errors with context:

```php
protected function mapValueObjects(stdClass $record, Collection $relations): array
{
    $attributes = array_merge((array) $record, $relations->all());
    
    foreach($this->mappings as $propertyName => $mapName) {
        if (isset(static::$maps[$mapName])) {
            try {
                $attributes[$propertyName] = call_user_func_array(
                    static::$maps[$mapName]['hydrate'], 
                    [$attributes[$propertyName]]
                );
            } catch (\Throwable $th) {
                // Provides context about which property failed
                throw new \Exception(
                    static::class . ": Unable to hydrate property $propertyName: " 
                    . $th->getMessage()
                );
            }
        }
    }
    
    return $attributes;
}
```

This gives helpful errors:

```text
ClientMapper: Unable to hydrate property email: Invalid email format
```

## Benefits of This Pattern

### 1. Declarative

```php
// Just declare the mapping
protected array $mappings = [
    'email' => 'email',
    'billing_address' => 'address',
];

// No imperative transformation code!
```

### 2. DRY (Don't Repeat Yourself)

```php
// Define transformation ONCE
Mapper::setMaps(['email' => ...]);

// Use EVERYWHERE
class UserMapper { protected array $mappings = ['email' => 'email']; }
class ClientMapper { protected array $mappings = ['email' => 'email']; }
class VendorMapper { protected array $mappings = ['email' => 'email']; }
```

### 3. Consistent

All entities transform the same way. No surprises, no bugs.

### 4. Testable

```php
// Test transformations in isolation
$email = Mapper::$maps['email']['hydrate']('john@example.com');
$this->assertInstanceOf(Email::class, $email);

$string = Mapper::$maps['email']['dehydrate']($email);
$this->assertEquals('john@example.com', $string);
```

### 5. Centralized

Change transformation logic in ONE place, affects all entities:

```php
// Change from lowercase to original case
'email' => [
    'hydrate'   => fn($value) => new Email($value), // No longer lowercases
    'dehydrate' => fn($value) => (string) $value,
],
```

## Alternative: Manual Transformation

If you don't want the mappings system, you can transform manually:

```php
class ClientMapper extends Mapper
{
    public function hydrate(stdClass $record, Collection $relations)
    {
        $client = $this->instantiator->instantiate(Client::class);
        
        return $client->mapperFill([
            'id' => $record->id,
            'email' => new Email($record->email),
            'billing_address' => Address::fromJson($record->billing_address),
            'total_revenue' => Money::fromJson($record->total_revenue),
        ]);
    }
    
    public function dehydrate($entity): array
    {
        return [
            'id' => $entity->id,
            'email' => (string) $entity->email,
            'billing_address' => $entity->billing_address->toJson(),
            'total_revenue' => $entity->total_revenue->toJson(),
        ];
    }
}
```

**When to use:**

- Simple applications with few entities
- Unique transformation logic per entity
- Need explicit control

**Tradeoffs:**

- ✅ Very explicit
- ✅ No magic
- ❌ Repetitive
- ❌ Not DRY

## Key Principles

1. **Define transformations once, use everywhere**
2. **Keep transformation logic out of entities**
3. **Value objects handle their own serialization** (fromJson/toJson)
4. **Mapper orchestrates transformations** but doesn't contain logic
5. **Type safety** - entities work with rich objects, not primitives

## Next Steps

- **[Value Objects Guide](./value-objects.md)** - Implementing Email, Money, Address
- **[Entity Hydration](./entity-hydration.md)** - How transformations fit into hydration
- **[Entity Lifecycle](./entity-lifecycle.md)** - When transformations happen
- **[Base Classes Reference](./base-classes.md)** - Complete implementation

## Further Reading

- Martin Fowler: [Value Object Pattern](https://martinfowler.com/bliki/ValueObject.html)
- Eric Evans: Domain-Driven Design (Value Objects chapter)
- Reference DataMapperServiceProvider (reference implementation)
