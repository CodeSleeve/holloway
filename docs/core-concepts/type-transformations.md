# Type Transformation Patterns

One of the biggest challenges in the datamapper pattern is the **impedance mismatch** between your database schema (strings, integers, JSON) and your domain model (Email objects, Money objects, Dates).

Holloway gives you complete control over these transformations through the hydrate/dehydrate cycle. You can handle this in two ways:

1. **Shared mapping registry (optional)** – centralise hydrate/dehydrate callbacks and reference them from each mapper. The examples below demonstrate this approach.
2. **Manual mapper transformations** – keep casting logic inside the `hydrate`/`dehydrate` methods of each mapper. Lightweight projects often start here.

Both approaches are valid. Pick the one that matches your team’s appetite for abstraction, and remember you can migrate from manual casts to the registry later without breaking your entities.

| Use this approach… | When it shines | Trade-offs |
| --- | --- | --- |
| **Shared mapping registry** | Large codebases, lots of shared value objects, teams that favour DRY abstractions. | Slight upfront setup, centralised defaults may feel opaque to new team members. |
| **Manual mapper transformations** | Small or experimental projects, unique casting rules per entity, teams that prefer explicit code. | Repetition across mappers, easier to forget an edge-case or new value object. |

## Table of Contents

- [The Problem](#the-problem)
- [Solution: The Mappings System](#solution-the-mappings-system)
- [Reference Implementation](#reference-implementation)

## The Problem

Your database stores primitive types:

```sql
CREATE TABLE clients (
    id INT,
    email VARCHAR(255),
    billing_address JSON,
    total_revenue JSON  -- {cents: 50000, currency: "USD"}
);
```

But your domain model uses rich value objects:

```php
class Client extends Entity
{
    protected Email $email;              // Not string!
    protected Address $billing_address;  // Not JSON!
    protected Money $total_revenue;      // Not JSON!
}
```

**How do you transform between them?**

## Solution: The Mappings System

This pattern uses a declarative mapping system that automatically transforms types during hydration and dehydration.

### Step 1: Register Type Transformations

In a service provider, register how each type should transform:

```php
// App/Providers/DataMapperServiceProvider.php

use App\Mappers\Mapper;

class DataMapperServiceProvider extends ServiceProvider
{
    public function register()
    {
        Mapper::setMaps([
            // Simple types
            'email' => [
                'hydrate'   => fn($value) => new Email($value),
                'dehydrate' => fn($value) => (string) $value,
            ],
            
            // Complex types
            'money' => [
                'hydrate'   => fn($value) => Money::fromJson($value),
                'dehydrate' => fn($value) => $value->toJson(),
            ],
            
            'address' => [
                'hydrate'   => fn($value) => Address::fromJson($value),
                'dehydrate' => fn($value) => $value->toJson(),
            ],
            
            'date' => [
                'hydrate'   => fn($value) => $value ? Chronos::parse($value) : null,
                'dehydrate' => fn($value) => $value ? (string) $value : null,
            ],
            
            // Collections
            'array' => [
                'hydrate'   => fn($value) => json_decode($value, true),
                'dehydrate' => fn($value) => json_encode($value),
            ],
            
            'collection' => [
                'hydrate'   => fn($value) => collect(json_decode($value, true)),
                'dehydrate' => fn($value) => json_encode($value),
            ],
            
            // Enums
            'status_enum' => [
                'hydrate'   => fn($value) => $value ? StatusEnum::from($value) : null,
                'dehydrate' => fn($value) => $value?->value,
            ],
        ]);
    }
}
```

### Step 2: Declare Mappings in Mapper

In your mapper, declare which properties use which transformations:

```php
class ClientMapper extends Mapper
{
    protected string $table = 'clients';
    protected string $entityClassName = Client::class;
    
    // This is where the magic happens!
    protected array $mappings = [
        'email' => 'email',
        'billing_address' => 'address',
        'total_revenue' => 'money',
        'date_of_birth' => 'date',
        'notification_settings' => 'json',
    ];
}
```

### Step 3: Entity Uses Value Objects

Your entity just declares the types it wants:

```php
class Client extends Entity
{
    protected Email $email;
    protected ?Address $billing_address = null;
    protected Money $total_revenue;
    protected ?Chronos $date_of_birth = null;
    protected array $notification_settings = [];
    
    // No transformation logic needed here!
}
```

### How It Works

When hydrating (loading from database):

```php
// Database has: {email: "john@example.com"}
$client = $clientMapper->find(1);

// Mapper sees 'email' in mappings array
// Looks up 'email' transformation
// Calls hydrate function: new Email("john@example.com")
// Result: $client->email is Email object!
```

When dehydrating (saving to database):

```php
// Entity has: Email object
$clientMapper->store($client);

// Mapper sees 'email' in mappings array
// Looks up 'email' transformation
// Calls dehydrate function: (string) $email
// Result: Database gets "john@example.com" string
```

## Reference Implementation

Here's the actual base Mapper from the reference implementation:

```php
abstract class Mapper extends HollowayMapper
{
    protected static array $maps = [];
    protected array $mappings = [];
    
    /**
     * Register a type transformation
     */
    public static function addMapp(string $name, callable $hydrate, callable $dehydrate)
    {
        static::$maps[$name] = compact('hydrate', 'dehydrate');
    }
    
    /**
     * Set all transformations at once
     */
    public static function setMaps(array $maps)
    {
        static::$maps = $maps;
    }
    
    /**
     * Hydrate: Database -> Entity
     */
    public function hydrate(stdClass $record, Collection $relations)
    {
        $attributes = $this->mapValueObjects($record, $relations);
        
        $entity = $this->instantiator->instantiate($this->entityClassName);
        
        return $entity->mapperFill($attributes);
    }
    
    /**
     * Dehydrate: Entity -> Database
     */
    public function dehydrate($entity): array
    {
        $attributes = $entity->toArray();
        
        // Remove relationships
        $attributes = Arr::except($attributes, 
            array_map(fn($rel) => $rel->getName(), $this->relationships)
        );
        
        // Remove null id for inserts
        if (!$attributes['id']) {
            unset($attributes['id']);
        }
        
        // Apply transformations
        foreach($this->mappings as $propertyName => $mapName) {
            if (isset(static::$maps[$mapName])) {
                $attributes[$propertyName] = call_user_func_array(
                    static::$maps[$mapName]['dehydrate'], 
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
        foreach($this->mappings as $propertyName => $mapName) {
            if (isset(static::$maps[$mapName])) {
                try {
                    $attributes[$propertyName] = call_user_func_array(
                        static::$maps[$mapName]['hydrate'], 
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

## Next Steps

- **[Value Objects Guide](./value-objects.md)** - Implementing Email, Money, Address
- **[Entity Hydration](./entity-hydration.md)** - How transformations fit into hydration
- **[Entity Lifecycle](./entity-lifecycle.md)** - When transformations happen
- **[Base Classes Reference](./base-classes.md)** - Complete implementation
