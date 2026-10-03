# Entity Design Patterns

Holloway's datamapper architecture provides complete decoupling between your entities and persistence logic. This means you have full freedom in how you design your entities. This guide showcases different approaches to entity design and how to configure mappers to work with each pattern.

## Table of Contents

- [Pattern Comparison](#pattern-comparison)
- [Magic Accessor Pattern (PHP 8.0-8.3)](#magic-accessor-pattern-recommended-for-php-80-83)
  - [Base Entity Class](#base-entity-class)
  - [Concrete Entity Example](#concrete-entity-example)
  - [Mapper Configuration](#mapper-for-magic-accessor-pattern)
- [Property Hooks Pattern (PHP 8.4+)](#property-hooks-pattern-recommended-for-php-84)
  - [Base Entity](#base-entity-with-property-hooks)
  - [Concrete Entity](#concrete-entity-with-property-hooks)
  - [Mapper Configuration](#mapper-for-property-hooks-pattern)
  - [Usage](#usage-with-property-hooks)
- [Array-Based Entities](#array-based-entities)
- [Immutable Entities](#immutable-entities)

## Pattern Comparison

| Pattern | PHP Version | Best For | Complexity |
|---------|-------------|----------|------------|
| **Magic Accessor Pattern** | 8.0+ | Complex apps, value objects, DRY | Medium |
| **Property Hooks Pattern** | 8.4+ | Modern apps, clean syntax | Low |
| **Array-Based Entities** | Any | Dynamic schemas, flexibility | Low |
| **Public Properties** | Any | Simple apps, quick prototypes | Very Low |
| **Getters/Setters** | Any | Strict encapsulation | High |
| **Immutable Entities** | 8.1+ | Functional programming, thread-safety | High |

## Magic Accessor Pattern (Recommended for PHP 8.0-8.3)

This pattern combines protected properties with magic `__get()` accessor and a `mapperFill()` method for hydration. Used in production by a multi-tenant SaaS application, this pattern scales exceptionally well for complex domains.

### Base Entity Class

```php
<?php

namespace App\Entities;

abstract class Entity
{
    protected int|string|null $id = null;
    
    /**
     * Fill entity properties from an array.
     * Used ONLY by mappers during hydration from database.
     */
    public function mapperFill(array $properties): self
    {
        foreach($properties as $propertyName => $propertyValue) {
            $this->$propertyName = $propertyValue;
        }
        
        return $this;
    }
    
    /**
     * Convert entity to array for persistence.
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
    
    /**
     * Magic accessor for read-only property access.
     */
    public function __get(string $name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
    }
    
    /**
     * Check if property exists.
     */
    public function __isset(string $name): bool
    {
        return property_exists($this, $name);
    }
}
```

### Concrete Entity Example

```php
<?php

namespace App\Entities;

use App\ValueObjects\Email;
use App\ValueObjects\Address;
use Money\Money;

class Client extends Entity
{
    // Protected properties - not directly accessible from outside
    protected string $tenant_id;
    protected string $first_name;
    protected string $last_name;
    protected Email $email;
    protected ?Address $billing_address = null;
    protected Money $total_revenue;
    protected Money $outstanding_balance;
    protected ClientStatus $status;
    
    /**
     * Constructor for CREATING new clients (validation here)
     */
    public function __construct(
        ClientCompany $company,
        string $first_name,
        string $last_name,
        string $job_title,
        Email $email
    ) {
        // Validation for NEW clients
        if (empty($first_name)) {
            throw new InvalidArgumentException('First name is required');
        }
        
        if (empty($last_name)) {
            throw new InvalidArgumentException('Last name is required');
        }
        
        // Initialize from domain objects
        $this->tenant_id = $company->tenant_id;
        $this->company_id = $company->id;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->job_title = $job_title;
        $this->email = $email;
        
        // Sensible defaults
        $this->total_revenue = Money::USD(0);
        $this->outstanding_balance = Money::USD(0);
        $this->status = ClientStatus::Active;
    }
    
    /**
     * Domain methods for business logic
     */
    public function changeName(string $first, string $last): void
    {
        if (empty($first) || empty($last)) {
            throw new InvalidArgumentException('Names cannot be empty');
        }
        
        $this->first_name = $first;
        $this->last_name = $last;
    }
    
    public function changeEmail(Email $newEmail): void
    {
        $this->email = $newEmail;
    }
    
    public function updateBillingAddress(Address $address): void
    {
        $this->billing_address = $address;
    }
    
    public function addRevenue(Money $amount): void
    {
        $this->total_revenue = $this->total_revenue->add($amount);
    }
    
    public function addOutstandingBalance(Money $amount): void
    {
        $this->outstanding_balance = $this->outstanding_balance->add($amount);
    }
    
    public function getFullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
    
    public function activate(): void
    {
        $this->status = ClientStatus::Active;
    }
    
    public function deactivate(): void
    {
        $this->status = ClientStatus::Inactive;
    }
}
```

### Mapper for Magic Accessor Pattern

```php
<?php

namespace App\Mappers;

use App\Entities\Client;

class ClientMapper extends Mapper
{
    protected string $table = 'clients';
    protected string $entityClassName = Client::class;
    
    // Declare type transformations
    protected array $mappings = [
        'email' => 'email',
        'billing_address' => 'address',
        'total_revenue' => 'money',
        'outstanding_balance' => 'money',
        'status' => 'client_status_enum',
    ];
    
    public function defineRelations(): void
    {
        $this->belongsTo('company', ClientCompany::class);
        $this->hasMany('invoices', ClientInvoice::class);
        $this->hasMany('services', Service::class);
    }
    
    // That's it! Base mapper handles hydrate/dehydrate automatically
}
```

## Property Hooks Pattern (Recommended for PHP 8.4+)

PHP 8.4 introduces property hooks, eliminating the need for magic methods while providing clean, declarative property behavior. This is the **modern recommended approach** for new projects on PHP 8.4+.

### Base Entity with Property Hooks

```php
<?php

namespace App\Entities;

abstract class Entity
{
    public int|string|null $id {
        get => $this->id;
        set => $this->id = $value;
    }
    
    /**
     * Fill entity properties from an array.
     * Used ONLY by mappers during hydration from database.
     */
    public function mapperFill(array $properties): self
    {
        foreach($properties as $propertyName => $propertyValue) {
            $this->$propertyName = $propertyValue;
        }
        
        return $this;
    }
    
    /**
     * Convert entity to array for persistence.
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
```

### Concrete Entity with Property Hooks

```php
<?php

namespace App\Entities;

use App\ValueObjects\Email;
use App\ValueObjects\Address;
use Money\Money;

class Client extends Entity
{
    // Property hooks for validation and transformation
    public string $tenant_id {
        get => $this->tenant_id;
        set {
            if (empty($value)) {
                throw new \InvalidArgumentException('Tenant ID required');
            }
            $this->tenant_id = $value;
        }
    }
    
    public string $first_name {
        get => $this->first_name;
        set => $this->first_name = trim($value);
    }
    
    public string $last_name {
        get => $this->last_name;
        set => $this->last_name = trim($value);
    }
    
    // Value object with automatic conversion
    public Email $email {
        get => $this->email;
        set {
            // Set hook can transform primitives to value objects
            $this->email = $value instanceof Email ? $value : new Email($value);
        }
    }
    
    public ?Address $billing_address {
        get => $this->billing_address ?? null;
        set => $this->billing_address = $value;
    }
    
    public Money $total_revenue {
        get => $this->total_revenue;
        set => $this->total_revenue = $value;
    }
    
    public Money $outstanding_balance {
        get => $this->outstanding_balance;
        set => $this->outstanding_balance = $value;
    }
    
    public ClientStatus $status {
        get => $this->status;
        set => $this->status = $value;
    }
    
    // Computed property (get-only hook)
    public string $full_name {
        get => "{$this->first_name} {$this->last_name}";
    }
    
    /**
     * Constructor for CREATING new clients (validation here)
     */
    public function __construct(
        string $tenant_id,
        string $first_name,
        string $last_name,
        Email|string $email
    ) {
        $this->tenant_id = $tenant_id;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->email = $email; // Set hook converts string to Email if needed
        
        // Sensible defaults
        $this->total_revenue = Money::USD(0);
        $this->outstanding_balance = Money::USD(0);
        $this->status = ClientStatus::Active;
    }
    
    /**
     * Domain methods for business logic
     */
    public function changeName(string $first, string $last): void
    {
        $this->first_name = $first; // Trimmed via set hook
        $this->last_name = $last;   // Trimmed via set hook
    }
    
    public function changeEmail(Email|string $newEmail): void
    {
        $this->email = $newEmail; // Converted via set hook
    }
    
    public function addRevenue(Money $amount): void
    {
        $this->total_revenue = $this->total_revenue->add($amount);
    }
    
    public function activate(): void
    {
        $this->status = ClientStatus::Active;
    }
    
    public function deactivate(): void
    {
        $this->status = ClientStatus::Inactive;
    }
}
```

### Mapper for Property Hooks Pattern

```php
<?php

namespace App\Mappers;

use App\Entities\Client;

class ClientMapper extends Mapper
{
    protected string $table = 'clients';
    protected string $entityClassName = Client::class;
    
    // Declare type transformations
    protected array $mappings = [
        'email' => 'email',
        'billing_address' => 'address',
        'total_revenue' => 'money',
        'outstanding_balance' => 'money',
        'status' => 'client_status_enum',
    ];
    
    public function defineRelations(): void
    {
        $this->belongsTo('company', ClientCompany::class);
        $this->hasMany('invoices', ClientInvoice::class);
        $this->hasMany('services', Service::class);
    }
}
```

### Usage with Property Hooks

```php
// Creating a NEW client
$client = new Client(
    tenant_id: 'tenant-123',
    first_name: '  John  ', // Trimmed via set hook
    last_name: 'Doe',
    email: 'john@example.com' // Converted to Email via set hook
);

$clientMapper->store($client);

// Loading EXISTING client
$client = $clientMapper->find(1);

// Direct property access (hooks execute automatically)
echo $client->first_name;        // "John" (trimmed)
echo $client->email->value;      // Email object (converted via hook)
echo $client->full_name;         // "John Doe" (computed property)

// Modifying (set hooks execute)
$client->first_name = '  Jane  '; // Automatically trimmed
$client->email = 'jane@example.com'; // Automatically converted to Email

$clientMapper->store($client);
```

## Array-Based Entities

Some developers prefer using arrays or array-like structures for maximum flexibility.

### Dynamic Properties with Arrays

```php
class User
{
    private array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    // Convenience methods for common attributes
    public function getId(): ?int
    {
        return $this->getAttribute('id');
    }

    public function getName(): ?string
    {
        return $this->getAttribute('name');
    }

    public function setName(string $name): void
    {
        $this->setAttribute('name', $name);
    }
}
```

**Mapper Configuration:**
```php
class UserMapper extends Mapper
{
    protected string $table = 'users';

    public function hydrate(array $attributes): User
    {
        return new User($attributes);
    }

    public function dehydrate($entity): array
    {
        return $entity->getAttributes();
    }

    public function getIdentifier($entity): mixed
    {
        return $entity->getId();
    }

    public function setIdentifier($entity, $identifier): void
    {
        $entity->setAttribute('id', $identifier);
    }
}
```

## Immutable Entities

Some prefer immutable entities for better consistency and thread safety.

### Immutable with Constructor

```php
class User
{
    private readonly ?int $id;
    private readonly string $name;
    private readonly string $email;
    private readonly bool $active;
    private readonly DateTime $createdAt;

    public function __construct(
        string $name,
        string $email,
        ?int $id = null,
        bool $active = true,
        ?DateTime $createdAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->active = $active;
        $this->createdAt = $createdAt ?? new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    // Create new instances for changes
    public function withName(string $name): self
    {
        return new self($name, $this->email, $this->id, $this->active, $this->createdAt);
    }

    public function withEmail(string $email): self
    {
        return new self($this->name, $email, $this->id, $this->active, $this->createdAt);
    }

    public function activate(): self
    {
        return new self($this->name, $this->email, $this->id, true, $this->createdAt);
    }

    public function deactivate(): self
    {
        return new self($this->name, $this->email, $this->id, false, $this->createdAt);
    }
}
```

**Mapper Configuration for Immutable Entities:**
```php
class UserMapper extends Mapper
{
    protected string $table = 'users';

    public function hydrate(array $attributes): User
    {
        return new User(
            $attributes['name'],
            $attributes['email'],
            $attributes['id'] ?? null,
            $attributes['active'] ?? true,
            isset($attributes['created_at']) ? new DateTime($attributes['created_at']) : null
        );
    }

    public function dehydrate($entity): array
    {
        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'email' => $entity->getEmail(),
            'active' => $entity->isActive(),
            'created_at' => $entity->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    public function getIdentifier($entity): mixed
    {
        return $entity->getId();
    }

    public function setIdentifier($entity, $identifier): User
    {
        // For immutable entities, return new instance
        return new User(
            $entity->getName(),
            $entity->getEmail(),
            $identifier,
            $entity->isActive(),
            $entity->getCreatedAt()
        );
    }

    public function store($entity): User
    {
        if ($entity->getId() === null) {
            // Insert and return new entity with ID
            $attributes = $this->dehydrate($entity);
            unset($attributes['id']);
            
            $id = $this->insertGetId($attributes);
            return $this->setIdentifier($entity, $id);
        } else {
            // Update existing
            $this->updateEntity($entity);
            return $entity;
        }
    }
}
```

## Configuration Considerations for Mappers

Different entity patterns may require specific mapper configurations:

### For Array-Based Entities
- Override `hydrate()` and `dehydrate()` methods
- Handle attribute access patterns
- Consider mass assignment protection

### For Immutable Entities
- Override `setIdentifier()` to return new instance
- Modify `store()` method to handle immutability
- Handle relationships carefully

## Choosing the Right Pattern

**Array-Based**: Best for maximum flexibility and dynamic schemas
**Defined Properties**: Best for type safety and IDE support
**Immutable**: Best for consistency and functional programming approaches
**Value Objects**: Best for rich domain models with validation
**Traits**: Best for sharing common behavior across entities

The beauty of Holloway's datamapper pattern is that your choice of entity design doesn't constrain your persistence strategy - the mapper handles the translation between your domain objects and the database.

## Next Steps

- **[Mappers](./mappers/creating-mappers.md)** - Implementing mappers for different entity patterns
- **[Relationships](./relationships/overview.md)** - Handling relationships with various entity designs
