# Holloway 

<!-- ![image of a Holloway in nature](./annie-spratt-holloway-unsplash-small.jpg?raw=true) -->
<img
  src="./annie-spratt-holloway-unsplash-small.jpg"
  alt="image of a Holloway in nature"
  title="A Holloway in nature"
  style="display: block; margin: 0 auto; max-width: 320px">

# Holloway

[![Build Status](https://github.com/CodeSleeve/holloway/workflows/tests/badge.svg)](https://github.com/CodeSleeve/holloway/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/codesleeve/holloway.svg)](https://packagist.org/packages/codesleeve/holloway)
[![PHP Version](https://img.shields.io/packagist/php-v/codesleeve/holloway.svg)](https://packagist.org/packages/codesleeve/holloway)
[![Total Downloads](https://img.shields.io/packagist/dt/codesleeve/holloway.svg)](https://packagist.org/packages/codesleeve/holloway)
[![License](https://img.shields.io/github/license/CodeSleeve/holloway.svg)](https://github.com/CodeSleeve/holloway/blob/main/LICENSE)

Holloway is a datamapper ORM for Laravel that separates your domain entities from database persistence. Built on Laravel's `illuminate/database`, it gives you the familiar query builder you know while enabling rich domain modeling with value objects, type transformations, and flexible entity patterns.

## Why Holloway?

**Unbreakable Domain Objects:** Create entities that are never allowed to exist in an invalid state, with complete control over their construction and behavior.

**Familiar Syntax:** Query using the same elegant syntax as Laravel's Eloquent, but with datamapper architecture benefits.

**Performance Optimized:** Built-in entity caching, optimized relationship loading, and efficient batch operations.

**Enterprise Ready:** Global scopes, soft deletes, event hooks, and transaction support for production applications.

## Quick Start

```php
use Codesleeve\Holloway\Entity;
use Codesleeve\Holloway\Mapper;

// Define your entity
class User extends Entity
{
    protected int $id;
    protected string $name;
    protected string $email;
}

// Create a mapper
class UserMapper extends Mapper
{
    protected string $table = 'users';
}

// Register and use
Holloway::instance()->register([UserMapper::class]);

$users = Holloway::instance()
    ->getMapper(User::class)
    ->where('active', true)
    ->get();
```

See the [Getting Started guide](./docs/getting-started.md) for installation and complete examples.

## Installation

```bash
composer require codesleeve/holloway
```

## Documentation

### Core Concepts

- [Getting Started](./docs/getting-started.md) - Installation, basic setup, and your first mapper
- [Architecture Overview](./docs/architecture.md) - Understanding the datamapper pattern
- [Entities vs Models](./docs/entities-vs-models.md) - Key differences from Active Record
- [Entity Patterns](./docs/entity-patterns.md) - Choose the pattern that fits your PHP version
- [Entity Hydration](./docs/core-concepts/entity-hydration.md) - How entities are loaded from the database
- [Type Transformations](./docs/core-concepts/type-transformations.md) - Automatic value object conversion
- [Value Objects](./docs/core-concepts/value-objects.md) - Rich domain types like Money and Email

### Mappers & Querying

- [Creating Mappers](./docs/mappers/creating-mappers.md) - Mapper basics and configuration
- [Query Building](./docs/mappers/query-building.md) - Filtering and retrieving entities
- [Persistence Operations](./docs/mappers/persistence.md) - Storing, updating, and deleting
- [Scopes](./docs/mappers/scopes.md) - Reusable query logic

### Relationships

- [Relationship Overview](./docs/relationships/overview.md) - Understanding relationships
- [Standard Relationships](./docs/relationships/standard.md) - HasOne, HasMany, BelongsTo, BelongsToMany
- [Custom Relationships](./docs/relationships/custom.md) - Flexible relationship patterns
- [Eager Loading](./docs/relationships/eager-loading.md) - Optimize relationship queries

### Advanced Topics

- [Rich Domain Models](./docs/entities/rich-domain-models.md) - Business logic in entities
- [Entity Caching](./docs/advanced/caching.md) - Performance optimization
- [Soft Deletes](./docs/advanced/soft-deletes.md) - Soft deletion support
- [Events & Hooks](./docs/advanced/events.md) - Lifecycle events
- [Factories & Testing](./docs/advanced/factories.md) - Test data creation


## Testing

```bash
composer test
```

## Contributing

Thank you for your interest in improving Holloway! We're currently focused on maintaining high code quality and consistency. If you encounter bugs or have suggestions, please open an issue.

## License

Holloway is open-sourced software licensed under the [MIT license](./LICENSE).
