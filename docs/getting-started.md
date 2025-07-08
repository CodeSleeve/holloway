# Getting Started with Holloway

Holloway is a datamapper pattern implementation that provides complete separation between your domain entities and database persistence. This guide will get you up and running quickly.

## Installation

Install Holloway via Composer:

```bash
composer require codesleeve/holloway
```

## Basic Concepts

### The Datamapper Pattern

Unlike Active Record (used by Eloquent), the datamapper pattern separates your domain objects (entities) from database logic (mappers):

- **Entities** - Pure domain objects with business logic, no database knowledge
- **Mappers** - Handle all database operations and entity hydration/dehydration

### Key Benefits

- **Domain-driven design** - Entities focus purely on business logic
- **Unbreakable entities** - Complete control over entity construction and validation
- **Testability** - Entities can be tested without database dependencies
- **Performance** - Built-in entity caching and optimized relationship loading

## Your First Entity

Create a simple entity class:

```php
<?php

namespace App\Entities;

class User
{
    private int $id;
    private string $name;
    private string $email;
    private \DateTime $createdAt;

    public function __construct(string $name, string $email)
    {
        if (empty($name) || empty($email)) {
            throw new \InvalidArgumentException('Name and email are required');
        }
        
        $this->name = $name;
        $this->email = $email;
        $this->createdAt = new \DateTime();
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

    public function updateEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }
        
        $this->email = $email;
    }

    // Internal methods for mapper use
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
```

## Your First Mapper

Create a corresponding mapper:

```php
<?php

namespace App\Mappers;

use App\Entities\User;
use CodeSleeve\Holloway\Mapper;

class UserMapper extends Mapper
{
    protected string $table = 'users';
    protected string $entityClassName = User::class;
    protected string $primaryKey = 'id';
    protected bool $hasTimestamps = true;

    /**
     * Define relationships for this mapper
     */
    public function defineRelations(): void
    {
        // We'll add relationships later
    }

    /**
     * Convert database record to entity attributes
     */
    public function dehydrate($entity): array
    {
        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'email' => $entity->getEmail(),
        ];
    }

    /**
     * Convert entity attributes to domain entity
     */
    public function hydrate($record, $relations = null)
    {
        $entity = new User($record->name, $record->email);
        
        if (isset($record->id)) {
            $entity->setId($record->id);
        }
        
        if (isset($record->created_at)) {
            $entity->setCreatedAt(new \DateTime($record->created_at));
        }

        return $entity;
    }
}
```

## Registration and Usage

### Register Your Mappers

In your Laravel service provider or bootstrap file:

```php
use CodeSleeve\Holloway\Holloway;
use App\Mappers\UserMapper;

// Register mappers with Holloway
Holloway::instance()->register([
    UserMapper::class,
]);
```

### Basic Operations

```php
use CodeSleeve\Holloway\Holloway;
use App\Entities\User;

// Get a mapper instance
$userMapper = Holloway::instance()->getMapper(User::class);

// Create a new user
$user = new User('John Doe', 'john@example.com');

// Persist to database
$userMapper->store($user);

// Query users
$users = $userMapper->all();
$user = $userMapper->find(1);
$users = $userMapper->where('name', 'John Doe')->get();

// Update user
$user->updateEmail('newemail@example.com');
$userMapper->store($user); // Will perform UPDATE

// Remove user
$userMapper->remove($user);
```

## Laravel Integration

If you're using Laravel, Holloway integrates seamlessly:

### Service Provider

Holloway includes a service provider that auto-registers with Laravel:

```php
// config/app.php (if not using auto-discovery)
'providers' => [
    // ...
    CodeSleeve\Holloway\HollowayServiceProvider::class,
],
```

### Database Configuration

Holloway uses Laravel's database configuration automatically:

```php
// Uses default connection
$userMapper = Holloway::instance()->getMapper(User::class);

// Use specific connection
$userMapper->setConnection('tenant_db');
```

## Configuration

### Mapper Configuration Options

```php
class UserMapper extends Mapper
{
    // Database table name
    protected string $table = 'users';
    
    // Entity class this mapper handles
    protected string $entityClassName = User::class;
    
    // Primary key column name
    protected string $primaryKey = 'id';
    
    // Primary key type
    protected string $keyType = 'int';
    
    // Whether primary key auto-increments
    protected bool $incrementing = true;
    
    // Whether to manage created_at/updated_at timestamps
    protected bool $hasTimestamps = true;
    
    // Timestamp format
    protected string $timestampFormat = 'Y-m-d H:i:s';
    
    // Database connection name
    protected string $connection = '';
    
    // Default pagination size
    protected int $perPage = 15;
    
    // Relationships to eager load by default
    protected array $with = [];
}
```

## Next Steps

Now that you have the basics:

1. **[Learn about Architecture](./architecture.md)** - Understand Holloway's design patterns
2. **[Explore Relationships](./relationships/overview.md)** - Connect your entities together
3. **[Master Query Building](./mappers/query-building.md)** - Advanced querying techniques
4. **[Set Up Testing](./advanced/factories.md)** - Create test factories for your entities

## Common Patterns

### Entity Validation

```php
class User
{
    public function updateEmail(string $email): void
    {
        if (!$this->isValidEmail($email)) {
            throw new \InvalidArgumentException('Invalid email format');
        }
        
        $this->email = $email;
    }

    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
```

### Mapper Scopes

```php
class UserMapper extends Mapper
{
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}

// Usage
$activeUsers = $userMapper->active()->get();
$admins = $userMapper->byRole('admin')->get();
```

### Repository Pattern

```php
class UserRepository
{
    private UserMapper $mapper;

    public function __construct()
    {
        $this->mapper = Holloway::instance()->getMapper(User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->mapper->where('email', $email)->first();
    }

    public function findActiveUsers(): Collection
    {
        return $this->mapper->active()->get();
    }
}
```
