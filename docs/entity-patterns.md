# Entity Design Patterns

Holloway's datamapper architecture provides complete decoupling between your entities and persistence logic. This means you have full freedom in how you design your entities. This guide showcases different approaches to entity design and how to configure mappers to work with each pattern.

## Array-Based Entities

Some developers prefer using arrays or array-like structures for maximum flexibility.

### Dynamic Properties with Arrays

```php
class User
{
    private array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    // Convenience methods for common attributes
    public function getId(): ?int
    {
        return $this->getAttribute('id');
    }

    public function getName(): ?string
    {
        return $this->getAttribute('name');
    }

    public function setName(string $name): void
    {
        $this->setAttribute('name', $name);
    }
}
```

**Mapper Configuration:**
```php
class UserMapper extends Mapper
{
    protected $table = 'users';

    public function hydrate(array $attributes): User
    {
        return new User($attributes);
    }

    public function dehydrate($entity): array
    {
        return $entity->getAttributes();
    }

    public function getIdentifier($entity): mixed
    {
        return $entity->getId();
    }

    public function setIdentifier($entity, $identifier): void
    {
        $entity->setAttribute('id', $identifier);
    }
}
```

### ArrayAccess Implementation

```php
class User implements ArrayAccess
{
    private array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    // ArrayAccess implementation
    public function offsetExists($offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
```

**Usage:**
```php
$user = new User(['name' => 'John', 'email' => 'john@example.com']);
$user['age'] = 30;
echo $user['name']; // John
```

## Defined Properties

Many developers prefer explicit property definitions for better IDE support and type safety.

### Public Properties

```php
class User
{
    public ?int $id = null;
    public string $name;
    public string $email;
    public bool $active = true;
    public DateTime $createdAt;

    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
        $this->createdAt = new DateTime();
    }
}
```

**Mapper Configuration:**
```php
class UserMapper extends Mapper
{
    protected $table = 'users';

    public function hydrate(array $attributes): User
    {
        $user = new User($attributes['name'], $attributes['email']);
        $user->id = $attributes['id'] ?? null;
        $user->active = $attributes['active'] ?? true;
        $user->createdAt = new DateTime($attributes['created_at']);
        
        return $user;
    }

    public function dehydrate($entity): array
    {
        return [
            'id' => $entity->id,
            'name' => $entity->name,
            'email' => $entity->email,
            'active' => $entity->active,
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    public function getIdentifier($entity): mixed
    {
        return $entity->id;
    }

    public function setIdentifier($entity, $identifier): void
    {
        $entity->id = $identifier;
    }
}
```

### Protected Properties with Getters/Setters

```php
class User
{
    protected ?int $id = null;
    protected string $name;
    protected string $email;
    protected bool $active = true;
    protected DateTime $createdAt;

    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
        $this->createdAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
}
```

## PHP 8.4 Property Hooks (Future)

PHP 8.4 introduces property hooks for cleaner property access patterns.

```php
class User
{
    public ?int $id = null;
    
    public string $name {
        set {
            $this->name = trim($value);
        }
    }
    
    public string $email {
        set {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid email format');
            }
            $this->email = strtolower($value);
        }
    }
    
    public bool $active = true;
    public DateTime $createdAt;

    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
        $this->createdAt = new DateTime();
    }
}
```

## Immutable Entities

Some prefer immutable entities for better consistency and thread safety.

### Immutable with Constructor

```php
class User
{
    private readonly ?int $id;
    private readonly string $name;
    private readonly string $email;
    private readonly bool $active;
    private readonly DateTime $createdAt;

    public function __construct(
        string $name,
        string $email,
        ?int $id = null,
        bool $active = true,
        ?DateTime $createdAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->active = $active;
        $this->createdAt = $createdAt ?? new DateTime();
    }

    public function getId(): ?int
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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    // Create new instances for changes
    public function withName(string $name): self
    {
        return new self($name, $this->email, $this->id, $this->active, $this->createdAt);
    }

    public function withEmail(string $email): self
    {
        return new self($this->name, $email, $this->id, $this->active, $this->createdAt);
    }

    public function activate(): self
    {
        return new self($this->name, $this->email, $this->id, true, $this->createdAt);
    }

    public function deactivate(): self
    {
        return new self($this->name, $this->email, $this->id, false, $this->createdAt);
    }
}
```

