# Persistence Operations
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

### Dirty Tracking

Holloway tracks entity changes through the entity cache:

```php
// Load entity (cached attributes stored)
$user = $userMapper->find(1);
// Cache: ['id' => 1, 'name' => 'John', 'email' => 'john@example.com']

// Modify entity
$user->updateName('John Smith');
$user->updateEmail('johnsmith@example.com');

// Store operation compares with cache
$userMapper->store($user);
// Only changed fields are updated:
// UPDATE users SET name = 'John Smith', email = 'johnsmith@example.com' WHERE id = 1

// No-op if no changes
$userMapper->store($user); // No database query - nothing changed
```

## Implementing Persistence Methods

### Dehydration: Entity → Database

Convert entities to database-compatible arrays:

```php
class UserMapper extends Mapper
{
    public function dehydrate($entity): array
    {
        $attributes = [
            'name' => $entity->getName(),
            'email' => $entity->getEmail(),
            'active' => $entity->isActive(),
        ];
        
        // Handle nullable fields
        if ($entity->getPhone()) {
            $attributes['phone'] = $entity->getPhone();
        }
        
        // Handle enums/value objects
        if ($entity->getRole()) {
            $attributes['role'] = $entity->getRole()->value;
        }
        
        // Handle JSON fields
        if ($entity->getSettings()) {
            $attributes['settings'] = json_encode($entity->getSettings());
        }
        
        // Don't include computed properties
        // $attributes['full_name'] = $entity->getFullName(); // NO!
        
        return $attributes;
    }
}
```

### Hydration: Database → Entity

Convert database records to entities:

```php
class UserMapper extends Mapper
{
    public function hydrate($record, $relations = null)
    {
        // Create entity with required fields
        $entity = new User($record->name, $record->email);
        
        // Set identifier
        if (isset($record->id)) {
            $entity->setId($record->id);
        }
        
        // Set optional fields
        if (isset($record->phone)) {
            $entity->setPhone($record->phone);
        }
        
        // Handle enums/value objects
        if (isset($record->role)) {
            $entity->setRole(UserRole::from($record->role));
        }
        
        // Handle JSON fields
        if (isset($record->settings)) {
            $settings = json_decode($record->settings, true);
            $entity->setSettings($settings);
        }
        
        // Handle timestamps
        if (isset($record->created_at)) {
            $entity->setCreatedAt(new DateTime($record->created_at));
        }
        
        // Attach relationships
        if ($relations) {
            if (isset($relations['profile'])) {
                $entity->setProfile($relations['profile']);
            }
            
            if (isset($relations['posts'])) {
                $entity->setPosts($relations['posts']);
            }
        }
        
        return $entity;
    }
}
```

### Identifier Management

Handle entity identifiers properly:

```php
class UserMapper extends Mapper
{
    public function getIdentifier($entity): mixed
    {
        return $entity->getId();
    }
    
    public function setIdentifier($entity, $identifier): void
    {
        $entity->setId($identifier);
    }
}

// For composite keys
class OrderItemMapper extends Mapper
{
    public function getIdentifier($entity): string
    {
        return $entity->getOrderId() . ':' . $entity->getProductId();
    }
    
    public function setIdentifier($entity, $identifier): void
    {
        [$orderId, $productId] = explode(':', $identifier);
        $entity->setOrderId((int) $orderId);
        $entity->setProductId((int) $productId);
    }
}
```

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

### Manual Transaction Control

```php
use Illuminate\Support\Facades\DB;

// Manual transaction for complex operations
DB::transaction(function() use ($userMapper, $postMapper) {
    // Create user
    $user = new User('John', 'john@example.com');
    $userMapper->store($user);
    
    // Create related posts
    $post1 = new Post($user->getId(), 'First Post', 'Content...');
    $post2 = new Post($user->getId(), 'Second Post', 'Content...');
    
    $postMapper->store([$post1, $post2]);
    
    // If any operation fails, entire transaction is rolled back
});
```

### Transaction Error Handling

