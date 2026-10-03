# Architecture Overview

This document explains how Holloway works under the hood: the design patterns, lifecycle hooks, and infrastructure that power the data mapper. You do **not** need any of this to ship your first feature—treat it as the engineering manual once you're comfortable with the basics.

> **Audience:** framework contributors, senior engineers integrating Holloway deeply, or anyone debugging advanced scenarios.  
> **Prerequisites:** you've completed the [Getting Started](./getting-started.md) tutorial and shipped at least one mapper in your application.

## Table of Contents

- [Before you dive in](#before-you-dive-in)
- [The Datamapper Pattern](#the-datamapper-pattern)
- [Core Components](#core-components)
- [Data Flow](#data-flow)
- [Performance Considerations](#performance-considerations)
- [Next Steps](#next-steps)

## Before you dive in

- Looking for setup instructions? Head back to [the readme](../readme.md#documentation).
- Need to wire a mapper or relationship? Start with [Mapper Query Building](./mappers/query-building.md) and [Relationships Overview](./relationships/overview.md).
- If you’re exploring internals to extend Holloway, keep this page handy—but skim the section summaries first so you can jump straight to what you need.

---

Understanding Holloway's architecture is key to leveraging its full potential. This guide explores the core design patterns and how they work together to provide a robust datamapper implementation.

## The Datamapper Pattern

Holloway implements Martin Fowler's **Datamapper Pattern**, which provides complete separation between domain logic and data access logic.

### Pattern Components

```text
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│                 │    │                 │    │                 │
│    ENTITIES     │    │     MAPPERS     │    │    DATABASE     │
│  (Domain Logic) │    │ (Data Access)   │    │   (Storage)     │
│                 │    │                 │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       │
         └───── No Knowledge ────┘                       │
                                 │                       │
                                 └─── Handles I/O ──────┘
```

### Comparison with Active Record

| Aspect | Active Record (Eloquent) | Datamapper (Holloway) |
|--------|--------------------------|----------------------|
| **Domain Logic** | Mixed with persistence | Separated |
| **Entity Dependencies** | Requires database connection | Database-agnostic |
| **Testing** | Requires database mocking | Pure unit testing possible |
| **Entity Construction** | Framework controlled | Application controlled |
| **Performance** | Lazy loading, N+1 potential | Explicit eager loading |

## Core Components

### 1. Holloway Registry (Singleton)

The central registry manages mapper instances and provides a clean API:

```php
namespace CodeSleeve\Holloway;

final class Holloway
{
    private static $instance;
    private $mappers = [];

    public static function instance(): self
    {
        if (!static::$instance) {
            static::$instance = new self;
        }
        return static::$instance;
    }

    public function register($mapperClasses): void
    {
        // Register mappers by entity class name
    }

    public function getMapper($entityName): Mapper
    {
        // Return mapper for given entity
    }
}
```

**Key Features:**

- **Singleton pattern** ensures single mapper instance per entity type
- **Entity-based lookup** - get mappers by entity class, not mapper class
- **Automatic relationship resolution** during registration

### 2. Mapper Base Class

Mappers handle all database operations and entity lifecycle. As of vX.X.X, you can override the `currentTime()` method on your mapper to control how timestamps (such as `created_at`, `updated_at`, and `deleted_at`) are set. This is useful for custom time zones, deterministic testing, or advanced use cases.

```php
abstract class Mapper
{
    // Configuration
    protected string $entityClassName = '';
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected bool $hasTimestamps = true;
    /**
     * Override this to control how Holloway sets timestamps (created_at, updated_at, deleted_at).
     * By default, returns the current UTC time.
     */
    protected function currentTime(): \DateTime
    {
        return new \DateTime('now', new \DateTimeZone(static::DEFAULT_TIME_ZONE));
    }
    
    // Core functionality
    abstract public function defineRelations(): void;
    abstract public function dehydrate($entity): array;
    abstract public function hydrate(stdClass $record, Collection $relations);
    
    // Query building
    public function newQuery(): Builder
    public function with($relations): Builder
    public function where($column, $operator, $value): Builder
    
    // Persistence
    public function store($entity): bool
    public function remove($entity): bool
    
    // Entity lifecycle
    public function makeEntity(stdClass $record): mixed
    public function makeEntities(Collection $records): Collection
}
```

**Responsibilities:**

- **Entity hydration/dehydration** - converting between database records and entities
- **Query building** - providing fluent query interface
- **Relationship definition** - declaring entity relationships
- **Persistence operations** - storing and removing entities
- **Entity caching** - performance optimization

### 3. Query Builder

Provides Laravel-compatible query syntax while maintaining datamapper principles:

```php
class Builder
{
    protected QueryBuilder $query;
    protected Mapper $mapper;
    protected Tree $tree;  // Relationship loading tree
    
    public function find($id)
    public function where($column, $operator, $value): self
    public function with($relations): self
    public function get(): Collection
    public function first()
    public function paginate(int $perPage): PaginatorContract
}
```

**Features:**

- **Fluent interface** similar to Eloquent
- **Relationship loading** through Tree component
- **Scope application** (global and local scopes)
- **Entity caching integration**

### 4. Entity Cache

Optimizes performance by caching loaded entities:

```php
class EntityCache
{
    private string $primaryKey;
    private array $cache = [];
    
    public function set($identifier, array $attributes): void
    public function get($identifier): ?array
    public function has($identifier): bool
    public function remove($identifier): void
    public function flush(): void
}
```

**Benefits:**

- **Remembers the attributes of each loaded row** so `store()` can detect changes
- **Entries are kept current** when entities are stored and removed when they are removed
- **Not an identity map**: each query builds new entity objects

### 5. Relationship System

Handles complex entity relationships with multiple strategies:

```text
BaseRelationship (Abstract)
├── HasOneOrMany (Abstract)
│   ├── HasOne
│   └── HasMany
├── BelongsTo
├── BelongsToMany
└── Custom
```

**Relationship Loading Process:**

1. **Tree Building** - Parse relationship strings into tree structure
2. **Data Loading** - Execute optimized queries for each relationship level
3. **Entity Mapping** - Convert loaded data to entities
4. **Entity Attachment** - Attach related entities to parent entities

### 6. Relationship Tree

Manages complex nested relationship loading:

```php
class Tree
{
    protected array $loads = [];
    protected array $data = [];
    
    public function addLoads($loads): self
    public function loadInto(Collection $records): Collection
    protected function buildTree(): array
    protected function loadData(array $nodes, Collection $records): void
    protected function mapData(Collection $records, array $nodes): Collection
}
```

**Process Flow:**

```text
'posts.comments.author' → Tree Structure → Optimized Queries → Entity Attachment
```

## Data Flow

### Entity Creation Flow

```text
1. Database Query
   ↓
2. Raw stdClass Records
   ↓
3. Entity Cache Check
   ↓
4. Mapper::hydrate()
   ↓
5. Domain Entity Instance
   ↓
6. Relationship Loading (if requested)
   ↓
7. Complete Entity Graph

```

### Entity Persistence Flow

```text
1. Domain Entity
   ↓
2. Dirty Checking (via cache)
   ↓
3. Mapper::dehydrate()
   ↓
4. Database Record Array
   ↓
5. INSERT/UPDATE Query
   ↓
6. Cache Update
   ↓
7. Event Firing
```

## Performance Considerations

### Entity Caching Strategy

- **Entity cache** - Remembers loaded rows' attributes for change detection (not an identity map)
- **Change detection** - Skips the UPDATE only when the dehydrated attributes are identical to the loaded row
- **Transactional batches** - Collections passed to `store()` / `remove()` are persisted inside one transaction

### Relationship Loading Optimization

- **Eager Loading** - Prevents N+1 queries
- **Batch Loading** - Single query per relationship level
- **Constraint Application** - Filters applied at database level

### Memory Management

- **Cache Flushing** - Automatic cache clearing in chunk operations
- **Chunking** - `chunk()` / `chunkById()` process large tables in batches
- **Selective Loading** - Load only requested relationships

## Next Steps

- **[Creating Mappers](./mappers/creating-mappers.md)** - Learn mapper implementation details
- **[Understanding Relationships](./relationships/overview.md)** - Deep dive into relationship system
- **[Entity Caching](./advanced/caching.md)** - How the entity cache works