**Mapper Configuration for Immutable Entities:**
```php
class UserMapper extends Mapper
{
    protected $table = 'users';

    public function hydrate(array $attributes): User
    {
        return new User(
            $attributes['name'],
            $attributes['email'],
            $attributes['id'] ?? null,
            $attributes['active'] ?? true,
            isset($attributes['created_at']) ? new DateTime($attributes['created_at']) : null
        );
    }

    public function dehydrate($entity): array
    {
        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'email' => $entity->getEmail(),
            'active' => $entity->isActive(),
            'created_at' => $entity->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    public function getIdentifier($entity): mixed
    {
        return $entity->getId();
    }

    public function setIdentifier($entity, $identifier): User
    {
        // For immutable entities, return new instance
        return new User(
            $entity->getName(),
            $entity->getEmail(),
            $identifier,
            $entity->isActive(),
            $entity->getCreatedAt()
        );
    }

    public function store($entity): User
    {
        if ($entity->getId() === null) {
            // Insert and return new entity with ID
            $attributes = $this->dehydrate($entity);
            unset($attributes['id']);
            
            $id = $this->insertGetId($attributes);
            return $this->setIdentifier($entity, $id);
        } else {
            // Update existing
            $this->updateEntity($entity);
            return $entity;
        }
    }
}
```

## Value Objects as Properties

Using value objects for better domain modeling.

```php
class Email
{
    private string $value;

    public function __construct(string $email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        $this->value = strtolower($email);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getDomain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

class User
{
    private ?int $id = null;
    private string $name;
    private Email $email;
    private bool $active = true;

    public function __construct(string $name, Email $email)
    {
        $this->name = $name;
        $this->email = $email;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function setEmail(Email $email): void
    {
        $this->email = $email;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
```

**Mapper Configuration with Value Objects:**
```php
class UserMapper extends Mapper
{
    protected $table = 'users';

    public function hydrate(array $attributes): User
    {
        $user = new User(
            $attributes['name'],
            new Email($attributes['email'])
        );
        $user->setId($attributes['id'] ?? null);
        $user->setActive($attributes['active'] ?? true);
        
        return $user;
    }

    public function dehydrate($entity): array
    {
        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'email' => $entity->getEmail()->getValue(),
            'active' => $entity->isActive(),
        ];
    }

    public function getIdentifier($entity): mixed
    {
        return $entity->getId();
    }

    public function setIdentifier($entity, $identifier): void
    {
        $entity->setId($identifier);
    }
}
```

## Trait-Based Approaches

Using traits for common entity behavior.

```php
trait HasAttributes
{
    private array $attributes = [];

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function fill(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }
}

trait HasTimestamps
{
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTime();
    }
}

class User
{
    use HasAttributes, HasTimestamps;

    private ?int $id = null;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->createdAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): ?string
    {
        return $this->getAttribute('name');
    }

    public function setName(string $name): void
    {
        $this->setAttribute('name', $name);
    }

    public function getEmail(): ?string
    {
        return $this->getAttribute('email');
    }

    public function setEmail(string $email): void
    {
        $this->setAttribute('email', $email);
    }
}
```

## Configuration Considerations for Mappers

Different entity patterns may require specific mapper configurations:

### For Array-Based Entities
- Override `hydrate()` and `dehydrate()` methods
- Handle attribute access patterns
- Consider mass assignment protection

### For Immutable Entities
- Override `setIdentifier()` to return new instance
- Modify `store()` method to handle immutability
- Handle relationships carefully

### For Value Objects
- Implement proper serialization/deserialization
- Handle nested object hydration
- Consider caching implications

### For Traits
- Ensure trait methods are accessible
- Handle multiple inheritance patterns
- Consider method conflicts

## Choosing the Right Pattern

**Array-Based**: Best for maximum flexibility and dynamic schemas
**Defined Properties**: Best for type safety and IDE support
**Immutable**: Best for consistency and functional programming approaches
**Value Objects**: Best for rich domain models with validation
**Traits**: Best for sharing common behavior across entities

The beauty of Holloway's datamapper pattern is that your choice of entity design doesn't constrain your persistence strategy - the mapper handles the translation between your domain objects and the database.

## Next Steps

- **[Mappers](../mappers/creating-mappers.md)** - Implementing mappers for different entity patterns
- **[Relationships](../relationships/overview.md)** - Handling relationships with various entity designs
- **[Best Practices](../examples/best-practices.md)** - Recommended patterns for different use cases
