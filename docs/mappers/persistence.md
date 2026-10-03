# Persistence Operations

## Table of Contents

- [Timestamp Handling](#timestamp-handling)
- [Core Persistence Methods](#core-persistence-methods)
- [Persistence Lifecycle](#persistence-lifecycle)
- [Timestamp Management](#timestamp-management)
- [Transaction Support](#transaction-support)
- [Factory Integration](#factory-integration)
- [Next Steps](#next-steps)

## Timestamp Handling

When persisting entities, Holloway uses the `currentTime()` method on your mapper to set `created_at`, `updated_at`, and (if using soft deletes) `deleted_at` columns. You can override this method to control how timestamps are generated (e.g., for custom time zones or deterministic tests).


Persistence operations in Holloway handle the storage, updating, and removal of entities through mappers. Unlike Active Record patterns, persistence is explicit and controlled through the mapper, providing clear separation between domain logic and data access.

## Core Persistence Methods

### Store Operations

The `store()` method handles both INSERT and UPDATE operations automatically:
// Example: Customizing timestamp behavior
class MyMapper extends Mapper
{
    protected function currentTime(): \DateTime
    {
        // Always use a fixed time for tests
        return new \DateTime('2020-01-01 00:00:00', new \DateTimeZone('UTC'));
    }
}

```php
$userMapper = Holloway::instance()->getMapper(User::class);

// Create new entity
$user = new User('John Doe', 'john@example.com');

// Persist to database (INSERT)
$userMapper->store($user);
echo $user->getId(); // Auto-generated ID is set on entity

// Modify entity
$user->updateEmail('newemail@example.com');

// Persist changes (UPDATE)
$userMapper->store($user);
```

### Remove Operations

The `remove()` method handles entity deletion:

```php
// Remove single entity
$user = $userMapper->find(1);
$userMapper->remove($user);

// Remove collection of entities
$inactiveUsers = $userMapper->where('active', false)->get();
$userMapper->remove($inactiveUsers);
```

### Batch Operations

Process multiple entities efficiently:

```php
// Store multiple entities
$users = [
    new User('Alice', 'alice@example.com'),
    new User('Bob', 'bob@example.com'),
    new User('Charlie', 'charlie@example.com')
];

$userMapper->store($users); // Wrapped in transaction

// Remove multiple entities
$expiredUsers = $userMapper->where('expires_at', '<', now())->get();
$userMapper->remove($expiredUsers); // Wrapped in transaction
```

## Persistence Lifecycle

### Entity State Detection

Holloway automatically determines whether to INSERT or UPDATE:

```php
// New entity (no ID) → INSERT
$user = new User('John', 'john@example.com');
$userMapper->store($user); // INSERT operation

// Existing entity (has ID) → UPDATE
$existingUser = $userMapper->find(1);
$existingUser->updateName('John Smith');
$userMapper->store($existingUser); // UPDATE operation
```

### Change Detection

Holloway remembers the attributes of each row it loads in the mapper's entity cache, and `store()` compares them with the entity's `dehydrate()` output:

```php
// Load entity (the row's attributes are cached)
$user = $userMapper->find(1);

$user->updateName('John Smith');

// store() issues an UPDATE for the entity
$userMapper->store($user);
// UPDATE users SET id = 1, name = 'John Smith', email = 'john@example.com', ... WHERE id = 1
```

Holloway does not diff individual columns: an update writes **all** dehydrated attributes. The `UPDATE` is skipped only when `dehydrate()` returns an array identical (strict `!==` comparison) to the cached attributes, which in the current implementation is uncommon, so expect an `UPDATE` on every `store()` of an existing entity. See [Entity Caching](../advanced/caching.md#what-store-does-with-it).

## Timestamp Management

### Automatic Timestamps

Holloway automatically manages created_at and updated_at timestamps:

```php
class UserMapper extends Mapper
{
    protected bool $hasTimestamps = true;
    protected string $timestampFormat = 'Y-m-d H:i:s';
    
    // Timestamps are automatically added/updated during persistence
    // created_at: Set on INSERT
    // updated_at: Set on INSERT and UPDATE
}
```

### Custom Timestamp Handling

```php
class UserMapper extends Mapper
{
    protected function currentTime(): DateTime
    {
        // Custom time provider (useful for testing)
        return new DateTime('now', new DateTimeZone('UTC'));
    }
    
    // Optional: Set timestamps on entities during persistence
    protected function setCreatedAtTimestampOnEntity($entity, DateTime $timestamp): void
    {
        $entity->setCreatedAt($timestamp);
    }
    
    protected function setUpdatedAtTimestampOnEntity($entity, DateTime $timestamp): void
    {
        $entity->setUpdatedAt($timestamp);
    }
}
```

### Custom Timestamp Columns

```php
class UserMapper extends Mapper
{
    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
    
    public function getCreatedAtColumnName(): string
    {
        return static::CREATED_AT;
    }
    
    public function getUpdatedAtColumnName(): string
    {
        return static::UPDATED_AT;
    }
}
```

## Transaction Support

### Automatic Transactions

Holloway automatically wraps batch operations in transactions:

```php
// Automatic transaction for collections
$users = [
    new User('Alice', 'alice@example.com'),
    new User('Bob', 'bob@example.com'),
    new User('Charlie', 'charlie@example.com')
];

$userMapper->store($users);
// Equivalent to:
// DB::transaction(function() use ($users, $userMapper) {
//     foreach ($users as $user) {
//         $userMapper->store($user);
//     }
// });
```

## Factory Integration

### Factory Creation

Holloway integrates with Laravel-style factories:

```php
$userMapper = Holloway::instance()->getMapper(User::class);

// Using legacy factory syntax (pre-Laravel 8)
// Create single entity
$user = factory(User::class)->create();

// Create multiple entities
$users = factory(User::class, 10)->create();

// Create with specific attributes
$admin = factory(User::class)->state('admin')->create();
```

### Factory Implementation

```php
class UserFactory extends Factory
{
    protected string $mapper = UserMapper::class;
    
    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'role' => UserRole::User,
        ];
    }
    
    public function admin(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'role' => UserRole::Admin,
            ];
        });
    }
    
    public function withProfile(): self
    {
        return $this->afterCreating(function (User $user) {
            $profileMapper = Holloway::instance()->getMapper(UserProfile::class);
            $profile = new UserProfile($user->getId(), $this->faker->text(200));
            $profileMapper->store($profile);
        });
    }
}
```

### Custom Factory Insert

```php
class UserMapper extends Mapper
{
    public function factoryInsert($entity): bool
    {
        // Custom logic for factory creation
        // By default, this just calls store()
        
        // Example: Skip validation for test data
        $this->skipValidation = true;
        $result = $this->store($entity);
        $this->skipValidation = false;
        
        return $result;
    }
}
```

## Next Steps

- **[Scopes](./scopes.md)** - Master global and local query scopes
- **[Soft Deletes](../advanced/soft-deletes.md)** - Implement soft deletion functionality
- **[Events & Hooks](../advanced/events.md)** - Advanced lifecycle event handling
