# Entity Lifecycle: Creation vs Hydration

One of the most important concepts in the datamapper pattern is understanding the **two distinct lifecycles** an entity can have:

1. **Creation** - Building a brand new entity for the first time
2. **Hydration** - Reconstituting an entity from persisted data

Understanding this distinction is critical for proper validation, initialization, and domain logic placement.

## Table of Contents

- [The Two Lifecycles](#the-two-lifecycles)
- [Why Separate These?](#why-separate-these)
- [Implementation Patterns](#implementation-patterns)
- [Decision Matrix](#decision-matrix)
- [Next Steps](#next-steps)

## The Two Lifecycles

### Creation Lifecycle

When you create a new entity from user input or business logic:

```php
// User submits a form to register
$user = new User(
    name: $request->input('name'),
    email: $request->input('email'),
    password: $request->input('password')
);

// Mapper persists it
$userMapper->store($user);
```

**Characteristics:**

- Data comes from **untrusted sources** (user input, API calls, imports)
- **Must validate** all business rules
- **Must enforce** required fields and constraints
- **May throw exceptions** for invalid data
- Constructor is the **entry point** for validation
- Happens **rarely** relative to reads

### Hydration Lifecycle

When you load an existing entity from the database:

```php
// Mapper loads from database
$user = $userMapper->find($id);

// Entity properties populated from database
// No constructor called, no validation run
```

**Characteristics:**

- Data comes from **trusted source** (your database)
- Data **already validated** when it was created
- **No need to re-validate** on every load
- **Should never throw exceptions** during hydration
- Hydration bypasses constructor entirely
- Happens **frequently** (every query)

## Why Separate These?

### Performance

```php
// If validation ran on hydration:
$users = $userMapper->all(); // Loads 10,000 users

// Each user would re-validate:
// - Email format check
// - Password strength check
// - Business rule validations
// = 10,000 × validation overhead = SLOW
```

With separate lifecycles, validation only runs once at creation, not on every query.

### Database Trust

Your database is your **source of truth**. If data is in the database, it has already passed validation. Re-validating on every load is:

- Wasteful (performance)
- Redundant (already validated)
- Risky (what if validation fails? You can't load the entity!)

### Domain Integrity

```php
class User
{
    public function __construct(string $name, string $email, string $password)
    {
        // These rules MUST be enforced for new users
        if (empty($name)) {
            throw new InvalidArgumentException('Name required');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email');
        }
        
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be 8+ characters');
        }
        
        $this->name = $name;
        $this->email = $email;
        $this->password = password_hash($password, PASSWORD_DEFAULT);
    }
}
```

This validation **protects your domain** from invalid state. But it should only run during creation, not every time you load from the database.

## Implementation Patterns

### Pattern 1: Constructor + Hydration Method

Separate creation from hydration with distinct methods:

```php
class User
{
    private int $id;
    private string $name;
    private string $email;
    private string $password_hash;
    
    /**
     * For CREATING new users (with validation)
     */
    public function __construct(string $name, string $email, string $password)
    {
        // Validation
        if (empty($name)) {
            throw new InvalidArgumentException('Name required');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password too short');
        }
        
        // Initialize
        $this->name = $name;
        $this->email = $email;
        $this->password_hash = password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * For HYDRATING from database (no validation)
     */
    public static function fromDatabase(array $data): self
    {
        $user = new self.__skipConstruct();
        $user->id = $data['id'];
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password_hash = $data['password_hash'];
        return $user;
    }
}
```

### Pattern 2: Instantiator Pattern (Magic Accessor Pattern)

Use Doctrine Instantiator to bypass constructor entirely:

```php
use Doctrine\Instantiator\Instantiator;

class Mapper extends HollowayMapper
{
    protected Instantiator $instantiator;
    
    public function __construct()
    {
        parent::__construct();
        $this->instantiator = new Instantiator();
    }
    
    public function hydrate(stdClass $record, Collection $relations)
    {
        // Create entity WITHOUT calling constructor
        $entity = $this->instantiator->instantiate($this->entityClassName);
        
        // Fill properties directly (bypassing validation)
        return $entity->mapperFill((array) $record);
    }
}
```

```php
class User extends Entity
{
    protected string $name;
    protected string $email;
    protected string $password_hash;
    
    /**
     * For CREATING new users (with validation)
     */
    public function __construct(string $name, string $email, string $password)
    {
        // Validation runs ONLY for new users
        if (empty($name)) {
            throw new InvalidArgumentException('Name required');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email');
        }
        
        $this->name = $name;
        $this->email = $email;
        $this->password_hash = password_hash($password, PASSWORD_DEFAULT);
    }
}

// Usage:
// Creating new user - validation runs
$user = new User('John Doe', 'john@example.com', 'secret123');
$userMapper->store($user);

// Loading existing user - validation bypassed
$user = $userMapper->find(1); // No constructor called!
```

**Key advantage:** Constructor signature can be completely different from database schema, because hydration doesn't use the constructor.

## Decision Matrix

| Scenario | Use Constructor | Use Hydration |
|----------|----------------|---------------|
| User registers via form | ✅ Yes | ❌ No |
| Loading user from database | ❌ No | ✅ Yes |
| Importing users from CSV | ✅ Yes | ❌ No |
| API creates new user | ✅ Yes | ❌ No |
| Loading users for display | ❌ No | ✅ Yes |
| Running query scopes | ❌ No | ✅ Yes |
| Seeding database | ✅ Yes | ❌ No |

## Next Steps

- **[Entity Hydration Patterns](./entity-hydration.md)** - Implementation patterns for hydration
- **[Type Transformations](./type-transformations.md)** - Transform database types to value objects
- **[Value Objects](./value-objects.md)** - Email, Money, Address patterns
