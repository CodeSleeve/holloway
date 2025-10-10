# Base Classes Reference

This guide documents a reference implementation of base `Entity` and `Mapper` classes that provide a proven, production-ready foundation for your Holloway applications using the **Magic Accessor Pattern**.

> **Note**: This is ONE approach to implementing Holloway entities and mappers. Holloway is flexible and doesn't require these patterns, but they represent a battle-tested approach used in a production multi-tenant SaaS application.

## Overview

This reference implementation extends Holloway's base classes to provide:
- **Entity**: Protected properties with magic `__get()` accessor, `mapperFill()` for hydration, `toArray()` for dehydration
- **Mapper**: Type transformation system via `mappings` and `$maps`, automatic hydration/dehydration of value objects
- **Traits**: Reusable cross-cutting concerns (`HasTimestamps`, `HasTenant`, etc.)

## Base Entity Class

The base `Entity` class provides the foundation for the Magic Accessor Pattern.

### Complete Implementation

```php
<?php

namespace App\Entities;

use JsonSerializable;
use BadMethodCallException;
use Illuminate\Support\Str;
use Cake\Chronos\{Chronos, Date};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

abstract class Entity implements JsonSerializable
{
    use HasTimeStamps;

    protected int|string|null $id = null;

    /**
     * Return an array representation of this entity.
     *
     * @return array
     */
    public function toArray() : array
    {
        return get_object_vars($this);
    }

    /**
     * Magic accessor for protected properties
     *
     * @param  string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        $accessor = $this->attributeAccessorName($name);

        if (method_exists($this, $accessor)) {
            return $this->$accessor();
        } elseif (property_exists($this, $name)) {
            return $this->$name;
        }
    }

    public function __isset($name) : bool
    {
        return property_exists($this, $name);
    }

    /**
     * JSON serialization with automatic date formatting
     */
    public function jsonSerialize() : array
    {
        return array_map(function($item) {
            if (is_a($item, Date::class)) {
                return $item->toDateString();
            } else if (is_a($item, Chronos::class)) {
                return $item->toIso8601String();
            } else {
                return $item;
            }
        }, $this->toArray());
    }

    public function toJson() : string
    {
        return json_encode($this);
    }

    /**
     * Get a subset of the model's attributes.
     */
    public function only($attributes) : array
    {
        $results = [];

        foreach (is_array($attributes) ? $attributes : func_get_args() as $attribute) {
            $results[$attribute] = $this->$attribute;
        }

        return $results;
    }

    public function setId(string|int $id)
    {
        $this->id = $id;
    }

    public function getId(): string|int|null
    {
        return $this->id;
    }

    private function attributeAccessorName(string $name) : string
    {
        return 'get' . Str::studly($name);
    }

    /**
     * FOR USE ONLY BY ENTITY MAPPERS to hydrate our entities
     *
     * Bypasses constructor and directly sets properties from database records.
     * This is called by the mapper during hydration, NOT by application code.
     *
     * @return self
     */
    public function mapperFill(array $properties) : self
    {
        foreach($properties as $propertyName => $propertyValue) {
            $this->$propertyName = $propertyValue;
        }

        return $this;
    }

    /**
     * FOR USE ONLY IN TESTS to hydrate relationships onto our entities
     *
     * @throws BadMethodCallException 
     */
    public function setRelationForTest(string $name, Entity|Collection $relation) : void
    {
        if (App::environment('testing')) {
            $this->$name = $relation;
        } else {
            throw new BadMethodCallException('This method may only be used for testing purposes', 1);
        }
    }
}
```

### Method Documentation

#### `toArray(): array`

Returns all entity properties as an associative array. Uses `get_object_vars()` to capture all properties including protected ones.

**Usage:**
```php
$client = $clientMapper->find(123);
$data = $client->toArray();
// ['id' => 123, 'first_name' => 'John', 'last_name' => 'Doe', ...]
```

**Key points:**
- Includes ALL properties (id, attributes, relationships)
- Used by mapper's `dehydrate()` for database persistence
- Protected properties are accessible because called from within class

