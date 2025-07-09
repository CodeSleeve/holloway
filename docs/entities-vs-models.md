# Entities vs Models: Understanding the Paradigm Shift

Moving from Active Record (Eloquent models) to Datamapper (Holloway entities) represents a fundamental shift in how you structure and think about your domain objects. This guide explains the key differences and benefits.

## Conceptual Differences

### Active Record Pattern (Eloquent)

```php
// Eloquent Model - Active Record Pattern
class User extends Model
{
    protected $fillable = ['name', 'email'];
    
    // Database logic mixed with domain logic
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
    
    // Business logic alongside persistence concerns
    public function promote()
    {
        $this->role = 'admin';
        $this->save(); // Direct database coupling
    }
}

// Usage
$user = User::find(1);           // Static method, database aware
$user->promote();                // Business logic + persistence
$posts = $user->posts;           // Lazy loading, potential N+1
```

### Datamapper Pattern (Holloway)

```php
// Holloway Entity - Pure Domain Object
class User
{
    private int $id;
    private string $name;
    private string $email;
    private UserRole $role;
    
    public function __construct(string $name, string $email, UserRole $role)
    {
        $this->validateEmail($email);
        $this->name = $name;
        $this->email = $email;
        $this->role = $role;
    }
    
    // Pure business logic, no database coupling
    public function promote(): void
    {
        if (!$this->canBePromoted()) {
            throw new InvalidOperationException('User cannot be promoted');
        }
        
        $this->role = UserRole::Admin;
        // No automatic persistence - intentional
    }
    
    private function canBePromoted(): bool
    {
        return $this->role !== UserRole::Admin && 
               $this->email !== null;
    }
    
    private function validateEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
    }
}

// Usage
$userMapper = Holloway::instance()->getMapper(User::class);
$user = $userMapper->with('posts')->find(1);  // Explicit loading
$user->promote();                              // Pure business logic
$userMapper->store($user);                     // Explicit persistence
```

## Key Differences

### 1. Domain Logic Purity

**Eloquent Models:**
```php
class User extends Model
{
    // Business logic mixed with framework concerns
    public function getFullNameAttribute()  // Accessor
    {
        return $this->first_name . ' ' . $this->last_name;
    }
    
    public function scopeActive($query)     // Query scope
    {
        return $query->where('active', true);
    }
    
    public function updateEmail($email)
    {
        $this->email = $email;
        $this->save();  // Database concern in business logic
    }
}
```

**Holloway Entities:**
```php
class User
{
    // Pure business logic
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }
    
    public function updateEmail(string $email): void
    {
        $this->validateEmail($email);
        $this->email = $email;
        // No persistence concern - that's the mapper's job
    }
    
    public function isActive(): bool
    {
        return $this->status === UserStatus::Active &&
               $this->deletedAt === null;
    }
    
    private function validateEmail(string $email): void
    {
        if (!Email::isValid($email)) {
            throw new InvalidEmailException($email);
        }
    }
}
```

### 2. Object Construction

**Eloquent Models:**
```php
// Framework controls construction
$user = new User();              // Empty object
$user->name = 'John';           // Property assignment
$user->email = 'invalid-email'; // No validation
$user->save();                  // May fail at persistence layer

// Or mass assignment with potential issues
$user = User::create([
    'name' => 'John',
    'email' => 'invalid-email'  // Validation happens later
]);
```

**Holloway Entities:**
```php
// Application controls construction
$user = new User(
    'John Doe',
    'john@example.com',
    UserRole::Member
);  // Validation happens at construction

// Invalid objects cannot be created
$user = new User('', 'invalid-email', UserRole::Member);
// ↑ Throws exception immediately
```

### 3. Relationship Loading

**Eloquent Models:**
```php
// Lazy loading with N+1 potential
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count(); // New query for each user
}

// Eager loading
$users = User::with('posts')->get();
foreach ($users as $user) {
    echo $user->posts->count(); // No additional queries
}
```

**Holloway Entities:**
```php
// Explicit loading only - no lazy loading
$userMapper = Holloway::instance()->getMapper(User::class);

// Must explicitly load relationships
$users = $userMapper->with('posts')->get();
foreach ($users as $user) {
    echo $user->posts->count(); // No additional queries possible
}

// Unloaded relationships are null/empty
$users = $userMapper->all();
foreach ($users as $user) {
    echo $user->posts; // null - clearly indicates not loaded
}
```

### 4. Testing

**Eloquent Models:**
```php
public function testUserPromotion()
{
    // Requires database for model testing
    $user = factory(User::class)->create(['role' => 'member']);
    
    $user->promote();
    
    $this->assertEquals('admin', $user->role);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'admin'
    ]);
}
```

**Holloway Entities:**
```php
public function testUserPromotion()
{
    // Pure unit test - no database required
    $user = new User('John', 'john@example.com', UserRole::Member);
    
    $user->promote();
    
    $this->assertEquals(UserRole::Admin, $user->getRole());
    // No database assertions needed for domain logic
}

public function testUserPersistence()
{
    // Separate test for persistence logic
    $user = new User('John', 'john@example.com', UserRole::Member);
    $mapper = new UserMapper();
    
    $mapper->store($user);
    
    $this->assertDatabaseHas('users', [
        'name' => 'John',
        'email' => 'john@example.com'
    ]);
}
```

