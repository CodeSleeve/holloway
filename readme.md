# Holloway 

<!-- ![image of a Holloway in nature](./annie-spratt-holloway-unsplash-small.jpg?raw=true) -->
<img
  src="./annie-spratt-holloway-unsplash-small.jpg"
  alt="image of a Holloway in nature"
  title="A Holloway in nature"
  style="display: block; margin: 0 auto; max-width: 320px">

Holloway is a sophisticated implementation of the datamapper pattern (Fowler), built on top of the `illuminate/database` package that powers Laravel's Eloquent ORM. While maintaining the familiar query builder syntax you know and love, Holloway provides complete separation between your domain entities and database persistence, enabling truly unbreakable domain objects.

> **Now supports recent versions of `illuminate/database`**
> Holloway is compatible with Laravel 8, 9, 10, and newer releases of the `illuminate/database` package. See the [installation instructions](#installation) for version details.

## Why Holloway?

**Unbreakable Domain Objects:** Create entities that are never allowed to exist in an invalid state, with complete control over their construction and behavior.

**Familiar Syntax:** Query using the same elegant syntax as Laravel's Eloquent, but with datamapper architecture benefits.

**Performance Optimized:** Built-in entity caching, optimized relationship loading, and efficient batch operations.

**Enterprise Ready:** Global scopes, soft deletes, event hooks, and transaction support for production applications.

## Quick Start

```php
// Register your mappers
Holloway::instance()->register([
    UserMapper::class,
    PostMapper::class,
]);

// Query like Eloquent, get immutable entities
$user = Holloway::instance()
    ->getMapper(User::class)
    ->with('posts.comments')
    ->find(1);

// Persist changes through mapper
$mapper = Holloway::instance()->getMapper(User::class);
$mapper->store($user);
```

## Installation

```bash
composer require codesleeve/holloway
```

## Documentation

### 📚 **Core Concepts**
- **[Getting Started](./docs/getting-started.md)** - Installation, basic setup, and your first mapper
- **[Architecture Overview](./docs/architecture.md)** - Understanding the datamapper pattern in Holloway
- **[Entities vs Models](./docs/entities-vs-models.md)** - Key differences from Active Record pattern

### 🗺️ **Mappers**
- **[Creating Mappers](./docs/mappers/creating-mappers.md)** - Mapper basics, configuration, and conventions
- **[Query Building](./docs/mappers/query-building.md)** - Querying, filtering, and retrieving entities
- **[Persistence Operations](./docs/mappers/persistence.md)** - Storing, updating, and removing entities
- **[Scopes](./docs/mappers/scopes.md)** - Global scopes and query scopes

### 🔗 **Relationships**
- **[Relationship Overview](./docs/relationships/overview.md)** - Understanding Holloway relationships
- **[Standard Relationships](./docs/relationships/standard.md)** - HasOne, HasMany, BelongsTo, BelongsToMany
- **[Custom Relationships](./docs/relationships/custom.md)** - Creating flexible custom relationships
- **[Eager Loading](./docs/relationships/eager-loading.md)** - Loading relationships efficiently
- **[Nested Relationships](./docs/relationships/nested.md)** - Deep relationship loading with constraints

### 🏗️ **Advanced Features**
- **[Entity Caching](./docs/advanced/caching.md)** - How entity caching works and performance benefits
- **[Soft Deletes](./docs/advanced/soft-deletes.md)** - Implementing soft deletion functionality
- **[Events & Hooks](./docs/advanced/events.md)** - Lifecycle events and persistence hooks
- **[Factories & Testing](./docs/advanced/factories.md)** - Creating test data and mocking
- **[Repository Pattern](./docs/advanced/repositories.md)** - Implementing repositories on top of mappers

### 🔧 **Laravel Integration**
- **[Service Provider](./docs/laravel/service-provider.md)** - Laravel auto-discovery and configuration
- **[Database Connections](./docs/laravel/connections.md)** - Multiple database support
- **[Pagination](./docs/laravel/pagination.md)** - Laravel-compatible pagination
- **[Artisan Commands](./docs/laravel/commands.md)** - Command-line tools and utilities

### 📖 **Examples & Patterns**
- **[Complete Examples](./docs/examples/complete-examples.md)** - Real-world usage patterns
- **[Migration Guide](./docs/examples/migration-guide.md)** - Migrating from Eloquent to Holloway
- **[Best Practices](./docs/examples/best-practices.md)** - Recommended patterns and conventions

### 🛠️ **API Reference**
- **[Holloway Class](./docs/api/holloway.md)** - Core registry methods
- **[Mapper Class](./docs/api/mapper.md)** - Complete mapper API
- **[Builder Class](./docs/api/builder.md)** - Query builder methods
- **[Relationship Classes](./docs/api/relationships.md)** - All relationship types

---

## Quick Example: Custom Relationship

```php
// In your mapper's defineRelations() method
$this->customMany('stickyNotes', function ($query, Collection $clientServices) {
    return $query->from('sticky_notes')
        ->select('sticky_notes.*', 'client_service_sticky_notes.client_service_id')
        ->join('client_service_sticky_notes', 'sticky_notes.id', '=', 'client_service_sticky_notes.sticky_note_id')
        ->whereIn('client_service_sticky_notes.client_service_id', $clientServices->pluck('id'))
        ->get();
}, function (stdClass $clientService, stdClass $stickyNote) {
    return $clientService->id == $stickyNote->client_service_id;
}, StickyNote::class);
```


## Contributing

Thank you for your interest in improving this project!
Currently, we are not accepting pull requests, as we are focused on maintaining a high standard of code quality and consistency.
If you encounter bugs or have ideas for improvement, please open an issue—your feedback is always welcome and appreciated.

## License

Holloway is open-sourced software licensed under the [MIT license](./LICENSE).