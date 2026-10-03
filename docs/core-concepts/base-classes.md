# Base Classes Reference

This page documents a reference implementation of base `Entity` and `Mapper` classes using the **Magic Accessor Pattern**.

> **Note**: This code lives in your application, not in Holloway. Holloway only requires the abstract methods on `Mapper`; these base classes are one way to implement them.

## Table of Contents

- [Overview](#overview)
- [Base Entity Class](#base-entity-class)
- [Base Mapper Class](#base-mapper-class)
- [Entity Traits](#entity-traits)
- [Next Steps](#next-steps)

## Overview

This reference implementation extends Holloway's base classes to provide:
- **Entity**: Protected properties with magic `__get()` accessor, `mapperFill()` for hydration, `toArray()` for dehydration
- **Mapper**: Type transformation system via `mappings` and `$maps`, automatic hydration/dehydration of value objects
- **Traits**: Reusable cross-cutting concerns (`HasTimestamps`, `HasTenant`, etc.)

## Base Entity Class

The base `Entity` class provides the foundation for the Magic Accessor Pattern.

### Complete Implementation

```php
<?php

namespace App\Entities;

use JsonSerializable;
use BadMethodCallException;
use Illuminate\Support\Str;
use Cake\Chronos\{Chronos, Date};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

abstract class Entity implements JsonSerializable
{
    use HasTimeStamps;

    protected int|string|null $id = null;

    /**
     * Return an array representation of this entity.
     *
     * @return array
     */
    public function toArray() : array
    {
        return get_object_vars($this);
    }

    /**
     * Magic accessor for protected properties
     *
     * @param  string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        $accessor = $this->attributeAccessorName($name);

        if (method_exists($this, $accessor)) {
            return $this->$accessor();
        } elseif (property_exists($this, $name)) {
            return $this->$name;
        }
    }

    public function __isset($name) : bool
    {
        return property_exists($this, $name);
    }

    /**
     * JSON serialization with automatic date formatting
     */
    public function jsonSerialize() : array
    {
        return array_map(function($item) {
            if (is_a($item, Date::class)) {
                return $item->toDateString();
            } else if (is_a($item, Chronos::class)) {
                return $item->toIso8601String();
            } else {
                return $item;
            }
        }, $this->toArray());
    }

    public function toJson() : string
    {
        return json_encode($this);
    }

    /**
     * Get a subset of the model's attributes.
     */
    public function only($attributes) : array
    {
        $results = [];

        foreach (is_array($attributes) ? $attributes : func_get_args() as $attribute) {
            $results[$attribute] = $this->$attribute;
        }

        return $results;
    }

    public function setId(string|int $id)
    {
        $this->id = $id;
    }

    public function getId(): string|int|null
    {
        return $this->id;
    }

    private function attributeAccessorName(string $name) : string
    {
        return 'get' . Str::studly($name);
    }

    /**
     * FOR USE ONLY BY ENTITY MAPPERS to hydrate our entities
     *
     * Bypasses constructor and directly sets properties from database records.
     * This is called by the mapper during hydration, NOT by application code.
     *
     * @return self
     */
    public function mapperFill(array $properties) : self
    {
        foreach($properties as $propertyName => $propertyValue) {
            $this->$propertyName = $propertyValue;
        }

        return $this;
    }

    /**
     * FOR USE ONLY IN TESTS to hydrate relationships onto our entities
     *
     * @throws BadMethodCallException 
     */
    public function setRelationForTest(string $name, Entity|Collection $relation) : void
    {
        if (App::environment('testing')) {
            $this->$name = $relation;
        } else {
            throw new BadMethodCallException('This method may only be used for testing purposes', 1);
        }
    }
}
```

## Base Mapper Class

The base `Mapper` class extends Holloway's mapper with the reference implementation's type transformation system.

### Complete Implementation

```php
<?php

namespace App\Mappers;

use stdClass;
use App\Entities\Entity;
use Cake\Chronos\Chronos;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Doctrine\Instantiator\Instantiator;
use CodeSleeve\Holloway\Mapper as HollowayMapper;
use function App\Functions\ValueObject\base_utc_date_time;

abstract class Mapper extends HollowayMapper
{
    /** @var array Global type transformation map */
    protected static array $maps = [];

    /** @var array Instance-specific mappings */
    protected array $mappings = [];

    /**
     * @param Instantiator|null $instantiator
     */
    public function __construct(?Instantiator $instantiator = null)
    {
        parent::__construct();

        $this->instantiator = $instantiator ?: new Instantiator();
    }

    /**
     * @return string
     */
    public function getEntityClassName() : string
    {
        return $this->entityClassName;
    }

    /**
     * Return the identifier (primary key) for a given entity.
     *
     * @param  mixed $entity
     * @return mixed
     */
    public function getIdentifier($entity)
    {
        return $entity->id;
    }

    /**
     * Set the identifier (primary key) for a given entity.
     */
    public function setIdentifier($entity, $value) : void
    {
        $entity->setId($value);
    }

    /**
     * Register a single type transformation
     *
     * @param string $name
     * @param callable $hydrate
     * @param callable $dehydrate
     * @return void
     */
    public static function addMapp(string $name, callable $hydrate, callable $dehydrate)
    {
        static::$maps[$name] = compact('hydrate', 'dehydrate');
    }

    /**
     * Register all type transformations at once
     *
     * @param array $maps
     * @return void
     */
    public static function setMaps(array $maps)
    {
        static::$maps = $maps;
    }

    /**
     * Convert database record to entity instance
     *
     * @param  stdClass  $record
     * @param  Collection $relations
     * @return mixed
     */
    public function hydrate(stdClass $record, Collection $relations)
    {
        $attributes = $this->mapValueObjects($record, $relations);

        $entity = $this->instantiator->instantiate($this->entityClassName);

        return $entity->mapperFill($attributes);
    }

    /**
     * Convert entity instance to database array
     *
     * @param  mixed $entity
     * @return array
     */
    public function dehydrate($entity) : array
    {
        // Get all entity properties except relationships
        $attributes = Arr::except(
            $entity->toArray(), 
            array_map(fn($relationship) => $relationship->getName(), $this->relationships)
        );

        // Remove null ID (new entities)
        if (!$attributes['id']) {
            unset($attributes['id']);
        }
        
        // Transform value objects back to primitives
        foreach($this->mappings as $propertyName => $map) {
            if (isset(static::$maps[$map])) {
                $attributes[$propertyName] = call_user_func_array(
                    static::$maps[$map]['dehydrate'], 
                    [$attributes[$propertyName]]
                );
            }
        }

        return $attributes;
    }

    /**
     * Set created_at timestamp (called by Holloway on insert)
     *
     * @param \DateTime $now
     * @param Entity    $entity
     */
    public function setCreatedAtTimestampOnEntity(Entity $entity, \DateTime $now)
    {
        $createdAt = Chronos::instance($now);
    
        $entity->setCreatedAt($createdAt);
    }

    /**
     * Set updated_at timestamp (called by Holloway on update)
     *
     * @param \DateTime $now
     * @param Entity    $entity
     */
    public function setUpdatedAtTimestampOnEntity(Entity $entity, \DateTime $now)
    {
        $updatedAt = Chronos::instance($now);
    
        $entity->setUpdatedAt($updatedAt);
    }

    /**
     * Define relationships (override in child mappers)
     *
     * @return void
     */
    public function defineRelations() : void
    {
        // Override in child classes
    }

    /**
     * Transform database primitives to value objects during hydration
     *
     * @param  stdClass  $record
     * @param  Collection $relations
     * @return array
     */
    protected function mapValueObjects(stdClass $record, Collection $relations) : array
    {
        $object = $this->instantiator->instantiate($this->entityClassName);
        $attributes = array_merge((array) $record, $relations->all());

        // Handle timestamps
        if ($this->hasTimestamps) {
            $attributes['created_at'] = base_utc_date_time($record->created_at);
            $attributes['updated_at'] = base_utc_date_time($record->updated_at);
        }

        // Transform mapped properties
        foreach($this->mappings as $propertyName => $map) {
            if (isset(static::$maps[$map])) {
                try {
                    $attributes[$propertyName] = call_user_func_array(
                        static::$maps[$map]['hydrate'], 
                        [$attributes[$propertyName]]
                    );
                } catch (\Throwable $th) {
                    throw new \Exception(
                        static::class . ": Unable to hydrate property $propertyName: " . $th->getMessage()
                    );
                }
            }
        }

        return $attributes;
    }
}
```

### The Mappings System

The mappings system is this reference implementation's approach to type transformations.

**How it works:**

1. **Define mappings in mapper:**
   ```php
   protected array $mappings = [
       'email' => 'email',           // property => type name
       'total_revenue' => 'money',
       'created_at' => 'date_time',
   ];
   ```

2. **Register transformations globally:**
   ```php
   Mapper::setMaps([
       'email' => [
           'hydrate' => fn($value) => email($value),
           'dehydrate' => fn($value) => $value?->toString(),
       ],
       // ...
   ]);
   ```

3. **Hydration (database → entity):**
   ```php
   $record->email = 'john@example.com';  // string from database
   // mapValueObjects() transforms via 'email' hydrate function
   $entity->email = Email('john@example.com'); // Email value object
   ```

4. **Dehydration (entity → database):**
   ```php
   $entity->email = Email('john@example.com'); // Email value object
   // dehydrate() transforms via 'email' dehydrate function
   $attributes['email'] = 'john@example.com'; // string for database
   ```

**Benefits:**
- **Centralized**: All transformations defined in one place
- **Reusable**: Same transformation used by all mappers
- **Type-safe**: Value objects ensure data integrity
- **Testable**: Transformations are pure functions
- **Flexible**: Easy to add new value object types

## Entity Traits

This pattern uses traits for cross-cutting concerns.

### HasTimestamps Trait

```php
<?php

namespace App\Entities;

use Cake\Chronos\Chronos;

trait HasTimestamps
{
    protected ?Chronos $created_at = null;
    protected ?Chronos $updated_at = null;

    public function getCreatedAt(): ?Chronos
    {
        return $this->created_at;
    }

    public function setCreatedAt(?Chronos $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function getUpdatedAt(): ?Chronos
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?Chronos $updated_at): void
    {
        $this->updated_at = $updated_at;
    }
}
```

**Usage:**
```php
class Client extends Entity
{
    use HasTimestamps;
    
    // Automatically includes created_at and updated_at
}
```

**Key points:**
- Used by base `Entity` class
- Holloway's `Mapper` calls the mapper's optional `setCreatedAtTimestampOnEntity()` / `setUpdatedAtTimestampOnEntity()` hooks, and the base mapper's implementations call `setCreatedAt()` / `setUpdatedAt()` on the entity
- Uses Cake Chronos for immutable date/time
- Properties are protected, accessed via magic `__get()`

## Next Steps

- **[Entity Hydration](./entity-hydration.md)** - Deep dive into hydration patterns
- **[Type Transformations](./type-transformations.md)** - Understanding the mappings system
- **[Value Objects](./value-objects.md)** - Working with Email, Money, Address
- **[Creating Mappers](../mappers/creating-mappers.md)** - Mapper implementation guide
