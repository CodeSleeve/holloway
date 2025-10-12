# Entity Lifecycle: Creation vs Hydration

One of the most important concepts in the datamapper pattern is understanding the **two distinct lifecycles** an entity can have:

1. **Creation** - Building a brand new entity for the first time
2. **Hydration** - Reconstituting an entity from persisted data

Understanding this distinction is critical for proper validation, initialization, and domain logic placement.

## Table of Contents

- [The Two Lifecycles](#the-two-lifecycles)
- [Why Separate These?](#why-separate-these)
- [Implementation Patterns](#implementation-patterns)
- [Real-World Example](#real-world-example)
- [Validation Placement](#validation-placement)
- [Common Pitfalls](#common-pitfalls)
- [Decision Matrix](#decision-matrix)
- [Key Principles](#key-principles)
- [Next Steps](#next-steps)
- [Further Reading](#further-reading)

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
$userMapper->save($user);
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
    
    public function hydrate($record, $relations)
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
$userMapper->save($user);

// Loading existing user - validation bypassed
$user = $userMapper->find(1); // No constructor called!
```

**Key advantage:** Constructor signature can be completely different from database schema, because hydration doesn't use the constructor.

### Pattern 3: Private Constructor + Named Factories

```php
class User
{
    private function __construct() {}
    
    /**
     * Named constructor for NEW users
     */
    public static function register(string $name, string $email, string $password): self
    {
        // Validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email');
        }
        
        $user = new self();
        $user->name = $name;
        $user->email = $email;
        $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        return $user;
    }
    
    /**
     * Named constructor for HYDRATION
     */
    public static function fromDatabase(array $data): self
    {
        $user = new self();
        $user->id = $data['id'];
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password_hash = $data['password_hash'];
        
        return $user;
    }
}

// Usage is explicit:
$user = User::register('John', 'john@example.com', 'secret123');
$user = User::fromDatabase($row);
```

## Real-World Example

Here's how this pattern handles a complex entity with value objects:

```php
class Client extends Entity
{
    protected string $tenant_id;
    protected string $first_name;
    protected string $last_name;
    protected Email $email;
    protected ?Address $billing_address = null;
    protected Money $total_revenue;
    
    /**
     * Constructor for CREATING new clients
     * Requires domain objects, enforces business rules
     */
    public function __construct(
        ClientCompany $company,
        string $first_name,
        string $last_name,
        string $job_title,
        Email $email
    ) {
        // Validation
        if (empty($first_name)) {
            throw new InvalidArgumentException('First name is required');
        }
        
        if (empty($last_name)) {
            throw new InvalidArgumentException('Last name is required');
        }
        
        // Initialize from domain objects
        $this->tenant_id = $company->tenant_id;
        $this->company_id = $company->id;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->job_title = $job_title;
        $this->email = $email;
        
        // Sensible defaults
        $this->total_revenue = Money::USD(0);
        $this->status = ClientStatus::Active;
    }
}

class ClientMapper extends Mapper
{
    protected string $table = 'clients';
    protected string $entityClassName = Client::class;
    
    /**
     * Hydration bypasses constructor completely
     */
    public function hydrate($record, $relations)
    {
        // Instantiate without constructor
        $client = $this->instantiator->instantiate(Client::class);
        
        // Transform database types to value objects
        $attributes = [
            'id' => $record->id,
            'tenant_id' => $record->tenant_id,
            'first_name' => $record->first_name,
            'last_name' => $record->last_name,
            'email' => new Email($record->email),
            'billing_address' => $record->billing_address 
                ? Address::fromJson($record->billing_address)
                : null,
            'total_revenue' => Money::fromCents(
                $record->total_revenue_cents,
                $record->total_revenue_currency
            ),
        ];
        
        // Fill properties
        return $client->mapperFill($attributes);
    }
}
```

**Notice:**

- Constructor requires `ClientCompany` domain object (doesn't exist in database)
- Constructor enforces validation rules
- Hydration transforms raw DB values to value objects (Email, Money, Address)
- Hydration never calls constructor - completely different initialization path

## Validation Placement

### ✅ Correct: Validation in Constructor (Creation)

```php
class User
{
    public function __construct(string $email, string $password)
    {
        // Validate NEW users
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email');
        }
        
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password too short');
        }
        
        $this->email = $email;
        $this->password = password_hash($password, PASSWORD_DEFAULT);
    }
}
```

### ✅ Correct: Validation in Value Objects

```php
class Email
{
    private string $value;
    
    public function __construct(string $email)
    {
        // Value objects validate their own invariants
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        $this->value = strtolower($email);
    }
}

class User
{
    public function __construct(Email $email, string $password)
    {
        // Email already validated by its own constructor
        $this->email = $email;
        $this->password = password_hash($password, PASSWORD_DEFAULT);
    }
}
```

### ✅ Correct: Validation in Domain Methods

```php
class User
{
    public function changeEmail(Email $newEmail): void
    {
        // Business rule: can't change to existing email
        if ($this->mapper->emailExists($newEmail)) {
            throw new DomainException('Email already in use');
        }
        
        $this->email = $newEmail;
        $this->email_verified = false;
    }
}
```

### ❌ Wrong: Validation in Hydration

```php
class UserMapper extends Mapper
{
    public function hydrate($record, $relations)
    {
        // DON'T DO THIS!
        if (!filter_var($record->email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email in database!');
        }
        
        // What happens if this fails?
        // You can't load the entity!
        // Your application breaks!
    }
}
```

### ❌ Wrong: Validation in Mapper Methods

```php
class UserMapper extends Mapper
{
    public function save($entity): void
    {
        // DON'T DO THIS!
        if (empty($entity->name)) {
            throw new Exception('Name required');
        }
        
        // Validation belongs in entity constructor
        // Mapper should only handle persistence
        parent::save($entity);
    }
}
```

## Common Pitfalls

### Pitfall 1: Required Constructor Parameters

```php
// Problem: Constructor requires objects not in database
class Order
{
    public function __construct(
        Customer $customer,  // Database only has customer_id!
        Product $product     // Database only has product_id!
    ) {
        $this->customer_id = $customer->id;
        $this->product_id = $product->id;
    }
}

// Solution: Use instantiator pattern
class OrderMapper extends Mapper
{
    public function hydrate($record, $relations)
    {
        // Bypass constructor
        $order = $this->instantiator->instantiate(Order::class);
        
        // Fill from database values
        return $order->mapperFill([
            'customer_id' => $record->customer_id,
            'product_id' => $record->product_id,
        ]);
    }
}
```

### Pitfall 2: Validation in Hydration

```php
// Problem: What if data fails validation?
class UserMapper
{
    public function hydrate($record, $relations)
    {
        if (!filter_var($record->email, FILTER_VALIDATE_EMAIL)) {
            // Now what? Can't load the user!
            throw new Exception('Invalid email');
        }
    }
}

// Solution: Trust the database, validate at creation
class User
{
    public function __construct(string $email)
    {
        // Validate ONLY when creating
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email');
        }
        $this->email = $email;
    }
}
```

### Pitfall 3: Side Effects in Constructor

```php
// Problem: Side effects happen on EVERY hydration
class User
{
    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
        
        // These happen EVERY time entity loads!
        $this->sendWelcomeEmail();
        $this->logUserCreated();
        event(new UserCreated($this));
    }
}

// Solution: Extract to domain method
class User
{
    public static function register(string $name, string $email): self
    {
        $user = new self($name, $email);
        
        // Side effects only happen on CREATION
        $user->sendWelcomeEmail();
        event(new UserCreated($user));
        
        return $user;
    }
}
```

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

## Key Principles

1. **Constructor = Validation + Business Rules**
   - Only called when creating NEW entities
   - Enforces all domain invariants
   - May throw exceptions for invalid data

2. **Hydration = Direct Property Population**
   - Only used when loading from database
   - No validation (database is trusted)
   - Never throws exceptions

3. **Database = Source of Truth**
   - Data in database already passed validation
   - No need to re-validate on every load
   - Performance optimization

4. **Separation = Flexibility**
   - Constructor can require domain objects
   - Hydration can work with raw database types
   - No coupling between creation and persistence

## Next Steps

- **[Entity Hydration Patterns](./entity-hydration.md)** - Implementation patterns for hydration
- **[Type Transformations](./type-transformations.md)** - Transform database types to value objects
- **[Value Objects](./value-objects.md)** - Email, Money, Address patterns

## Further Reading

- Martin Fowler: [Separating Validation from Object Construction](https://martinfowler.com/articles/domain-oriented-observability.html)
- Eric Evans: Domain-Driven Design (Entities chapter)
- Doctrine Instantiator: [Bypass constructors](https://github.com/doctrine/instantiator)