#### `__get(string $name): mixed`

Magic accessor allowing read access to protected properties and computed attributes.

**Property access:**
```php
// Direct property access
$name = $client->first_name; // Calls __get('first_name')
```

**Accessor methods:**
```php
class Client extends Entity
{
    protected string $first_name;
    protected string $last_name;
    
    // Accessor method (getFullName)
    protected function getFullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}

$fullName = $client->full_name; // Calls getFullName()
```

**Key points:**
- Checks for accessor method first: `get{StudlyName}()`
- Falls back to direct property access
- Returns `null` if property doesn't exist
- Enables computed properties without public properties

#### `mapperFill(array $properties): self`

**Purpose:** Hydrates entity from database record. Called exclusively by mapper's `hydrate()` method.

**Usage:**
```php
// In Mapper::hydrate()
$entity = $this->instantiator->instantiate($this->entityClassName);
return $entity->mapperFill($attributes);
```

**Why this exists:**
- Bypasses constructor validation (entities from DB are trusted)
- Sets protected properties from outside the class (mapper needs this access)
- Returns `$this` for fluent interface
- Clearly marks mapper-only code with ALL-CAPS comment

**Key points:**
- ⚠️ **Never call from application code** - only for mappers
- Loops through properties and sets directly: `$this->$propertyName = $propertyValue`
- Works with Doctrine Instantiator (which bypasses constructor)
- Properties must already be declared on the entity

#### `jsonSerialize(): array`

Implements `JsonSerializable` for automatic JSON encoding with date formatting.

**Usage:**
```php
$client = $clientMapper->find(123);
return response()->json($client);
// Automatically converts dates to ISO-8601 strings
```

**Key points:**
- `Date` objects → `toDateString()` (e.g., "2024-01-15")
- `Chronos` objects → `toIso8601String()` (e.g., "2024-01-15T10:30:00+00:00")
- Other values passed through unchanged
- Enables `json_encode($entity)` and `$entity->toJson()`

#### `only(...$attributes): array`

