# Creating Mappers

Mappers are the core component of Holloway's datamapper pattern. They handle all database operations, entity hydration/dehydration, and relationship management. This guide covers everything you need to know about creating and configuring mappers.

## Table of Contents

- [Basic Mapper Structure](#basic-mapper-structure)
- [Configuration Properties](#configuration-properties)
  - [Essential Configuration](#essential-configuration)
  - [Timestamp Configuration](#timestamp-configuration)
  - [Database Configuration](#database-configuration)
- [Required Methods](#required-methods)
  - [Dehydration: Entity → Database](#dehydration-entity--database)
  - [Hydration: Database → Entity](#hydration-database--entity)
  - [Relationship Definition](#relationship-definition)
- [Advanced Configuration](#advanced-configuration)
  - [Table Name Convention Override](#table-name-convention-override)
  - [Primary Key Configuration](#primary-key-configuration)
  - [Custom Timestamps](#custom-timestamps)
- [Mapper Scopes](#mapper-scopes)
- [Global Scopes](#global-scopes)
- [What NOT to Put in Mappers](#what-not-to-put-in-mappers)
- [Magic Accessor Pattern Base Mapper](#magic-accessor-pattern-base-mapper)
- [Testing Your Mappers](#testing-your-mappers)
- [Common Patterns](#common-patterns)
  - [Multi-Database Support](#multi-database-support)
  - [Multi-Tenant Support](#multi-tenant-support)
  - [Custom Helper Methods](#custom-helper-methods)

## Basic Mapper Structure

Every mapper extends the base `Mapper` class and must implement specific abstract methods:

```php
<?php

namespace App\Mappers;

use App\Entities\User;
use CodeSleeve\Holloway\Mapper;

class UserMapper extends Mapper
{
    // Required configuration
    protected string $table = 'users';
    protected string $entityClassName = User::class;
    
    // Required methods
    public function defineRelations(): void
    {
        // Define entity relationships
    }
    
    public function dehydrate($entity): array
    {
        // Convert entity to database record
    }
    
    public function hydrate($record, $relations = null)
    {
        // Convert database record to entity
    }
}
```

## Configuration Properties

### Essential Configuration

```php
class UserMapper extends Mapper
{
    // The database table this mapper uses
    protected string $table = 'users';
    
    // The entity class this mapper creates
    protected string $entityClassName = User::class;
    
    // The primary key column name
    protected string $primaryKey = 'id';
    
    // The primary key type ('int', 'string', etc.)
    protected string $keyType = 'int';
    
    // Whether the primary key auto-increments
    protected bool $incrementing = true;
}
```

### Timestamp Configuration

```php
class UserMapper extends Mapper
{
    // Whether to automatically manage created_at/updated_at
    protected bool $hasTimestamps = true;
    
    // Format for timestamp columns
    protected string $timestampFormat = 'Y-m-d H:i:s';
    
    // Custom timestamp column names
    public function getCreatedAtColumnName(): string
    {
        return 'created_at';
    }
    
    public function getUpdatedAtColumnName(): string
    {
        return 'updated_at';
    }
}
```

### Database Configuration

```php
class UserMapper extends Mapper
{
    // Database connection name (uses default if empty)
    protected string $connection = '';
    
    // Default pagination size
    protected int $perPage = 15;
    
    // Relationships to eager load by default
    protected array $with = ['profile', 'roles'];
}
```

## Required Methods

### Dehydration: Entity → Database

The `dehydrate()` method converts an entity instance into an array suitable for database storage.

**Simple manual approach:**

```php
public function dehydrate($entity): array
{
    return [
        'id' => $entity->id,
        'name' => $entity->name,
        'email' => $entity->email,
        'is_active' => $entity->is_active,
    ];
}
```

**Magic Accessor Pattern (recommended):**

```php
// Base mapper handles this automatically!
// Entity provides toArray(), mapper removes relationships and transforms types
public function dehydrate($entity): array
{
    $attributes = $entity->toArray();
    
    // Remove relationships
    $attributes = Arr::except($attributes, ['posts', 'company']);
    
    // Remove null id for inserts
    if (!$attributes['id']) {
        unset($attributes['id']);
    }
    
    // Transformations applied automatically via mappings
    return $attributes;
}
```

**Key Principles:**
- ❌ **DON'T** put validation in dehydrate - validation belongs in entity constructor
- ❌ **DON'T** put business logic in dehydrate - mappers handle persistence only
- ✅ **DO** handle type conversions (objects to strings, enums to values)
- ✅ **DO** exclude computed properties and relationships
- ✅ **DO** delegate to type transformation system when possible

### Hydration: Database → Entity

The `hydrate()` method converts a database record into an entity instance.

**Simple manual approach:**

```php
public function hydrate($record, $relations = null)
{
    $user = new User();
    $user->id = $record->id;
    $user->name = $record->name;
    $user->email = $record->email;
    
    return $user;
}
```

**Magic Accessor Pattern (recommended):**

```php
use Doctrine\Instantiator\Instantiator;

public function hydrate($record, $relations)
{
    // Prepare attributes with type transformations
    $attributes = $this->mapValueObjects($record, $relations);
    
    // Instantiate WITHOUT calling constructor (bypasses validation)
    $entity = $this->instantiator->instantiate($this->entityClassName);
    
    // Fill properties directly
    return $entity->mapperFill($attributes);
}
```

**Key Principles:**
- ❌ **DON'T** call entity constructor during hydration - it runs validation meant for NEW entities
- ❌ **DON'T** put validation in hydration - database data is already trusted
- ❌ **DON'T** put business logic in hydration - keep it in entities
- ✅ **DO** use Instantiator to bypass constructor
- ✅ **DO** delegate to type transformation system
- ✅ **DO** trust database data (it was validated when created)

**Why bypass constructor?**
See [Entity Lifecycle](../core-concepts/entity-lifecycle.md) for the full explanation of creation vs hydration lifecycles.

### Relationship Definition

The `defineRelations()` method declares all relationships for this entity:

```php
public function defineRelations(): void
{
    // Standard relationships
    $this->hasOne('profile', UserProfile::class);
    $this->hasMany('posts', Post::class);
    $this->belongsTo('company', Company::class);
    $this->belongsToMany('roles', Role::class, 'user_roles');
    
    // Custom relationships
    $this->customMany('recentPosts', function($query, $users) {
        return $query->from('posts')
            ->whereIn('user_id', $users->pluck('id'))
            ->where('created_at', '>=', now()->subDays(30))
            ->get();
    }, function($user, $post) {
        return $user->id == $post->user_id;
    }, Post::class);
}
```

## Advanced Configuration

### Table Name Convention Override

If you need custom table naming logic:

```php
public function getTable(): string
{
    if (!$this->table) {
        // Custom logic for table name generation
        $entityName = class_basename($this->entityClassName);
        return strtolower($entityName) . 's';
    }
    
    return $this->table;
}
```

### Primary Key Configuration

For composite or non-standard primary keys:

```php
class UserMapper extends Mapper
{
    protected string $primaryKey = 'user_id';
    protected string $keyType = 'string';
    protected bool $incrementing = false;
    
    public function getIdentifier($entity): mixed
    {
        return $entity->getUserId();
    }
    
    public function setIdentifier($entity, $identifier): void
    {
        $entity->setUserId($identifier);
    }
}
```

### Custom Timestamps

For applications with specific timestamp requirements:

```php
class UserMapper extends Mapper
{
    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
    
    protected string $timestampFormat = 'Y-m-d H:i:s.u'; // Microseconds
    
    protected function currentTime(): DateTime
    {
        return new DateTime('now', new DateTimeZone('UTC'));
    }
    
    // Optional: Custom timestamp setters for entities
    protected function setCreatedAtTimestampOnEntity($entity, DateTime $timestamp): void
    {
        $entity->setDateCreated($timestamp);
    }
    
    protected function setUpdatedAtTimestampOnEntity($entity, DateTime $timestamp): void
    {
        $entity->setDateModified($timestamp);
    }
}
```

## Mapper Scopes

Add reusable query logic directly to your mapper:

```php
class UserMapper extends Mapper
{
    /**
     * Scope to filter active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    /**
     * Scope to filter users by role
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }
    
    /**
     * Scope to filter users created in date range
     */
    public function scopeCreatedBetween($query, DateTime $start, DateTime $end)
    {
        return $query->whereBetween('created_at', [
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        ]);
    }
}

// Usage
$activeAdmins = $userMapper->active()->withRole('admin')->get();
$recentUsers = $userMapper->createdBetween(
    new DateTime('-30 days'),
    new DateTime()
)->get();
```

## Global Scopes

Apply conditions to all queries automatically:

```php
class UserMapper extends Mapper
{
    public function __construct()
    {
        parent::__construct();
        
        // Add global scope for multi-tenant application
        static::addGlobalScope('tenant', function($builder) {
            $builder->where('tenant_id', auth()->user()->tenant_id);
        });
        
        // Add global scope to exclude deleted users
        static::addGlobalScope('active', function($builder) {
            $builder->where('deleted_at', null);
        });
    }
}

// Remove global scope for specific query
$allUsers = $userMapper->newQueryWithoutScope('active')->get();
```

## What NOT to Put in Mappers

Mappers should handle **persistence only**. Here are common anti-patterns to avoid:

### ❌ Anti-Pattern: Validation in Mapper

```php
// DON'T DO THIS!
class UserMapper extends Mapper
{
    protected function storeEntity($entity): bool
    {
        // This is WRONG - validation belongs in entity constructor
        if (empty($entity->name)) {
            throw new InvalidArgumentException('Name required');
        }
        
        return parent::storeEntity($entity);
    }
}
```

**✅ Correct: Validation in Entity**

```php
class User extends Entity
{
    public function __construct(string $name, Email $email)
    {
        // Validation happens at CREATION time
        if (empty($name)) {
            throw new InvalidArgumentException('Name required');
        }
        
        $this->name = $name;
        $this->email = $email;
    }
}
```

### ❌ Anti-Pattern: Business Logic in Mapper

```php
// DON'T DO THIS!
class OrderMapper extends Mapper
{
    public function save($entity): bool
    {
        // This is WRONG - business logic belongs in entity
        if ($entity->getTotal() > 1000) {
            $entity->setStatus('requires_approval');
        }
        
        return parent::save($entity);
    }
}
```

**✅ Correct: Business Logic in Entity**

```php
class Order extends Entity
{
    public function setTotal(Money $total): void
    {
        $this->total = $total;
        
        // Business logic stays in entity
        if ($total->greaterThan(Money::USD(1000))) {
            $this->status = OrderStatus::RequiresApproval;
        }
    }
}
```

### ❌ Anti-Pattern: Cache Manipulation in Mapper

```php
// DON'T DO THIS!
class UserMapper extends Mapper
{
    public function save($entity): bool
    {
        $result = parent::save($entity);
        
        // This is WRONG - cache concerns don't belong here
        Cache::forget("user.{$entity->id}");
        Cache::put("user.{$entity->id}", $entity, 3600);
        
        return $result;
    }
}
```

**✅ Correct: Use Events or Observers**

```php
// In a service provider or observer
Event::listen(EntitySaved::class, function($event) {
    if ($event->entity instanceof User) {
        Cache::forget("user.{$event->entity->id}");
    }
});
```

**Mapper Responsibilities:**
- ✅ Database queries
- ✅ Hydration/dehydration
- ✅ Relationship loading
- ✅ Type transformations
- ✅ Scopes

**NOT Mapper Responsibilities:**
- ❌ Validation
- ❌ Business logic
- ❌ Cache management
- ❌ Event dispatching
- ❌ Authorization
- ❌ Notifications

## Magic Accessor Pattern Base Mapper

The reference implementation provides a sophisticated base mapper that handles hydration, dehydration, and type transformations automatically. This is the **recommended pattern** for complex applications:

```php
<?php

namespace App\Mappers;

use stdClass;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Doctrine\Instantiator\Instantiator;
use CodeSleeve\Holloway\Mapper as HollowayMapper;

abstract class Mapper extends HollowayMapper
{
    /** @var array Type transformation registry */
    protected static array $maps = [];

    /** @var array Property-to-transformation mappings */
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
     * Register a type transformation
     */
    public static function addMapp(string $name, callable $hydrate, callable $dehydrate): void
    {
        static::$maps[$name] = compact('hydrate', 'dehydrate');
    }

    /**
     * Set all type transformations at once
     */
    public static function setMaps(array $maps): void
    {
        static::$maps = $maps;
    }

    /**
     * Hydrate: Database → Entity
     */
    public function hydrate(stdClass $record, Collection $relations)
    {
        $attributes = $this->mapValueObjects($record, $relations);
        
        // Instantiate WITHOUT calling constructor
        $entity = $this->instantiator->instantiate($this->entityClassName);
        
        // Fill properties directly
        return $entity->mapperFill($attributes);
    }

    /**
     * Dehydrate: Entity → Database
     */
    public function dehydrate($entity): array
    {
        $attributes = $entity->toArray();
        
        // Remove relationships
        $attributes = Arr::except($attributes, 
            array_map(fn($relationship) => $relationship->getName(), $this->relationships)
        );
        
        // Remove null id for inserts
        if (!$attributes['id']) {
            unset($attributes['id']);
        }
        
        // Apply dehydration transformations
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
     * Apply hydration transformations
     */
    protected function mapValueObjects(stdClass $record, Collection $relations): array
    {
        $attributes = array_merge((array) $record, $relations->all());
        
        // Apply transformations
        foreach($this->mappings as $propertyName => $map) {
            if (isset(static::$maps[$map])) {
                try {
                    $attributes[$propertyName] = call_user_func_array(
                        static::$maps[$map]['hydrate'], 
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

**Using the base mapper:**

```php
class UserMapper extends Mapper
{
    protected string $table = 'users';
    protected string $entityClassName = User::class;
    
    // Declare type transformations
    protected array $mappings = [
        'email' => 'email',
        'profile_image' => 'url',
        'settings' => 'json',
        'created_at' => 'date_time',
    ];
    
    public function defineRelations(): void
    {
        $this->hasMany('posts', Post::class);
        $this->belongsTo('company', Company::class);
    }
    
    // That's it! No need for manual hydrate/dehydrate
}
```

**Benefits:**
- ✅ Automatic type transformations
- ✅ DRY - no repetitive hydration code
- ✅ Consistent across all mappers
- ✅ Scales to complex entities
- ✅ Separates creation from hydration (see [Entity Lifecycle](../core-concepts/entity-lifecycle.md))

For more details, see:
- **[Type Transformations](../core-concepts/type-transformations.md)** - How the mappings system works
- **[Entity Hydration](../core-concepts/entity-hydration.md)** - Different hydration patterns

## Testing Your Mappers

Create testable mappers with dependency injection:

```php
class UserMapper extends Mapper
{
    private TimeProvider $timeProvider;
    
    public function __construct(TimeProvider $timeProvider = null)
    {
        parent::__construct();
        $this->timeProvider = $timeProvider ?? new SystemTimeProvider();
    }
    
    protected function currentTime(): DateTime
    {
        return $this->timeProvider->now();
    }
}

// In tests, inject a mock time provider
$mockTimeProvider = new MockTimeProvider(new DateTime('2023-01-01'));
$mapper = new UserMapper($mockTimeProvider);
```

## Common Patterns

### Soft Deletes

```php
use CodeSleeve\Holloway\SoftDeletes;

class UserMapper extends Mapper
{
    use SoftDeletes;
    
    protected string $deletedAt = 'deleted_at';
}
```

### Multi-Database Support

```php
class UserMapper extends Mapper
{
    protected string $connection = 'users_db';
    
    public function useConnection(string $connection): self
    {
        $this->connection = $connection;
        return $this;
    }
}

// Usage
$userMapper->useConnection('replica_db')->all();
```

### Multi-Tenant Support

```php
// Using a trait for tenant-aware queries
class UserMapper extends Mapper
{
    public function __construct()
    {
        parent::__construct();
        
        // Global scope ensures all queries are tenant-specific
        static::addGlobalScope('tenant', function($builder) {
            if ($tenantId = auth()->user()?->tenant_id) {
                $builder->where('tenant_id', $tenantId);
            }
        });
    }
}
```

### Custom Helper Methods

Mappers can have helper methods for common queries:

```php
class UserMapper extends Mapper
{
    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->where('email', $email)->first();
    }
    
    /**
     * Find active users
     */
    public function findActive(): Collection
    {
        return $this->where('is_active', true)->get();
    }
    
    /**
     * Get users created in date range
     */
    public function createdBetween(DateTime $start, DateTime $end): Collection
    {
        return $this->whereBetween('created_at', [
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        ])->get();
    }
}
```

**These are fine because they:**
- ✅ Only handle queries
- ✅ Don't contain business logic
- ✅ Make your API more expressive

## Next Steps

- **[Query Building](./query-building.md)** - Learn advanced querying techniques
- **[Persistence Operations](./persistence.md)** - Master entity storage and retrieval
- **[Scopes](./scopes.md)** - Implement reusable query logic
