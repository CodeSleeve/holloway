# Creating Mappers

Mappers are the core component of Holloway's datamapper pattern. They handle all database operations, entity hydration/dehydration, and relationship management. This guide covers everything you need to know about creating and configuring mappers.

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

The `dehydrate()` method converts an entity instance into an array suitable for database storage:

```php
public function dehydrate($entity): array
{
    return [
        'id' => $entity->getId(),
        'name' => $entity->getName(),
        'email' => $entity->getEmail(),
        'role' => $entity->getRole()->value, // Enum to string
        'settings' => json_encode($entity->getSettings()), // Object to JSON
        'is_active' => $entity->isActive(),
    ];
}
```

**Best Practices:**
- Handle type conversions (objects to strings, enums to values)
- Exclude computed properties
- Include only persistable data
- Handle null values gracefully

### Hydration: Database → Entity

The `hydrate()` method converts a database record into an entity instance:

```php
public function hydrate($record, $relations = null)
{
    // Create entity using constructor
    $entity = new User(
        $record->name,
        $record->email,
        UserRole::from($record->role)
    );
    
    // Set internal properties
    if (isset($record->id)) {
        $entity->setId($record->id);
    }
    
    if (isset($record->created_at)) {
        $entity->setCreatedAt(new \DateTime($record->created_at));
    }
    
    // Handle JSON fields
    if (isset($record->settings)) {
        $settings = json_decode($record->settings, true);
        $entity->setSettings($settings);
    }
    
    // Attach relationships
    if ($relations && isset($relations['profile'])) {
        $entity->setProfile($relations['profile']);
    }
    
    return $entity;
}
```

**Best Practices:**
- Use entity constructor for required properties
- Use setter methods for optional/internal properties
- Handle type conversions (strings to objects, JSON to arrays)
- Validate data integrity where appropriate
- Attach loaded relationships

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

## Entity Cache Configuration

Control how entities are cached for performance:

```php
class UserMapper extends Mapper
{
    public function __construct()
    {
        parent::__construct();
        
        // Custom cache key (default uses primary key)
        $this->entityCache = new EntityCache('id');
    }
    
    /**
     * Custom entity instantiation
     */
    public function instantiateEntity(array $attributes)
    {
        // Use custom instantiation logic if needed
        return $this->instantiator->instantiate($this->entityClassName);
    }
    
    /**
     * Clear cache after bulk operations
     */
    protected function afterBulkOperation(): void
    {
        $this->clearEntityCache();
    }
}
```

## Validation and Business Rules

Implement validation within your mapper:

```php
class UserMapper extends Mapper
{
    protected function validateEntity($entity): void
    {
        if (empty($entity->getName())) {
            throw new InvalidArgumentException('User name is required');
        }
        
        if (!filter_var($entity->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        // Check for unique email
        if ($this->emailExists($entity->getEmail(), $entity->getId())) {
            throw new InvalidArgumentException('Email already exists');
        }
    }
    
    private function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = $this->where('email', $email);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->exists();
    }
    
    protected function storeEntity($entity): bool
    {
        $this->validateEntity($entity);
        
        return parent::storeEntity($entity);
    }
}
```

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

### Audit Logging

```php
class UserMapper extends Mapper
{
    protected function storeEntity($entity): bool
    {
        $isUpdate = $this->getIdentifier($entity) !== null;
        
        $result = parent::storeEntity($entity);
        
        if ($result) {
            $this->logAuditEvent($isUpdate ? 'updated' : 'created', $entity);
        }
        
        return $result;
    }
    
    private function logAuditEvent(string $action, $entity): void
    {
        // Log the action for audit purposes
    }
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

## Next Steps

- **[Query Building](./query-building.md)** - Learn advanced querying techniques
- **[Persistence Operations](./persistence.md)** - Master entity storage and retrieval
- **[Scopes](./scopes.md)** - Implement reusable query logic
