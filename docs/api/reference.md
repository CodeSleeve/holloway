# API Reference

Reference for Holloway's core classes and interfaces. Covers the primary public API; see source for the full list of methods.

## Table of Contents

- [Mapper](#mapper)
- [Builder](#builder)
- [Scope](#scope)
- [SoftDeletes Trait](#softdeletes-trait)
- [EntityCache](#entitycache)
- [Events](#events)
- [Exceptions](#exceptions)
- [Next Steps](#next-steps)

## Mapper

Base abstract class for all data mappers.

```php
abstract class CodeSleeve\Holloway\Mapper
```

### Configurable Properties

```php
protected string $entityClassName = '';  // Entity class FQCN
protected string $table = '';            // Database table name
protected string $primaryKey = 'id';     // Primary key column
protected string $keyType = 'int';       // Primary key type
protected string $connection = '';       // Database connection name
protected bool $hasTimestamps = true;    // Enable created_at/updated_at
protected string $timestampFormat = 'Y-m-d H:i:s';
protected bool $incrementing = true;     // Auto-incrementing primary key
protected int $perPage = 15;             // Default pagination page size
protected array $with = [];              // Relationships to always eager load
```

### Abstract Methods

All concrete mappers must implement these:

```php
abstract public function getEntityClassName(): string;

abstract public function defineRelations(): void;

abstract public function getIdentifier($entity): mixed;

abstract public function setIdentifier($entity, $value): void;

abstract public function hydrate(stdClass $record, Collection $relations): mixed;

abstract public function dehydrate($entity): array;
```

### Querying

#### `all(): Collection`

Retrieve all entities.

```php
$users = $userMapper->all();
```

#### `query(): Builder`

Get a new query builder with global scopes applied.

```php
$activeUsers = $userMapper->query()
    ->where('active', true)
    ->orderBy('name')
    ->get();
```

#### `newQueryWithoutScope(Scope|string $scope): Builder`

Get a new query builder without a specific global scope.

```php
$allPosts = $postMapper->newQueryWithoutScope(PublishedScope::class)->get();
```

#### `newQueryWithoutScopes(): Builder`

Get a new query builder with no global scopes applied.

#### `with(mixed $relations): Builder`

Begin a query with eager loading.

```php
$posts = $postMapper->with(['author', 'category'])->get();
```

All other query methods (`where`, `orderBy`, `limit`, `join`, etc.) are proxied via `__call` to the underlying `Illuminate\Database\Query\Builder` and return the Holloway `Builder` instance.

### Persistence

#### `store($entity): bool`

Insert or update an entity. Fires `storing`/`creating`/`created` (for new entities) or `storing`/`updating`/`updated` (for existing entities), then `stored`.

```php
$post = new Post('My Title', 'My content');
$postMapper->store($post);
```

#### `remove($entity): bool`

Delete an entity. Fires `removing` then `removed`. If the mapper uses `SoftDeletes`, sets `deleted_at` instead of removing the row.

```php
$postMapper->remove($post);
```

### Global Scopes

#### `addGlobalScope(Scope|Closure|string $scope, ?Closure $implementation = null): void`

Add a global scope that applies to all queries from this mapper.

```php
// Register a Scope instance (anonymous, identified by class name)
static::addGlobalScope(new PublishedScope());

// Register a closure scope with a name
static::addGlobalScope('active', function(Builder $builder) {
    $builder->where('active', true);
});

// Register an anonymous closure scope
static::addGlobalScope(function(Builder $builder) {
    $builder->where('active', true);
});
```

> **Note:** Calling `addGlobalScope('name', new ScopeInstance())` is invalid — the second argument must be a `Closure`, not a `Scope` instance. Use `addGlobalScope(new ScopeInstance())` to register an instance.

Global scopes are registered in `__construct()`:

```php
class PostMapper extends Mapper
{
    public function __construct()
    {
        parent::__construct();

        static::addGlobalScope(new PublishedScope());
    }
}
```

#### `removeGlobalScope(Scope|string $scope): void`

Remove a registered global scope by class instance or string name.

```php
PostMapper::removeGlobalScope(PublishedScope::class);
PostMapper::removeGlobalScope('active');
```

#### `hasGlobalScope(Scope|string $scope): bool`

Check whether a global scope is registered.

#### `getGlobalScope(Scope|string $scope): Scope|Closure|string|null`

Retrieve a registered global scope by class or name.

### Events

#### `registerPersistenceEvent(string $eventName, callable $callback): void`

Register a listener for a named persistence event. See [Events](#events) for available event names.

```php
$this->registerPersistenceEvent('created', function(Post $post) {
    Cache::tags(['posts'])->flush();
});
```

### Entity Cache

#### `clearEntityCache(): void`

Flush the mapper's internal entity cache. Called automatically by `chunk()` and `chunkById()` between batches.

#### `flushEntityCache(): void`

Alias for `clearEntityCache()`.

#### `getNumberOfCachedEntities(): int`

Return the number of entities currently in the cache.

---

## Builder

Fluent query builder wrapping `Illuminate\Database\Query\Builder`.

```php
class CodeSleeve\Holloway\Builder
```

### Result Retrieval

#### `get(): Collection`

Execute the query and return hydrated entities.

```php
$posts = $postMapper->where('published', true)->get();
```

#### `first(): mixed|null`

Return the first result or null.

#### `firstOrFail(): mixed`

Return the first result or throw `ModelNotFoundException`.

#### `find(int|string|array $id): mixed`

Find by primary key. Returns a single entity, a Collection (for array input), or null.

```php
$post = $postMapper->find(1);
$posts = $postMapper->find([1, 2, 3]);
```

#### `findOrFail(int|string $id): mixed`

Find by primary key or throw `ModelNotFoundException`.

### Eager Loading

#### `with(mixed $relations): self`

Eager load relationships.

```php
$postMapper->with(['author', 'comments' => function($query) {
    $query->where('approved', true);
}])->get();
```

#### `without(mixed $relations): self`

Remove relationships from the eager load list (useful for overriding `$with`).

#### `withCount(mixed $relations): self`

Add subselect count queries for relationships.

```php
$users = $userMapper->withCount(['posts', 'posts as published_posts' => function($query) {
    $query->where('published', true);
}])->get();

echo $users->first()->posts_count;
echo $users->first()->published_posts;
```

The count attribute is the relationship name in `snake_case` with a `_count` suffix, or the alias if specified with `as`.

### Pagination

#### `paginate(int $perPage = null): LengthAwarePaginator`

Paginate results with a total count.

```php
$posts = $postMapper->where('published', true)->paginate(20);
```

#### `simplePaginate(int $perPage = null): Paginator`

Paginate results without a total count (more efficient for large tables).

### Chunking

#### `chunk(int $count, callable $callback): bool`

Process results in chunks to reduce memory usage. Clears the entity cache between batches.

```php
$postMapper->with('author')->chunk(100, function(Collection $posts) {
    foreach ($posts as $post) {
        processPost($post);
    }
});
```

#### `chunkById(int $count, callable $callback, ?string $column = null, ?string $alias = null): bool`

Chunk using cursor-based pagination by ID (avoids offset issues on large tables).

### Global Scopes on Builder

#### `withGlobalScope(string $identifier, Scope|Closure $scope): self`

Register a global scope on this builder instance (called internally by `newQuery()`). If the scope has an `extend()` method it is called immediately to register builder macros.

#### `withoutGlobalScope(Scope|string $scope): Builder`

Remove a specific global scope from the query.

```php
$postMapper->query()->withoutGlobalScope(PublishedScope::class)->get();
$postMapper->query()->withoutGlobalScope('active')->get();
```

#### `withoutGlobalScopes(?array $scopes): Builder`

Remove all global scopes (pass `null`) or a specific list.

```php
// Remove all global scopes
$postMapper->query()->withoutGlobalScopes(null)->get();

// Remove specific scopes
$postMapper->query()->withoutGlobalScopes([PublishedScope::class, 'active'])->get();
```

#### `removedScopes(): array`

Return the list of scope identifiers that have been removed from this query.

### Delete Methods

#### `delete(): mixed`

Execute a delete against the query. When `SoftDeletingScope` is registered, the `onDelete` callback is used to set `deleted_at` instead.

> **Note:** The builder-level soft-delete path currently has a bug (calls a nonexistent mapper method). Use mapper-level `remove()` for soft deletes.

#### `forceDelete(): mixed`

Execute a hard delete, bypassing any `onDelete` callback.

### Proxied Methods

All methods not explicitly defined on `Builder` are proxied to the underlying `Illuminate\Database\Query\Builder`. This includes `select`, `selectRaw`, `where`, `orWhere`, `whereIn`, `whereNull`, `whereNotNull`, `whereBetween`, `orderBy`, `groupBy`, `having`, `join`, `leftJoin`, `limit`, `offset`, `skip`, `take`, `distinct`, `union`, `when`, and more.

---

## Scope

Interface that all global scope classes must implement.

```php
interface CodeSleeve\Holloway\Scope
```

### Required Method

```php
public function apply(Builder $builder, Mapper $mapper): void;
```

Both parameters are required. Example:

```php
use CodeSleeve\Holloway\Scope;
use CodeSleeve\Holloway\Builder;
use CodeSleeve\Holloway\Mapper;

class PublishedScope implements Scope
{
    public function apply(Builder $builder, Mapper $mapper): void
    {
        $builder->where('published', true);
    }
}
```

---

## SoftDeletes Trait

Add to a **mapper** (not entity) to enable soft delete behavior. You must also explicitly register `SoftDeletingScope` as a global scope:

```php
use CodeSleeve\Holloway\SoftDeletes;
use CodeSleeve\Holloway\SoftDeletingScope;

class PostMapper extends Mapper
{
    use SoftDeletes;

    public function __construct()
    {
        parent::__construct();
        static::addGlobalScope(new SoftDeletingScope());
    }
}
```

The trait provides flags and helper methods. `SoftDeletingScope` is what adds `WHERE deleted_at IS NULL` to all queries and provides the `withTrashed()`, `onlyTrashed()`, and `withoutTrashed()` builder macros.

### Methods

#### `forceRemove($entity): bool|null`

Hard-delete an entity, bypassing soft delete logic.

```php
$post = $postMapper->withTrashed()->find(1);
$postMapper->forceRemove($post);
```

#### `restore($entity): bool|null`

Restore a soft-deleted entity or iterable of entities. Fires `restoring`/`restored` events.

```php
$post = $postMapper->onlyTrashed()->find(1);
$postMapper->restore($post);

// Or restore multiple
$posts = $postMapper->onlyTrashed()->where('author_id', 5)->get();
$postMapper->restore($posts);
```

#### `getDeletedAtColumnName(): string`

Return the soft delete column name (`deleted_at` by default, or `static::DELETED_AT` if defined).

#### `getQualifiedDeletedAtColumn(): string`

Return the fully-qualified column name (`table.deleted_at`).

### Custom Column

```php
class PostMapper extends Mapper
{
    use SoftDeletes;

    const DELETED_AT = 'archived_at';
}
```

---

## EntityCache

Internal per-mapper cache of raw entity attribute arrays (keyed by primary key). Used internally by the mapper during hydration.

```php
class CodeSleeve\Holloway\EntityCache
```

```php
public function get(string $identifier): ?array
public function set(string $identifier, array $attributes): void
public function has(string $identifier): bool
public function all(): array
public function count(): int
public function merge(array $records): void
public function remove(string $identifier): void
public function flush(): void
```

---

## Events

Holloway dispatches string-based events using the Laravel event dispatcher. Events are formatted as:

```
"eventName: FullyQualifiedEntityClassName"
```

### Event Names

| Event | When | Cancellable |
|-------|------|-------------|
| `storing` | Before create or update | Yes |
| `creating` | Before a new entity is inserted | Yes |
| `created` | After a new entity is inserted | No |
| `updating` | Before an existing entity is updated | Yes |
| `updated` | After an existing entity is updated | No |
| `stored` | After create or update completes | No |
| `removing` | Before an entity is removed | Yes |
| `removed` | After an entity is removed | No |
| `restoring` | Before a soft-deleted entity is restored | Yes |
| `restored` | After a soft-deleted entity is restored | No |

### Registering Listeners

Use `registerPersistenceEvent()` on the mapper:

```php
class PostMapper extends Mapper
{
    public function __construct()
    {
        parent::__construct();

        $this->registerPersistenceEvent('created', function(Post $post) {
            Cache::tags(['posts'])->flush();
        });

        $this->registerPersistenceEvent('removing', function(Post $post) {
            if ($post->hasActiveOrders()) {
                return false; // Cancels the remove
            }
        });
    }
}
```

Return `false` from a `storing`, `creating`, `updating`, or `removing` listener to cancel the operation.

---

## Exceptions

### `Illuminate\Database\Eloquent\ModelNotFoundException`

Thrown by `findOrFail()` and `firstOrFail()` when no matching entity exists.

### `CodeSleeve\Holloway\Exceptions\UknownRelationshipException`

Thrown when accessing a relationship that has not been defined in `defineRelations()`.

---

## Next Steps

- **[Eager Loading](../relationships/eager-loading.md)** - Optimizing relationship loading
- **[Query Scopes](../mappers/scopes.md)** - Reusable query constraints
- **[Events](../advanced/events.md)** - Full event system documentation
- **[Soft Deletes](../advanced/soft-deletes.md)** - Soft delete behavior