```php
try {
    DB::transaction(function() use ($userMapper) {
        $users = collect();
        
        for ($i = 0; $i < 1000; $i++) {
            $user = new User("User {$i}", "user{$i}@example.com");
            $users->push($user);
        }
        
        $userMapper->store($users->toArray());
    });
    
    echo "All users created successfully";
    
} catch (Exception $e) {
    Log::error("Batch user creation failed: " . $e->getMessage());
    echo "User creation failed, all changes rolled back";
}
```

## Factory Integration

### Factory Creation

Holloway integrates with Laravel-style factories:

```php
$userMapper = Holloway::instance()->getMapper(User::class);

// Create single entity
$user = UserFactory::new()->create();

// Create multiple entities
$users = UserFactory::new()->count(10)->create();

// Create with specific attributes
$admin = UserFactory::new()->admin()->create();
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

## Advanced Persistence Patterns

### Bulk Operations

```php
class UserMapper extends Mapper
{
    public function bulkInsert(array $users): bool
    {
        $records = array_map(function($user) {
            return $this->dehydrate($user);
        }, $users);
        
        // Add timestamps
        $now = $this->currentTime()->format($this->timestampFormat);
        foreach ($records as &$record) {
            $record['created_at'] = $now;
            $record['updated_at'] = $now;
        }
        
        // Bulk insert
        $this->getConnection()->table($this->getTable())->insert($records);
        
        return true;
    }
    
    public function bulkUpdate(array $users): bool
    {
        $this->getConnection()->transaction(function() use ($users) {
            foreach ($users as $user) {
                $this->storeEntity($user);
            }
        });
        
        return true;
    }
}
```

### Upsert Operations

```php
class UserMapper extends Mapper
{
    public function upsert($entity): bool
    {
        $attributes = $this->dehydrate($entity);
        
        // MySQL upsert
        $this->getConnection()->table($this->getTable())->updateOrInsert(
            ['email' => $attributes['email']], // Match condition
            $attributes // Values to insert/update
        );
        
        return true;
    }
}
```

### Conditional Persistence

```php
class UserMapper extends Mapper
{
    protected function storeEntity($entity): bool
    {
        // Custom validation before persistence
        if (!$this->validateEntity($entity)) {
            return false;
        }
        
        // Custom business rules
        if ($this->isDuplicateEmail($entity)) {
            throw new DuplicateEmailException($entity->getEmail());
        }
        
        return parent::storeEntity($entity);
    }
    
    private function validateEntity($entity): bool
    {
        // Entity-specific validation logic
        return !empty($entity->getName()) && 
               filter_var($entity->getEmail(), FILTER_VALIDATE_EMAIL);
    }
    
    private function isDuplicateEmail($entity): bool
    {
        $existing = $this->where('email', $entity->getEmail());
        
        if ($entity->getId()) {
            $existing->where('id', '!=', $entity->getId());
        }
        
        return $existing->exists();
    }
}
```

## Persistence Events

### Lifecycle Events

Holloway fires events during entity persistence:

```php
class UserMapper extends Mapper
{
    public function __construct()
    {
        parent::__construct();
        
        // Register event listeners
        $this->registerPersistenceEvent('creating', function($user) {
            Log::info("Creating user: {$user->getEmail()}");
            
            // Modify entity before creation
            if (!$user->getRole()) {
                $user->setRole(UserRole::User);
            }
        });
        
        $this->registerPersistenceEvent('created', function($user) {
            Log::info("Created user: {$user->getId()}");
            
            // Send welcome email
            Mail::to($user->getEmail())->send(new WelcomeEmail($user));
        });
        
        $this->registerPersistenceEvent('updating', function($user) {
            Log::info("Updating user: {$user->getId()}");
        });
        
        $this->registerPersistenceEvent('updated', function($user) {
            Log::info("Updated user: {$user->getId()}");
            
            // Clear cache
            Cache::forget("user.{$user->getId()}");
        });
        
        $this->registerPersistenceEvent('removing', function($user) {
            Log::info("Removing user: {$user->getId()}");
        });
        
        $this->registerPersistenceEvent('removed', function($user) {
            Log::info("Removed user: {$user->getId()}");
            
            // Clean up related data
            $this->cleanupUserData($user);
        });
    }
    
