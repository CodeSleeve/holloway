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
- [Global Scopes](#global-scopes)

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
    
    public function hydrate(stdClass $record, Collection $relations)
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
public function hydrate(stdClass $record, Collection $relations)
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

public function hydrate(stdClass $record, Collection $relations)
{
    // Prepare attributes with type transformations
    $attributes = $this->mapValueObjects($record, $relations);
    
    // Instantiate WITHOUT calling constructor (bypasses validation)
    $entity = $this->instantiator->instantiate($this->entityClassName);
    
    // Fill properties directly
    return $entity->mapperFill($attributes);
}
```

> **Note:** `Holloway\Mapper` declares `protected Instantiator $instantiator` but does not initialize it, and `doctrine/instantiator` is not installed with Holloway. Install it in your application and assign `$this->instantiator` in your base mapper's constructor before calling `instantiateEntity()` or the example above (see [Base Classes](../core-concepts/base-classes.md)).

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

## Next Steps

- **[Query Building](./query-building.md)** - Learn advanced querying techniques
- **[Persistence Operations](./persistence.md)** - Master entity storage and retrieval
- **[Scopes](./scopes.md)** - Implement reusable query logic