Returns subset of entity attributes (like Laravel's `Model::only()`).

**Usage:**
```php
$client = $clientMapper->find(123);

// Array syntax
$subset = $client->only(['first_name', 'last_name', 'email']);

// Variadic syntax
$subset = $client->only('first_name', 'last_name', 'email');

// Result: ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com']
```

**Key points:**
- Useful for API responses with specific fields
- Uses `__get()` magic accessor internally
- Supports both array and variadic arguments

#### `setRelationForTest(string $name, Entity|Collection $relation): void`

**Purpose:** Allows setting relationships in tests without loading from database.

**Usage:**
```php
// In tests
$client = new Client('John', 'Doe', email('john@example.com'));
$serviceJob = new ServiceJob(...);

$client->setRelationForTest('serviceJobs', collect([$serviceJob]));

$this->assertCount(1, $client->serviceJobs);
```

**Key points:**
- ⚠️ **Only works in testing environment** - throws exception in production
- Prevents misuse of test-only code
- Useful for unit tests that don't need database
- Guards against accidentally using test helpers in production

## Base Mapper Class

The base `Mapper` class extends Holloway's mapper with the reference implementation's type transformation system.

### Complete Implementation

```php
<?php

namespace App\Mappers;

use stdClass;
use App\Entities\Entity;
use Cake\Chronos\Chronos;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Doctrine\Instantiator\Instantiator;
use CodeSleeve\Holloway\Mapper as HollowayMapper;
use function App\Functions\ValueObject\base_utc_date_time;

abstract class Mapper extends HollowayMapper
{
    /** @var array Global type transformation map */
    protected static array $maps = [];

    /** @var array Instance-specific mappings */
    protected array $mappings = [];

    /**
     * @param Instantiator|null $instantiator
     */
    public function __construct(?Instantiator $instantiator = null)
    {
        parent::__construct();

        $this->instantiator = $instantiator ?: new Instantiator();
    }

    /**
     * @return string
     */
    public function getEntityClassName() : string
    {
        return $this->entityClassName;
    }

    /**
     * Return the identifier (primary key) for a given entity.
     *
     * @param  mixed $entity
     * @return mixed
     */
    public function getIdentifier($entity)
    {
        return $entity->id;
    }

    /**
     * Set the identifier (primary key) for a given entity.
     */
    public function setIdentifier($entity, $value) : void
    {
        $entity->setId($value);
    }

    /**
     * Register a single type transformation
     *
     * @param string $name
     * @param callable $hydrate
     * @param callable $dehydrate
     * @return void
     */
    public static function addMapp(string $name, callable $hydrate, callable $dehydrate)
    {
        static::$maps[$name] = compact('hydrate', 'dehydrate');
    }

    /**
     * Register all type transformations at once
     *
     * @param array $maps
     * @return void
     */
    public static function setMaps(array $maps)
    {
        static::$maps = $maps;
    }

    /**
     * Convert database record to entity instance
     *
     * @param  stdClass  $record
     * @param  Collection $relations
     * @return mixed
     */
    public function hydrate(stdClass $record, Collection $relations)
    {
        $attributes = $this->mapValueObjects($record, $relations);

        $entity = $this->instantiator->instantiate($this->entityClassName);

        return $entity->mapperFill($attributes);
    }

    /**
     * Convert entity instance to database array
     *
     * @param  mixed $entity
     * @return array
     */
    public function dehydrate($entity) : array
    {
        // Get all entity properties except relationships
        $attributes = Arr::except(
            $entity->toArray(), 
            array_map(fn($relationship) => $relationship->getName(), $this->relationships)
        );

        // Remove null ID (new entities)
        if (!$attributes['id']) {
            unset($attributes['id']);
        }
        
        // Transform value objects back to primitives
        foreach($this->mappings as $propertyName => $map) {
            if (isset(static::$maps[$map])) {
                $attributes[$propertyName] = call_user_func_array(
                    static::$maps[$map]['dehydrate'], 
                    [$attributes[$propertyName]]
                );
            }
        }

        return $attributes;
    }

    /**
     * Set created_at timestamp (called by Holloway on insert)
     *
     * @param \DateTime $now
     * @param Entity    $entity
     */
    public function setCreatedAtTimestampOnEntity(Entity $entity, \DateTime $now)
    {
        $createdAt = Chronos::instance($now);
    
        $entity->setCreatedAt($createdAt);
    }

    /**
     * Set updated_at timestamp (called by Holloway on update)
     *
     * @param \DateTime $now
     * @param Entity    $entity
     */
    public function setUpdatedAtTimestampOnEntity(Entity $entity, \DateTime $now)
    {
        $updatedAt = Chronos::instance($now);
    
        $entity->setUpdatedAt($updatedAt);
    }

    /**
     * Define relationships (override in child mappers)
     *
     * @return void
     */
    public function defineRelations() : void
    {
        // Override in child classes
    }

    /**
     * Transform database primitives to value objects during hydration
     *
     * @param  stdClass  $record
     * @param  Collection $relations
     * @return array
     */
    protected function mapValueObjects(stdClass $record, Collection $relations) : array
    {
        $object = $this->instantiator->instantiate($this->entityClassName);
        $attributes = array_merge((array) $record, $relations->all());

        // Handle timestamps
        if ($this->hasTimestamps) {
            $attributes['created_at'] = base_utc_date_time($record->created_at);
            $attributes['updated_at'] = base_utc_date_time($record->updated_at);
        }

        // Transform mapped properties
        foreach($this->mappings as $propertyName => $map) {
            if (isset(static::$maps[$map])) {
                try {
                    $attributes[$propertyName] = call_user_func_array(
                        static::$maps[$map]['hydrate'], 
                        [$attributes[$propertyName]]
                    );
                } catch (\Throwable $th) {
                    throw new \Exception(
                        static::class . ": Unable to hydrate property $propertyName: " . $th->getMessage()
                    );
                }
            }
        }

        return $attributes;
    }
}
```

### Method Documentation

#### `hydrate(stdClass $record, Collection $relations): Entity`

**Purpose:** Converts database record to entity instance.

**Flow:**
1. Call `mapValueObjects()` to transform primitives → value objects
2. Use Doctrine Instantiator to create entity without constructor
3. Call `mapperFill()` to set properties
4. Return hydrated entity

**Example:**
```php
// Called internally by Holloway
$record = (object) ['id' => 1, 'email' => 'john@example.com', 'created_at' => '2024-01-15 10:30:00'];
$relations = collect(['serviceJobs' => collect([...])]);

$client = $mapper->hydrate($record, $relations);
// Client entity with Email value object and relationships
```

**Key points:**
- Never called directly - Holloway calls this internally
- Bypasses constructor (uses Instantiator)
- Transforms ALL properties through mappings system
- Relationships already loaded by Holloway

#### `dehydrate(Entity $entity): array`

**Purpose:** Converts entity instance to database array for persistence.

**Flow:**
1. Call `toArray()` on entity
2. Remove relationships (they're stored in separate tables)
3. Remove null ID (new entities don't have IDs yet)
4. Transform value objects → primitives via mappings
5. Return array ready for database

**Example:**
```php
$client = new Client('John', 'Doe', email('john@example.com'));

$data = $mapper->dehydrate($client);
// ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com']
// Note: Email value object converted to string
```

**Key points:**
- Relationships excluded (Holloway persists them separately)
- Null IDs removed (database auto-generates them)
- All mappings applied in reverse (dehydrate functions)
- Returns array compatible with database insert/update

#### `mapValueObjects(stdClass $record, Collection $relations): array`

**Purpose:** Core transformation engine - converts database primitives to value objects.

**Flow:**
1. Merge database record with loaded relationships
2. Transform timestamps to Chronos objects
3. Loop through `$this->mappings`
4. For each mapping, call the `hydrate` function from `static::$maps`
5. Return transformed attributes array

**Example:**
```php
// In ClientMapper
protected array $mappings = [
    'email' => 'email',
    'billing_address' => 'address',
    'total_revenue' => 'money',
];

// Database record
$record = (object) [
    'email' => 'john@example.com',
    'billing_address' => '{"street": "123 Main St", ...}',
    'total_revenue' => 150000, // cents
];

// After mapValueObjects()
$attributes = [
    'email' => Email('john@example.com'),
    'billing_address' => Address(...),
    'total_revenue' => Money::USD(1500, 00),
];
```

**Error handling:**
```php
try {
    $attributes[$propertyName] = call_user_func_array(
        static::$maps[$map]['hydrate'], 
        [$attributes[$propertyName]]
    );
} catch (\Throwable $th) {
    throw new \Exception(
        static::class . ": Unable to hydrate property $propertyName: " . $th->getMessage()
    );
}
```

**Key points:**
- Wraps transformations in try/catch for debugging
- Error messages include mapper class and property name
- Only transforms properties listed in `$this->mappings`
- Other properties pass through unchanged

#### `setMaps(array $maps): void`

**Purpose:** Register all type transformations globally (called once in service provider).

**Usage:**
```php
// In DataMapperServiceProvider
Mapper::setMaps([
    'email' => [
        'hydrate' => fn(?string $value) => $value ? email($value) : null,
        'dehydrate' => fn(?Email $value) => $value?->toString(),
    ],
    'money' => [
        'hydrate' => fn(?int $value) => $value !== null ? money($value) : null,
        'dehydrate' => fn(?Money $value) => $value?->getAmount(),
    ],
    'address' => [
        'hydrate' => fn(?string $value) => $value ? address($value) : null,
        'dehydrate' => fn(?Address $value) => $value ? json_encode($value) : null,
    ],
    // ... more transformations
]);
```

**Key points:**
- Called ONCE at application boot
- Static property shared across ALL mappers
- Each transformation has `hydrate` and `dehydrate` functions
- Hydrate: primitive → value object
- Dehydrate: value object → primitive

#### `addMapp(string $name, callable $hydrate, callable $dehydrate): void`

**Purpose:** Register single type transformation (rarely used - prefer `setMaps()`).

**Usage:**
```php
// Register custom transformation
Mapper::addMapp(
    'custom_type',
    fn($value) => new CustomType($value),
    fn($obj) => $obj->getValue()
);
```

**Key points:**
- Useful for one-off custom transformations
- Most applications use `setMaps()` for all transformations
- Same format as `setMaps()` entries

### The Mappings System

The mappings system is this reference implementation's approach to type transformations.

**How it works:**

1. **Define mappings in mapper:**
   ```php
   protected array $mappings = [
       'email' => 'email',           // property => type name
       'total_revenue' => 'money',
       'created_at' => 'date_time',
   ];
   ```

2. **Register transformations globally:**
   ```php
   Mapper::setMaps([
       'email' => [
           'hydrate' => fn($value) => email($value),
           'dehydrate' => fn($value) => $value?->toString(),
       ],
       // ...
   ]);
   ```

3. **Hydration (database → entity):**
   ```php
   $record->email = 'john@example.com';  // string from database
   // mapValueObjects() transforms via 'email' hydrate function
   $entity->email = Email('john@example.com'); // Email value object
   ```

4. **Dehydration (entity → database):**
   ```php
   $entity->email = Email('john@example.com'); // Email value object
   // dehydrate() transforms via 'email' dehydrate function
   $attributes['email'] = 'john@example.com'; // string for database
   ```

**Benefits:**
- **Centralized**: All transformations defined in one place
- **Reusable**: Same transformation used by all mappers
- **Type-safe**: Value objects ensure data integrity
- **Testable**: Transformations are pure functions
- **Flexible**: Easy to add new value object types

## Entity Traits

This pattern uses traits for cross-cutting concerns.

### HasTimestamps Trait

```php
<?php

namespace App\Entities;

use Cake\Chronos\Chronos;

trait HasTimestamps
{
    protected ?Chronos $created_at = null;
    protected ?Chronos $updated_at = null;

    public function getCreatedAt(): ?Chronos
    {
        return $this->created_at;
    }

    public function setCreatedAt(?Chronos $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function getUpdatedAt(): ?Chronos
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?Chronos $updated_at): void
    {
        $this->updated_at = $updated_at;
    }
}
```

**Usage:**
```php
class Client extends Entity
{
    use HasTimestamps;
    
    // Automatically includes created_at and updated_at
}
```

**Key points:**
- Used by base `Entity` class
- Mapper calls `setCreatedAt()` and `setUpdatedAt()` automatically
- Uses Cake Chronos for immutable date/time
- Properties are protected, accessed via magic `__get()`

### HasTenant Trait (Multi-Tenancy)

```php
<?php

namespace App\Entities;

trait HasTenant
{
    protected string $tenant_id;

    public function getTenantId(): string
    {
        return $this->tenant_id;
    }

    public function setTenantId(string $tenant_id): void
    {
        $this->tenant_id = $tenant_id;
    }
}
```

**Usage:**
```php
class Client extends Entity
{
    use HasTimestamps, HasTenant;
    
    // Now has tenant_id property
}
```

**Key points:**
- Every entity in a multi-tenant app needs tenant isolation
- Trait provides consistent `tenant_id` property
- Mapper can apply global scope for automatic tenant filtering

## Example Child Mapper

Here's how the reference implementation uses the base mapper:

```php
<?php

namespace App\Mappers;

use App\Entities;

class ClientMapper extends Mapper
{
    protected string $table = 'clients';
    protected string $entityClassName = Entities\Client::class;
    
    // Define which properties should be transformed
    protected array $mappings = [
        'date_of_birth' => 'date',
        'email' => 'email',
        'billing_address' => 'address',
        'total_revenue' => 'money',
        'outstanding_balance' => 'money',
        'last_service_date' => 'date',
        'notification_settings' => 'client_notification_settings',
    ];

    public function defineRelations(): void
    {
        $this->belongsTo('tenant', Entities\Tenant::class);
        $this->hasMany('services', Entities\Service::class);
        $this->hasMany('invoices', Entities\ClientInvoice::class);
        $this->belongsToMany('locations', Entities\Location::class);
    }
}
```

**What happens:**
1. Database loads: `date_of_birth = '1985-03-15'` (string)
2. Mapper hydrates: `date_of_birth = Date::parse('1985-03-15')` (Date object)
3. Entity receives: `$client->date_of_birth` is a Date object
4. Application uses: `$client->date_of_birth->addYears(1)`
5. Mapper dehydrates: `date_of_birth = '1986-03-15'` (string for database)

## Example Child Entity

Here's how the reference implementation uses the base entity:

```php
<?php

namespace App\Entities;

use Cake\Chronos\Date;
use App\ValueObjects\{Email, Address, Money};

class Client extends Entity
{
    use HasTimestamps, HasTenant;

    protected string $first_name;
    protected string $last_name;
    protected Email $email;
    protected ?Date $date_of_birth;
    protected ?Address $billing_address;
    protected Money $total_revenue;
    protected Money $outstanding_balance;

    public function __construct(
        string $first_name,
        string $last_name,
        Email $email
    ) {
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->email = $email;
        $this->total_revenue = money(0);
        $this->outstanding_balance = money(0);
    }

    // Accessor for computed property
    protected function getFullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // Domain logic
    public function recordPayment(Money $amount): void
    {
        if ($amount->greaterThan($this->outstanding_balance)) {
            throw new \DomainException('Payment exceeds outstanding balance');
        }
        
        $this->outstanding_balance = $this->outstanding_balance->subtract($amount);
    }
}
```

**What the base class provides:**
- `$client->full_name` - Calls `getFullName()` accessor via `__get()`
- `$client->email` - Returns Email value object via `__get()`
- `$client->toArray()` - Returns all properties as array
- `json_encode($client)` - Automatic JSON with date formatting
- `$client->mapperFill([...])` - Used by mapper during hydration

## Integration Example

Here's how it all works together:

```php
// 1. Application boot - register transformations
Mapper::setMaps([
    'email' => [
        'hydrate' => fn($value) => email($value),
        'dehydrate' => fn($value) => $value?->toString(),
    ],
    // ... other transformations
]);

// 2. Load from database
$clientMapper = app(ClientMapper::class);
$client = $clientMapper->find(123);
// - Holloway queries database
// - Returns stdClass record
// - Mapper calls hydrate()
//   - mapValueObjects() transforms 'email' string → Email object
//   - Instantiator creates Client entity
//   - mapperFill() sets all properties
// - Returns Client entity with value objects

// 3. Use entity
$client->first_name; // "John" (via __get())
$client->email; // Email object (via __get())
$client->email->toString(); // "john@example.com"
$client->full_name; // "John Doe" (calls getFullName() accessor)

// 4. Modify entity
$client = new Client('Jane', 'Smith', email('jane@example.com'));

// 5. Save to database
$clientMapper->save($client);
// - Mapper calls dehydrate()
//   - toArray() gets all properties
//   - Relationships excluded
//   - mapValueObjects() transforms Email object → 'email' string
// - Holloway inserts/updates database
// - Returns saved entity with ID
```

## When to Use These Base Classes

**Use the Magic Accessor Pattern when:**
- Building a new project from scratch (PHP 8.0-8.3)
- Want opinionated, proven patterns
- Need type transformation system
- Like protected properties with magic accessors
- Value consistency across entities and mappers
- Working with complex value objects

**Consider Property Hooks Pattern instead when:**
- Using PHP 8.4+ (cleaner, more modern syntax)

**Don't use this pattern when:**
- Already have established entity/mapper patterns
- Prefer public properties or explicit getters/setters
- Don't need value objects
- Want different hydration/dehydration approach
- Project has simpler requirements

**Remember:** Holloway is flexible - this is ONE solution, not THE solution.

## Next Steps

- **[Entity Hydration](./entity-hydration.md)** - Deep dive into hydration patterns
- **[Type Transformations](./type-transformations.md)** - Understanding the mappings system
- **[Value Objects](./value-objects.md)** - Working with Email, Money, Address
- **[Creating Mappers](../mappers/creating-mappers.md)** - Mapper implementation guide
