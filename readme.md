# Holloway

<p align="center">
  <img
    src="./annie-spratt-holloway-unsplash-small.jpg"
    alt="A Holloway in nature"
    width="400"
    style="border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
</p>

[![Build Status](https://github.com/CodeSleeve/holloway/workflows/tests/badge.svg)](https://github.com/CodeSleeve/holloway/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/codesleeve/holloway.svg)](https://packagist.org/packages/codesleeve/holloway)
[![PHP Version](https://img.shields.io/packagist/php-v/codesleeve/holloway.svg)](https://packagist.org/packages/codesleeve/holloway)
[![Total Downloads](https://img.shields.io/packagist/dt/codesleeve/holloway.svg)](https://packagist.org/packages/codesleeve/holloway)
[![License](https://img.shields.io/github/license/CodeSleeve/holloway.svg)](https://github.com/CodeSleeve/holloway/blob/master/LICENSE)

Holloway is an ORM toolkit for Laravel that let's you create your own custom datamappers on top of Laravel's `illuminate/database` package. It offers you the query builder you know and love, but at the same time gives you complete control over how your ORM records (Entities) are mapped to and from your database. You can create unbreakable domain models (Entities that are never allowed to exist in an invalid state), with complete control over their construction and behavior throughout the entire application request lifecycle.


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

## Contributing

Thank you for your interest in improving Holloway! We're currently not accepting PRs at the moment. If you find a bug or have a proposal, please open an issue.

## License

Holloway is open-sourced software licensed under the [MIT license](./LICENSE).
