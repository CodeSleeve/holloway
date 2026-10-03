# Query Scopes

Query scopes in Holloway provide a clean, reusable way to encapsulate common query constraints and logic. They promote code reuse, improve readability, and maintain consistency across your application.

## Table of Contents

- [Understanding Scopes](#understanding-scopes)
- [Global Scopes](#global-scopes)
- [Local Scopes](#local-scopes)
- [Parameterized Global Scopes](#parameterized-global-scopes)
- [Soft Delete Scopes](#soft-delete-scopes)
- [Best Practices](#best-practices)

## Understanding Scopes

Scopes are predefined query modifications that can be applied to any query builder instance. They encapsulate common filtering, ordering, and constraint logic that you use repeatedly throughout your application.

### Benefits

- **Reusability** - Define complex logic once, use everywhere
- **Readability** - Expressive, chainable query methods
- **Maintainability** - Centralized query logic
- **Testability** - Isolated, focused query components

## Global Scopes

Global scopes are automatically applied to all queries for a mapper unless explicitly removed.

### Defining Global Scopes

```php
use CodeSleeve\Holloway\Scope;
use CodeSleeve\Holloway\Builder;
use CodeSleeve\Holloway\Mapper;

class ActiveScope implements Scope
{
    public function apply(Builder $builder, Mapper $mapper): void
    {
        $builder->where('active', true);
    }
}

class PublishedScope implements Scope
{
    public function apply(Builder $builder, Mapper $mapper): void
    {
        $builder->where('published', true)
                ->where('published_at', '<=', now());
    }
}
```

### Registering Global Scopes

```php
class PostMapper extends Mapper
{
    public function __construct()
    {
        parent::__construct();
        
        // Apply to all queries by default
        static::addGlobalScope(new PublishedScope());
        static::addGlobalScope(new ActiveScope());
    }
}

// All queries will include published and active constraints
$posts = app(PostMapper::class)->all(); // WHERE published = 1 AND active = 1
```

### Removing Global Scopes

```php
class PostMapper extends Mapper
{
    public function findAllIncludingDrafts(): Collection
    {
        return $this->query()
            ->withoutGlobalScope(PublishedScope::class)
            ->get();

        // Or alternatively, use the newQueryWithoutScope() shortcut
        // return $this->newQueryWithoutScope(PublishedScope::class)->get();
    }

    public function findIncludingInactive(): Collection
    {
        return $this->query()
            ->withoutGlobalScope('active')
            ->get();
    }

    public function findAllUnfiltered(): Collection
    {
        return $this->query()
            ->withoutGlobalScopes(null)
            ->get();

        // Or alternatively, use the newQueryWithoutScopes() shortcut
        // return $this->newQueryWithoutScopes()->get();
    }
}
```

## Local Scopes

Local scopes are chainable methods that apply specific constraints to a query. They're defined directly on the mapper and can accept parameters.

### Defining Local Scopes

```php
class PostMapper extends Mapper
{
    public function scopeByAuthor(Builder $query, int $authorId): Builder
    {
        return $query->where('author_id', $authorId);
    }

    public function scopeByCategory(Builder $query, string $categorySlug): Builder
    {
        return $query->whereIn('category_id', function($q) use ($categorySlug) {
            $q->select('id')->from('categories')->where('slug', $categorySlug);
        });
    }

    public function scopePopular(Builder $query, int $minViews = 1000): Builder
    {
        return $query->where('view_count', '>=', $minViews)
                     ->orderBy('view_count', 'desc');
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days))
                     ->orderBy('created_at', 'desc');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true)
                     ->orderBy('featured_at', 'desc');
    }
}
```

### Using Local Scopes

```php
$postMapper = app(PostMapper::class);

// Single scope
$authorPosts = $postMapper->byAuthor(123)->get();

// Chained scopes
$popularRecent = $postMapper->popular(500)
                           ->recent(7)
                           ->get();

// Scopes with relationships
$categoryPosts = $postMapper->byCategory('technology')
                           ->featured()
                           ->with('author')
                           ->get();

// Complex combinations
$results = $postMapper->byAuthor(123)
                     ->byCategory('tech')
                     ->popular(100)
                     ->recent(14)
                     ->paginate(10);
```

## Parameterized Global Scopes

Create global scopes that accept parameters:

```php
class TenantScope implements Scope
{
    private int $tenantId;

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    public function apply(Builder $builder, Mapper $mapper): void
    {
        $builder->where('tenant_id', $this->tenantId);
    }
}

class PostMapper extends Mapper
{
    public function setTenant(int $tenantId): void
    {
        static::addGlobalScope(new TenantScope($tenantId));
    }
}

// Usage in service provider or middleware
$postMapper = app(PostMapper::class);
$postMapper->setTenant(auth()->user()->getTenantId());
```

## Soft Delete Scopes

Soft delete behavior requires both the `SoftDeletes` trait and explicit registration of `SoftDeletingScope` as a global scope:

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

`SoftDeletingScope` adds `WHERE deleted_at IS NULL` to all queries and provides the `withTrashed()`, `onlyTrashed()`, and `withoutTrashed()` builder macros. The trait alone is not sufficient.

See [Soft Deletes](../advanced/soft-deletes.md) for full documentation.

## Best Practices

1. **Keep Scopes Focused** - Each scope should have a single responsibility
2. **Use Descriptive Names** - Make scope purpose clear from the method name
3. **Document Complex Logic** - Add docblocks for non-obvious scope behavior
4. **Consider Performance** - Be mindful of indexes and query optimization
5. **Test Thoroughly** - Scopes are reused, so bugs affect multiple areas
6. **Avoid State Dependencies** - Scopes should be stateless and predictable
7. **Use `when()` for Conditionals** - Cleaner than if/else for optional filters
8. **Qualify Column Names** - Always use `table.column` in joins
9. **Validate Before Filtering** - Check for null/empty before applying constraints
10. **Extract Filter Defaults** - Make filter values and defaults explicit at the top

## Next Steps

- **[Query Building](./query-building.md)** - Advanced query construction
- **[Relationships](../relationships/overview.md)** - Working with related data