## Benefits of the Datamapper Approach

### 1. Unbreakable Domain Objects

```php
// Holloway entities cannot exist in invalid states
class User
{
    public function __construct(string $name, string $email)
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Name cannot be empty');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        $this->name = $name;
        $this->email = $email;
    }
    
    public function changeEmail(string $email): void
    {
        // Validation always happens
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        $this->email = $email;
    }
}

// Impossible to create invalid user
$user = new User('', 'invalid'); // Throws exception immediately
```

### 2. True Unit Testing

```php
// Test domain logic without database
class UserTest extends TestCase
{
    public function testEmailValidation()
    {
        $this->expectException(InvalidArgumentException::class);
        
        new User('John', 'invalid-email');
    }
    
    public function testPromotion()
    {
        $user = new User('John', 'john@example.com');
        
        $user->promote();
        
        $this->assertTrue($user->isAdmin());
    }
    
    public function testCannotPromoteAdmin()
    {
        $user = new User('John', 'john@example.com');
        $user->promote(); // Now admin
        
        $this->expectException(InvalidOperationException::class);
        $user->promote(); // Cannot promote admin
    }
}
```

### 3. Explicit Relationship Loading

```php
// Always know what's loaded
$userMapper = Holloway::instance()->getMapper(User::class);

// Clearly loaded relationships
$user = $userMapper->with(['posts', 'profile'])->find(1);
$postCount = count($user->posts);    // Known to be loaded
$profile = $user->profile;          // Known to be loaded

// Unloaded relationships are obvious
$user = $userMapper->find(1);
$posts = $user->posts;             // null - clearly not loaded
```

### 4. Performance Predictability

```php
// Predictable query count
$users = $userMapper->with('posts.comments.author')->get();
// Exactly 4 queries:
// 1. Users
// 2. Posts for these users  
// 3. Comments for these posts
// 4. Authors for these comments

// No surprise N+1 queries possible
foreach ($users as $user) {
    foreach ($user->posts as $post) {
        foreach ($post->comments as $comment) {
            echo $comment->author->name; // No additional queries
        }
    }
}
```

## Migration Strategies

### Gradual Migration

You can introduce Holloway alongside existing Eloquent models:

```php
// Keep existing Eloquent models
class LegacyUser extends Model
{
    // Existing functionality
}

// Introduce Holloway entities for new features
class User  // Holloway entity
{
    // New domain-driven approach
}

class UserMapper extends Mapper
{
    protected string $table = 'users'; // Same table
    protected string $entityClassName = User::class;
}
```

### Dual Reading

Read from both systems during transition:

```php
class UserService
{
    public function getUser(int $id)
    {
        // Try new system first
        $userMapper = Holloway::instance()->getMapper(User::class);
        $user = $userMapper->find($id);
        
        if ($user) {
            return $user;
        }
        
        // Fallback to legacy system
        return LegacyUser::find($id);
    }
}
```

## Best Practices for Entities

### 1. Immutable by Default

```php
class User
{
    private readonly string $email;
    private readonly DateTime $createdAt;
    
    public function __construct(string $email)
    {
        $this->email = $email;
        $this->createdAt = new DateTime();
    }
    
    // Return new instance for changes
    public function withNewEmail(string $email): self
    {
        $new = clone $this;
        $new->email = $email;
        return $new;
    }
}
```

### 2. Rich Domain Models

```php
class Order
{
    private array $items;
    private OrderStatus $status;
    
    public function addItem(Product $product, int $quantity): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw new InvalidOperationException('Cannot modify confirmed order');
        }
        
        $this->items[] = new OrderItem($product, $quantity);
    }
    
    public function getTotalAmount(): Money
    {
        return array_reduce($this->items, function(Money $total, OrderItem $item) {
            return $total->add($item->getAmount());
        }, Money::zero());
    }
    
    public function confirm(): void
    {
        if (empty($this->items)) {
            throw new InvalidOperationException('Cannot confirm empty order');
        }
        
        $this->status = OrderStatus::Confirmed;
    }
}
```

### 3. Value Objects

```php
class Email
{
    private readonly string $value;
    
    public function __construct(string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        $this->value = $value;
    }
    
    public function getValue(): string
    {
        return $this->value;
    }
    
    public function getDomain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
    }
}

class User
{
    private Email $email;
    
    public function __construct(string $name, Email $email)
    {
        $this->name = $name;
        $this->email = $email;
    }
}
```

The datamapper pattern with Holloway provides a more structured, maintainable, and testable approach to domain modeling, especially for complex applications where business logic integrity is crucial.

## Next Steps

- **[Getting Started](./getting-started.md)** - Set up your first Holloway entities and mappers
- **[Architecture Overview](./architecture.md)** - Understand the underlying design patterns
- **[Creating Mappers](./mappers/creating-mappers.md)** - Learn to build effective mappers