    private function cleanupUserData($user): void
    {
        // Remove user's files, clear caches, etc.
    }
}
```

### Event Return Values

```php
class UserMapper extends Mapper
{
    public function __construct()
    {
        parent::__construct();
        
        $this->registerPersistenceEvent('storing', function($user) {
            // Returning false cancels the operation
            if ($user->isBanned()) {
                Log::warning("Attempted to store banned user: {$user->getId()}");
                return false; // Prevents storage
            }
        });
    }
}
```

## Error Handling

### Persistence Exceptions

```php
try {
    $userMapper->store($user);
} catch (QueryException $e) {
    // Database constraint violations, etc.
    if ($e->getCode() === '23000') { // Integrity constraint violation
        throw new DuplicateEmailException($user->getEmail());
    }
    
    throw $e;
} catch (Exception $e) {
    Log::error("Failed to store user: " . $e->getMessage());
    throw new PersistenceException("Unable to save user", 0, $e);
}
```

### Validation Errors

```php
class UserMapper extends Mapper
{
    protected function storeEntity($entity): bool
    {
        $errors = $this->validateEntity($entity);
        
        if (!empty($errors)) {
            throw new ValidationException('Entity validation failed', $errors);
        }
        
        return parent::storeEntity($entity);
    }
    
    private function validateEntity($entity): array
    {
        $errors = [];
        
        if (empty($entity->getName())) {
            $errors['name'] = 'Name is required';
        }
        
        if (!filter_var($entity->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }
        
        return $errors;
    }
}
```

## Best Practices

### 1. Keep Dehydration/Hydration Simple

```php
// Good: Simple, direct mapping
public function dehydrate($entity): array
{
    return [
        'name' => $entity->getName(),
        'email' => $entity->getEmail(),
        'active' => $entity->isActive(),
    ];
}

// Avoid: Complex logic in dehydration
public function dehydrate($entity): array
{
    // Don't do complex calculations here
    $data = [
        'name' => $entity->getName(),
        'email' => $entity->getEmail(),
    ];
    
    // This should be in the entity or a service
    $data['score'] = $this->calculateComplexScore($entity);
    
    return $data;
}
```

### 2. Use Transactions for Related Operations

```php
// Good: Group related operations in transactions
DB::transaction(function() use ($userMapper, $profileMapper) {
    $user = new User('John', 'john@example.com');
    $userMapper->store($user);
    
    $profile = new UserProfile($user->getId(), 'Bio...');
    $profileMapper->store($profile);
});

// Avoid: Separate operations without transaction protection
$user = new User('John', 'john@example.com');
$userMapper->store($user);

$profile = new UserProfile($user->getId(), 'Bio...');
$profileMapper->store($profile); // Could fail, leaving orphaned user
```

### 3. Handle Identifiers Properly

```php
// Good: Check for identifier existence
public function getIdentifier($entity): mixed
{
    $id = $entity->getId();
    return $id !== null ? $id : null;
}

// Avoid: Assuming identifier always exists
public function getIdentifier($entity): mixed
{
    return $entity->getId(); // Could be null for new entities
}
```

### 4. Validate Before Persistence

```php
// Good: Validate at entity level AND mapper level
class User
{
    public function updateEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        $this->email = $email;
    }
}

class UserMapper extends Mapper
{
    protected function storeEntity($entity): bool
    {
        // Additional business rule validation
        if ($this->emailExists($entity->getEmail(), $entity->getId())) {
            throw new DuplicateEmailException($entity->getEmail());
        }
        
        return parent::storeEntity($entity);
    }
}
```

Persistence operations in Holloway provide explicit, controlled data storage with clear separation between domain logic and persistence concerns. The system's dirty tracking, automatic transaction support, and event hooks enable robust, maintainable applications.

## Next Steps

- **[Scopes](./scopes.md)** - Master global and local query scopes
- **[Soft Deletes](../advanced/soft-deletes.md)** - Implement soft deletion functionality
- **[Events & Hooks](../advanced/events.md)** - Advanced lifecycle event handling
