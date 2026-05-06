# Entity Hydration Patterns

Entity hydration is the process of converting database records (raw data) into domain entity instances. This is one of the core responsibilities of the datamapper pattern, and Holloway gives you complete control over how this process works.

## Table of Contents

- [Understanding the Core Contract](#understanding-the-core-contract)
- [The Challenge](#the-challenge)
- [Approach 1: Simple Manual Hydration](#approach-1-simple-manual-hydration)
- [Approach 2: Magic Accessor Pattern (Recommended for PHP 8.0-8.3)](#approach-2-magic-accessor-pattern-recommended-for-php-80-83)
- [Approach 3: Named Constructors](#approach-3-named-constructors)
- [Approach 4: Reflection-Based](#approach-4-reflection-based)
- [Comparison Table](#comparison-table)
- [Recommendations](#recommendations)
- [Key Principles](#key-principles)
- [Next Steps](#next-steps)
- [Further Reading](#further-reading)

## Understanding the Core Contract

At its heart, Holloway requires mappers to implement two critical methods:

```php
abstract class Mapper
{
    /**
     * Convert a database record into an entity instance
     */
    abstract public function hydrate(stdClass $record, Collection $relations);
    
    /**
     * Convert an entity instance into a database-ready array
     */
    abstract public function dehydrate($entity): array;
}
```

**That's it.** These two methods give you complete control over:

- How entities are constructed from database data
- How entity data is extracted for persistence
- What type transformations happen
- How relationships are attached

The rest is up to you and your application's needs.

## The Challenge

The challenge with entity hydration is that entities often have:

- **Protected/private properties** that can't be set directly
- **Value objects** that need transformation from strings/JSON
- **Required constructor parameters** that don't match database columns
- **Relationships** that need to be attached
- **Validation rules** that should run for new entities but not loaded ones

You need a pattern that handles all of this cleanly.

## Approach 1: Simple Manual Hydration

For simple entities, manual hydration is straightforward:

```php
class User
{
    private int $id;
    private string $name;
    private string $email;
    
    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
    }
    
    public function setId(int $id): void
    {
        $this->id = $id;
    }
    
    public function getId(): int
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
}

class UserMapper extends Mapper
{
    public function hydrate(stdClass $record, Collection $relations)
    {
        // Create entity with required properties
        $user = new User($record->name, $record->email);
        
        // Set internal properties
        if (isset($record->id)) {
            $user->setId($record->id);
        }
        
        return $user;
    }
    
    public function dehydrate($entity): array
    {
        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'email' => $entity->getEmail(),
        ];
    }
}
```

**When to use:**

- Simple entities with few properties
- No value objects or complex types
- Different constructor signature than database columns
- Need explicit control over each property

**Tradeoffs:**

- ✅ Full control and clarity
- ✅ No hidden magic
- ❌ Verbose for complex entities
- ❌ Must update mapper when entity changes

## Approach 2: Magic Accessor Pattern (Recommended for PHP 8.0-8.3)

> **User-space pattern** — `mapperFill()`, `mapValueObjects()`, and `addMapp()` are **not** provided by Holloway. They are reference implementations you define in your own base classes. The code below is a starting point, not a built-in API.

This reusable pattern separates concerns and scales to complex entities. Used in production by a multi-tenant SaaS application:

### The Pattern

```php
// Base Entity class
abstract class Entity
{
    protected int|string|null $id = null;
    
    /**
     * Fill entity properties from an array
     * Used ONLY by mappers during hydration
     */
    public function mapperFill(array $properties): self
    {
        foreach($properties as $propertyName => $propertyValue) {
            $this->$propertyName = $propertyValue;
        }
        
        return $this;
    }
    
    /**
     * Convert entity to array for persistence
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
    
    /**
     * Magic accessor for read-only property access
     */
    public function __get(string $name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
    }
}
```

```php
// Base Mapper class
use Doctrine\Instantiator\Instantiator;

abstract class Mapper extends HollowayMapper
{
    protected Instantiator $instantiator;
    
    public function __construct()
    {
        parent::__construct();
        $this->instantiator = new Instantiator();
    }
    
    /**
     * Hydrate entity from database record
     */
    public function hydrate(stdClass $record, Collection $relations)
    {
        // Prepare attributes (including type transformations)
        $attributes = $this->prepareAttributes($record, $relations);
        
        // Create entity WITHOUT calling constructor
        $entity = $this->instantiator->instantiate($this->entityClassName);
        
        // Fill properties
        return $entity->mapperFill($attributes);
    }
    
    /**
     * Dehydrate entity to database array
     */
    public function dehydrate($entity): array
    {
        $attributes = $entity->toArray();
        
        // Remove relationships
        $attributes = $this->removeRelationships($attributes);
        
        // Remove null id for inserts
        if (!$attributes['id']) {
            unset($attributes['id']);
        }
        
        // Apply type transformations for persistence
        $attributes = $this->applyDehydration($attributes);
        
        return $attributes;
    }
    
    protected function prepareAttributes($record, $relations): array
    {
        // Convert stdClass to array and merge relationships
        return array_merge((array) $record, $relations->all());
    }
    
    protected function removeRelationships(array $attributes): array
    {
        $relationshipNames = array_map(
            fn($rel) => $rel->getName(), 
            $this->relationships
        );
        
        return array_diff_key($attributes, array_flip($relationshipNames));
    }
    
    protected function applyDehydration(array $attributes): array
    {
        // Override in subclass for type transformations
        return $attributes;
    }
}
```

### Real-World Usage

```php
// Your entity
class Client extends Entity
{
    protected string $tenant_id;
    protected string $first_name;
    protected string $last_name;
    protected Email $email;
    protected Address $billing_address;
    protected Money $total_revenue;
    
    /**
     * Constructor for CREATING new clients
     */
    public function __construct(
        ClientCompany $company,
        string $first_name,
        string $last_name,
        string $job_title,
        Email $email
    ) {
        // Validation happens here
        if (empty($first_name)) {
            throw new InvalidArgumentException('First name required');
        }
        
        $this->tenant_id = $company->tenant_id;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->email = $email;
    }
    
    // Domain methods...
    public function changeName(string $first, string $last): void
    {
        $this->first_name = $first;
        $this->last_name = $last;
    }
}

// Your mapper - very simple!
class ClientMapper extends Mapper
{
    protected string $table = 'clients';
    protected string $entityClassName = Client::class;
    
    // That's it! Hydration and dehydration handled by base class
}
```

### How It Works

1. **Hydration:**
   - Mapper receives database record
   - Creates entity WITHOUT calling constructor (using Instantiator)
   - Calls `mapperFill()` to set all properties directly
   - No validation runs (database data assumed valid)

2. **Dehydration:**
   - Entity's `toArray()` converts to array
   - Mapper removes relationships
   - Mapper removes null id for inserts
   - Result saved to database

**When to use:**
- Complex entities with many properties
- Need value objects (Email, Money, Address)
- Want separation of creation vs hydration
- Building a reusable architecture

**Tradeoffs:**
- ✅ Scales to complex entities
- ✅ DRY - define properties once
- ✅ Enables type transformation system
- ✅ Clean separation of concerns
- ❌ Slightly less explicit than manual
- ❌ Requires base classes

## Approach 3: Named Constructors

Use static factory methods for different creation scenarios:

```php
class User
{
    private int $id;
    private string $name;
    private string $email;
    private bool $active;
    
    // Private constructor prevents direct instantiation
    private function __construct() {}
    
    /**
     * Named constructor for creating NEW users
     */
    public static function create(string $name, string $email): self
    {
        $user = new self();
        
        // Validation for new users
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email');
        }
        
        $user->name = $name;
        $user->email = $email;
        $user->active = true;
        
        return $user;
    }
    
    /**
     * Named constructor for hydration from database
     */
    public static function fromDatabase(array $data): self
    {
        $user = new self();
        
        // No validation - database data trusted
        $user->id = $data['id'];
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->active = $data['active'];
        
        return $user;
    }
    
    public function getId(): int
    {
        return $this->id;
    }
}

class UserMapper extends Mapper
{
    public function hydrate(stdClass $record, Collection $relations)
    {
        return User::fromDatabase((array) $record);
    }
    
    public function dehydrate($entity): array
    {
        return [
            'id' => $entity->getId(),
            // ... other properties
        ];
    }
}
```

**When to use:**

- Want explicit factory methods
- Different initialization logic for different scenarios
- Prefer static methods over base class

**Tradeoffs:**

- ✅ Very explicit and clear
- ✅ Self-documenting
- ✅ No base class required
- ❌ Still needs manual property mapping
- ❌ Dehydration still verbose

## Approach 4: Reflection-Based

Use PHP's reflection for automatic property population:

```php
use ReflectionClass;
use ReflectionProperty;

class ReflectionHydrator
{
    public static function hydrate(string $class, array $data): object
    {
        $reflection = new ReflectionClass($class);
        $entity = $reflection->newInstanceWithoutConstructor();
        
        foreach ($data as $property => $value) {
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $prop->setAccessible(true);
                $prop->setValue($entity, $value);
            }
        }
        
        return $entity;
    }
}

class UserMapper extends Mapper
{
    public function hydrate(stdClass $record, Collection $relations)
    {
        $data = (array) $record;
        return ReflectionHydrator::hydrate(User::class, $data);
    }
}
```

**When to use:**

- Want zero boilerplate
- Properties directly match database columns
- Performance is not critical (reflection is slower)

**Tradeoffs:**

- ✅ Minimal code
- ✅ Works with private properties
- ❌ Slower than direct access
- ❌ Less control over process
- ❌ Harder to debug

## Comparison Table

| Approach | Boilerplate | Flexibility | Type Transform | Performance | Best For |
|----------|-------------|-------------|----------------|-------------|----------|
| Manual | High | High | Manual | Fast | Simple entities |
| Magic Accessor | Low | High | System | Fast | Complex entities |
| Named Constructors | Medium | High | Manual | Fast | Explicit control |
| Reflection | Minimal | Medium | Limited | Slower | Rapid development |

## Recommendations

### For Simple Applications

Start with **Manual Hydration** for clarity and simplicity. As complexity grows, consider the Magic Accessor Pattern.

### For Complex Applications

Use **Magic Accessor Pattern** from the start. The base classes provide:

- Scalable hydration/dehydration
- Support for type transformations (see [Type Transformations](./type-transformations.md))
- Clean separation of creation vs loading
- DRY principle across all entities

### For Rapid Prototyping

**Named Constructors** or **Reflection** can get you moving quickly, but may require refactoring as the application grows.

## Key Principles

Regardless of approach, follow these principles:

1. **Separate Creation from Hydration**
   - Constructor validates and enforces business rules
   - Hydration trusts database data (already validated)

2. **Keep Mappers Thin**
   - Mappers handle data access only
   - No business logic, no validation
   - Delegate to entities or value objects

3. **Be Consistent**
   - Choose one pattern for your application
   - Document your choice
   - Train team on the pattern

4. **Entity Control**
   - Entities decide their structure
   - Mappers adapt to entities
   - Not the other way around

## Next Steps

- **[Entity Lifecycle](./entity-lifecycle.md)** - Understand creation vs hydration lifecycle
- **[Type Transformations](./type-transformations.md)** - Transform database types to domain objects
- **[Value Objects](./value-objects.md)** - Working with Email, Money, Address value objects
- **[Base Classes](./base-classes.md)** - Reference implementation of base Entity and Mapper

## Further Reading

- Martin Fowler's [Data Mapper Pattern](https://martinfowler.com/eaaCatalog/dataMapper.html)
- Doctrine Instantiator: [Bypass constructors for hydration](https://github.com/doctrine/instantiator)
