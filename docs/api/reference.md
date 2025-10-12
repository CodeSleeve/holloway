# API Reference

Complete reference documentation for Holloway's core classes and interfaces. This reference covers all public methods, properties, and configuration options.

## Table of Contents

- [Core Classes](#core-classes)
- [Configuration Options](#configuration-options)
- [Events](#events)
- [Exceptions](#exceptions)
- [Constants](#constants)
- [Next Steps](#next-steps)

## Core Classes

### Mapper

The base mapper class provides the foundation for all data mapping operations.

#### Class Declaration

```php
abstract class Mapper
```

#### Properties

```php
protected string $table;           // Database table name
protected string $primaryKey;      // Primary key column name  
protected string $connection;      // Database connection name
protected array $fillable;         // Mass-assignable attributes
protected array $hidden;           // Hidden attributes for serialization
protected array $casts;            // Attribute casting definitions
protected bool $timestamps;        // Enable automatic timestamps
```

#### Core Methods

##### find(int|string $id): ?object

Find an entity by its primary key.

```php
public function find(int|string $id): ?object
```

**Parameters:**
- `$id` - The primary key value

**Returns:** The entity instance or null if not found

**Example:**
```php
$user = $userMapper->find(123);
```

##### findOrFail(int|string $id): object

Find an entity by its primary key or throw an exception.

```php
public function findOrFail(int|string $id): object
```

**Parameters:**
- `$id` - The primary key value

**Returns:** The entity instance

**Throws:** `EntityNotFoundException` if not found

##### all(): Collection

Retrieve all entities from the table.

```php
public function all(): Collection
```

**Returns:** Collection of all entities

##### query(): Builder

Get a new query builder instance.

```php
public function query(): Builder
```

**Returns:** Query builder instance

**Example:**
```php
$activeUsers = $userMapper->query()
    ->where('active', true)
    ->orderBy('name')
    ->get();
```

##### save(object $entity): object

Save an entity to the database.

```php
public function save(object $entity): object
```

**Parameters:**
- `$entity` - The entity to save

**Returns:** The saved entity with updated attributes

##### delete(object $entity): bool

Delete an entity from the database.

```php
public function delete(object $entity): bool
```

**Parameters:**
- `$entity` - The entity to delete

**Returns:** Success boolean

##### create(array $attributes): object

Create and save a new entity.

```php
public function create(array $attributes): object
```

**Parameters:**
- `$attributes` - Entity attributes

**Returns:** The created entity

##### update(object $entity, array $attributes): object

Update an existing entity.

```php
public function update(object $entity, array $attributes): object
```

**Parameters:**
- `$entity` - The entity to update
- `$attributes` - New attribute values

**Returns:** The updated entity

#### Query Builder Methods

##### where(string $column, mixed $operator = null, mixed $value = null): Builder

Add a where clause to the query.

```php
public function where(string $column, mixed $operator = null, mixed $value = null): Builder
```

##### whereIn(string $column, array $values): Builder

Add a where in clause.

```php
public function whereIn(string $column, array $values): Builder
```

##### orderBy(string $column, string $direction = 'asc'): Builder

Add an order by clause.

```php
public function orderBy(string $column, string $direction = 'asc'): Builder
```

##### limit(int $limit): Builder

Limit the number of results.

```php
public function limit(int $limit): Builder
```

##### offset(int $offset): Builder

Set the query offset.

```php
public function offset(int $offset): Builder
```

#### Relationship Methods

##### with(array|string $relations): Builder

Eager load relationships.

```php
public function with(array|string $relations): Builder
```

**Parameters:**
- `$relations` - Relationship names or associative array with constraints

**Example:**
```php
$posts = $postMapper->with(['author', 'comments' => function($query) {
    $query->where('approved', true);
}])->get();
```

##### has(string $relation, string $operator = '>=', int $count = 1): Builder

Add a relationship existence constraint.

```php
public function has(string $relation, string $operator = '>=', int $count = 1): Builder
```

##### whereHas(string $relation, callable $callback): Builder

Add a relationship constraint with callback.

```php
public function whereHas(string $relation, callable $callback): Builder
```

#### Scope Methods

##### addGlobalScope(string|Scope $identifier, Scope $scope = null): void

Add a global scope to the mapper.

```php
public function addGlobalScope(string|Scope $identifier, Scope $scope = null): void
```

##### removeGlobalScope(string|Scope $identifier): void

Remove a global scope.

```php
public function removeGlobalScope(string|Scope $identifier): void
```

##### withoutGlobalScope(string|Scope $identifier): Builder

Create a query without a specific global scope.

```php
public function withoutGlobalScope(string|Scope $identifier): Builder
```

##### withoutGlobalScopes(): Builder

Create a query without any global scopes.

```php
public function withoutGlobalScopes(): Builder
```

#### Caching Methods

##### remember(int $seconds): Builder

Cache the query results.

```php
public function remember(int $seconds): Builder
```

##### rememberForever(): Builder

Cache the query results forever.

```php
public function rememberForever(): Builder
```

##### flush(): void

Clear all cached results for this mapper.

```php
public function flush(): void
```

#### Event Methods

##### creating(callable $callback): void

Register a creating event listener.

```php
public function creating(callable $callback): void
```

##### created(callable $callback): void

Register a created event listener.

```php
public function created(callable $callback): void
```

##### updating(callable $callback): void

Register an updating event listener.

```php
public function updating(callable $callback): void
```

##### updated(callable $callback): void

Register an updated event listener.

```php
public function updated(callable $callback): void
```

##### deleting(callable $callback): void

Register a deleting event listener.

```php
public function deleting(callable $callback): void
```

##### deleted(callable $callback): void

Register a deleted event listener.

```php
public function deleted(callable $callback): void
```

### Builder

The query builder provides a fluent interface for constructing database queries.

#### Class Declaration

```php
class Builder
```

#### Query Methods

##### select(array|string $columns): Builder

Set the columns to select.

```php
public function select(array|string $columns): Builder
```

##### selectRaw(string $expression, array $bindings = []): Builder

Add a raw select expression.

```php
public function selectRaw(string $expression, array $bindings = []): Builder
```

##### distinct(): Builder

Add a distinct clause.

```php
public function distinct(): Builder
```

##### join(string $table, string $first, string $operator, string $second): Builder

Add an inner join.

```php
public function join(string $table, string $first, string $operator, string $second): Builder
```

##### leftJoin(string $table, string $first, string $operator, string $second): Builder

Add a left join.

```php
public function leftJoin(string $table, string $first, string $operator, string $second): Builder
```

##### groupBy(string ...$columns): Builder

Add group by clauses.

```php
public function groupBy(string ...$columns): Builder
```

##### having(string $column, string $operator, mixed $value): Builder

Add a having clause.

```php
public function having(string $column, string $operator, mixed $value): Builder
```

##### union(Builder $query): Builder

Add a union clause.

```php
public function union(Builder $query): Builder
```

#### Execution Methods

##### get(): Collection

Execute the query and return all results.

```php
public function get(): Collection
```

##### first(): ?object

Execute the query and return the first result.

```php
public function first(): ?object
```

##### firstOrFail(): object

Execute the query and return the first result or fail.

```php
public function firstOrFail(): object
```

##### count(): int

Get the count of query results.

```php
public function count(): int
```

##### exists(): bool

Determine if any results exist.

```php
public function exists(): bool
```

##### max(string $column): mixed

Get the maximum value of a column.

```php
public function max(string $column): mixed
```

##### min(string $column): mixed

Get the minimum value of a column.

```php
public function min(string $column): mixed
```

##### avg(string $column): mixed

Get the average value of a column.

```php
public function avg(string $column): mixed
```

##### sum(string $column): mixed

Get the sum of a column.

```php
public function sum(string $column): mixed
```

#### Pagination Methods

##### paginate(int $perPage = 15): LengthAwarePaginator

Paginate the query results.

```php
public function paginate(int $perPage = 15): LengthAwarePaginator
```

##### simplePaginate(int $perPage = 15): Paginator

Simple pagination without total count.

```php
public function simplePaginate(int $perPage = 15): Paginator
```

##### chunk(int $count, callable $callback): bool

Process results in chunks.

```php
public function chunk(int $count, callable $callback): bool
```

### Scope

Base class for query scopes.

#### Class Declaration

```php
abstract class Scope
```

#### Abstract Methods

##### apply(Builder $builder): void

Apply the scope to a query builder.

```php
abstract public function apply(Builder $builder): void
```

### Relationship Classes

#### HasOne

One-to-one relationship.

```php
class HasOne extends Relationship
{
    public static function make(string $related, string $foreignKey, string $localKey = 'id'): self
    public function getResults(): ?object
    public function associate(object $entity): void
    public function dissociate(): void
}
```

#### HasMany

One-to-many relationship.

```php
class HasMany extends Relationship
{
    public static function make(string $related, string $foreignKey, string $localKey = 'id'): self
    public function getResults(): Collection
    public function create(array $attributes): object
    public function save(object $entity): object
    public function saveMany(array $entities): Collection
}
```

#### BelongsTo

Inverse one-to-one or one-to-many relationship.

```php
class BelongsTo extends Relationship
{
    public static function make(string $related, string $foreignKey, string $ownerKey = 'id'): self
    public function getResults(): ?object
    public function associate(object $entity): void
    public function dissociate(): void
}
```

#### BelongsToMany

Many-to-many relationship.

```php
class BelongsToMany extends Relationship
{
    public static function make(string $related, string $table, string $foreignPivotKey, string $relatedPivotKey): self
    public function getResults(): Collection
    public function attach(mixed $id, array $attributes = []): void
    public function detach(mixed $ids = null): int
    public function sync(array $ids): array
    public function toggle(mixed $ids): array
}
```

### EntityCache

Entity caching functionality.

```php
class EntityCache
{
    public function get(string $key): ?object
    public function put(string $key, object $entity, int $ttl = null): void
    public function forget(string $key): bool
    public function flush(): bool
    public function remember(string $key, int $ttl, callable $callback): mixed
}
```

### Factory

Entity factory for testing and seeding.

```php
abstract class Factory
{
    public static function new(): static
    public function count(int $count): self
    public function state(array $state): self
    public function create(array $attributes = []): object|Collection
    public function make(array $attributes = []): object|Collection
    public function for(object $parent): self
    public function afterCreating(callable $callback): self
    abstract protected function definition(): array
}
```

## Configuration Options

### Mapper Configuration

```php
class UserMapper extends Mapper
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    protected string $connection = 'mysql';
    protected array $fillable = ['name', 'email'];
    protected array $hidden = ['password'];
    protected array $casts = [
        'email_verified_at' => 'datetime',
        'active' => 'boolean',
    ];
    protected bool $timestamps = true;
}
```

### Global Configuration

```php
// config/holloway.php
return [
    'default_connection' => 'mysql',
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'prefix' => 'holloway:',
    ],
    'performance' => [
        'chunk_size' => 1000,
        'log_queries' => false,
    ],
];
```

## Events

### Available Events

- `holloway.creating` - Before entity creation
- `holloway.created` - After entity creation  
- `holloway.updating` - Before entity update
- `holloway.updated` - After entity update
- `holloway.deleting` - Before entity deletion
- `holloway.deleted` - After entity deletion
- `holloway.query.executed` - After query execution

### Event Payloads

```php
// Entity events
[
    'entity' => $entity,
    'mapper' => $mapper,
    'attributes' => $attributes // for update events
]

// Query events
[
    'sql' => $sql,
    'bindings' => $bindings,
    'time' => $executionTime,
    'mapper' => $mapper
]
```

## Exceptions

### Core Exceptions

- `EntityNotFoundException` - Entity not found
- `RelationshipNotFoundException` - Relationship not defined
- `InvalidQueryException` - Invalid query construction
- `CacheException` - Caching operation failed
- `ConnectionException` - Database connection failed

### Exception Handling

```php
try {
    $user = $userMapper->findOrFail(123);
} catch (EntityNotFoundException $e) {
    // Handle not found
} catch (ConnectionException $e) {
    // Handle connection error
}
```

## Constants

### Query Operators

```php
const OPERATORS = [
    '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
    'like', 'like binary', 'not like', 'ilike',
    '&', '|', '^', '<<', '>>',
    'rlike', 'not rlike', 'regexp', 'not regexp',
    '~', '~*', '!~', '!~*', 'similar to',
    'not similar to', 'not ilike', '~~*', '!~~*',
];
```

### Cache Keys

```php
const CACHE_KEYS = [
    'entity' => 'holloway:entity:{class}:{id}',
    'query' => 'holloway:query:{hash}',
    'relationship' => 'holloway:rel:{class}:{id}:{relation}',
];
```

This API reference provides comprehensive documentation for all public interfaces in Holloway. Use it as a reference when building applications or contributing to the framework.

## Next Steps

- **[Examples](../examples/complete-examples.md)** - Practical usage examples
- **[Best Practices](../examples/best-practices.md)** - Recommended patterns
