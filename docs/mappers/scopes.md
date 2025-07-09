# Query Scopes

Query scopes in Holloway provide a clean, reusable way to encapsulate common query constraints and logic. They promote code reuse, improve readability, and maintain consistency across your application.

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
use Holloway\Scope;
use Holloway\Builder;

class ActiveScope extends Scope
{
    public function apply(Builder $builder): void
    {
        $builder->where('active', true);
    }
}

class PublishedScope extends Scope
{
    public function apply(Builder $builder): void
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
    protected function boot(): void
    {
        parent::boot();
        
        // Apply to all queries by default
        $this->addGlobalScope(new PublishedScope());
        
        // Named scope for easier removal
        $this->addGlobalScope('active', new ActiveScope());
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
            ->withoutGlobalScopes()
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
        return $query->whereHas('category', function($q) use ($categorySlug) {
            $q->where('slug', $categorySlug);
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

## Advanced Scope Patterns

### Conditional Scopes

Apply scopes based on conditions:

```php
class UserMapper extends Mapper
{
    public function scopeByRole(Builder $query, ?string $role = null): Builder
    {
        return $role ? $query->where('role', $role) : $query;
    }

    public function scopeActive(Builder $query, bool $activeOnly = true): Builder
    {
        return $activeOnly ? $query->where('active', true) : $query;
    }

    public function scopeVerified(Builder $query, bool $verifiedOnly = true): Builder
    {
        return $verifiedOnly ? $query->whereNotNull('email_verified_at') : $query;
    }

    public function findUsers(array $filters = []): Collection
    {
        return $this->query()
            ->byRole($filters['role'] ?? null)
            ->active($filters['active'] ?? true)
            ->verified($filters['verified'] ?? false)
            ->get();
    }
}
```

### Dynamic Scopes

Create scopes that adapt based on parameters:

```php
class ProductMapper extends Mapper
{
    public function scopeInPriceRange(Builder $query, ?float $min = null, ?float $max = null): Builder
    {
        if ($min !== null) {
            $query->where('price', '>=', $min);
        }
        
        if ($max !== null) {
            $query->where('price', '<=', $max);
        }
        
        return $query;
    }

    public function scopeByTags(Builder $query, array $tags): Builder
    {
        if (empty($tags)) {
            return $query;
        }

        return $query->whereHas('tags', function($q) use ($tags) {
            $q->whereIn('name', $tags);
        }, '>=', count($tags)); // Must have ALL tags
    }

    public function scopeAvailable(Builder $query, ?DateTime $date = null): Builder
    {
        $date = $date ?? now();
        
        return $query->where('available_from', '<=', $date)
                     ->where(function($q) use ($date) {
                         $q->whereNull('available_until')
                           ->orWhere('available_until', '>=', $date);
                     });
    }
}
```

### Relationship Scopes

Scopes that work with relationships:

```php
class OrderMapper extends Mapper
{
    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeWithItems(Builder $query, array $productIds = []): Builder
    {
        if (empty($productIds)) {
            return $query->has('items');
        }

        return $query->whereHas('items', function($q) use ($productIds) {
            $q->whereIn('product_id', $productIds);
        });
    }

    public function scopeCompletedBetween(Builder $query, DateTime $start, DateTime $end): Builder
    {
        return $query->where('status', 'completed')
                     ->whereBetween('completed_at', [$start, $end]);
    }

    public function scopeWithValue(Builder $query, float $minValue): Builder
    {
        return $query->whereHas('items', function($q) use ($minValue) {
            $q->selectRaw('SUM(quantity * price) as total_value')
              ->groupBy('order_id')
              ->having('total_value', '>=', $minValue);
        });
    }
}
```

## Scope Composition

Combine multiple scopes for complex queries:

```php
class PostMapper extends Mapper
{
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true)
                     ->where('published_at', '<=', now());
    }

    public function scopeTrending(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days))
                     ->orderByRaw('(view_count + comment_count * 2) DESC');
    }

    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    // Composed scope using other scopes
    public function scopeTrendingInLanguage(Builder $query, string $language, int $days = 7): Builder
    {
        return $query->published()
                     ->trending($days)
                     ->byLanguage($language);
    }

    // Method that combines multiple scopes
    public function findHomepagePosts(string $language = 'en'): Collection
    {
        return $this->query()
            ->trendingInLanguage($language)
            ->with('author', 'category')
            ->limit(10)
            ->get();
    }
}
```

## Parameterized Global Scopes

Create global scopes that accept parameters:

```php
class TenantScope extends Scope
{
    private int $tenantId;

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    public function apply(Builder $builder): void
    {
        $builder->where('tenant_id', $this->tenantId);
    }
}

class PostMapper extends Mapper
{
    public function setTenant(int $tenantId): void
    {
        $this->addGlobalScope('tenant', new TenantScope($tenantId));
    }
}

// Usage in service provider or middleware
$postMapper = app(PostMapper::class);
$postMapper->setTenant(auth()->user()->getTenantId());
```

## Soft Delete Scopes

Handling soft deletes with scopes:

```php
class SoftDeleteScope extends Scope
{
    public function apply(Builder $builder): void
    {
        $builder->whereNull('deleted_at');
    }
}

class PostMapper extends Mapper
{
    use SoftDeletes;

    protected function boot(): void
    {
        parent::boot();
        $this->addGlobalScope(new SoftDeleteScope());
    }

    public function scopeOnlyTrashed(Builder $query): Builder
    {
        return $query->withoutGlobalScope(SoftDeleteScope::class)
                     ->whereNotNull('deleted_at');
    }

    public function scopeWithTrashed(Builder $query): Builder
    {
        return $query->withoutGlobalScope(SoftDeleteScope::class);
    }
}
```

## Testing Scopes

### Testing Global Scopes

```php
class GlobalScopeTest extends TestCase
{
    public function testPublishedScopeAppliedByDefault(): void
    {
        // Arrange
        PostFactory::new()->published()->create();
        PostFactory::new()->draft()->create();

        // Act
        $posts = app(PostMapper::class)->all();

        // Assert
        $this->assertCount(1, $posts);
        $this->assertTrue($posts->first()->isPublished());
    }

    public function testCanRemoveGlobalScope(): void
    {
        // Arrange
        PostFactory::new()->published()->create();
        PostFactory::new()->draft()->create();

        // Act
        $allPosts = app(PostMapper::class)
            ->withoutGlobalScope(PublishedScope::class)
            ->all();

        // Assert
        $this->assertCount(2, $allPosts);
    }
}
```

### Testing Local Scopes

```php
class LocalScopeTest extends TestCase
{
    public function testByAuthorScope(): void
    {
        // Arrange
        $author = UserFactory::new()->create();
        $otherAuthor = UserFactory::new()->create();
        
        PostFactory::new()->for($author)->count(3)->create();
        PostFactory::new()->for($otherAuthor)->count(2)->create();

        // Act
        $authorPosts = app(PostMapper::class)
            ->byAuthor($author->getId())
            ->get();

        // Assert
        $this->assertCount(3, $authorPosts);
        foreach ($authorPosts as $post) {
            $this->assertEquals($author->getId(), $post->getAuthorId());
        }
    }

    public function testScopeChaining(): void
    {
        // Arrange
        $author = UserFactory::new()->create();
        PostFactory::new()->for($author)->popular()->recent()->count(2)->create();
        PostFactory::new()->for($author)->unpopular()->recent()->create();
        PostFactory::new()->for($author)->popular()->old()->create();

        // Act
        $results = app(PostMapper::class)
            ->byAuthor($author->getId())
            ->popular()
            ->recent()
            ->get();

        // Assert
        $this->assertCount(2, $results);
    }
}
```

## Performance Considerations

### Efficient Scope Implementation

```php
class PostMapper extends Mapper
{
    // Good - Uses database indexes
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days))
                     ->orderBy('created_at', 'desc');
    }

    // Consider indexing implications
    public function scopeByTitle(Builder $query, string $search): Builder
    {
        return $query->where('title', 'LIKE', "%{$search}%");
    }

    // Better for full-text search
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->whereRaw('MATCH(title, content) AGAINST(? IN BOOLEAN MODE)', [$term]);
    }
}
```

### Scope Caching

```php
class PostMapper extends Mapper
{
    public function scopePopularCached(Builder $query, int $minViews = 1000): Builder
    {
        $cacheKey = "posts.popular.{$minViews}";
        
        return cache()->remember($cacheKey, 3600, function() use ($query, $minViews) {
            return $query->where('view_count', '>=', $minViews)
                         ->orderBy('view_count', 'desc')
                         ->get();
        });
    }
}
```

## Best Practices

1. **Keep Scopes Focused** - Each scope should have a single responsibility
2. **Use Descriptive Names** - Make scope purpose clear from the method name
3. **Document Complex Logic** - Add docblocks for non-obvious scope behavior
4. **Consider Performance** - Be mindful of indexes and query optimization
5. **Test Thoroughly** - Scopes are reused, so bugs affect multiple areas
6. **Avoid State Dependencies** - Scopes should be stateless and predictable

## Common Patterns

### Search and Filter Scopes

```php
class ProductMapper extends Mapper
{
    public function scopeSearch(Builder $query, ?string $term = null): Builder
    {
        if (!$term) {
            return $query;
        }

        return $query->where(function($q) use ($term) {
            $q->where('name', 'LIKE', "%{$term}%")
              ->orWhere('description', 'LIKE', "%{$term}%")
              ->orWhere('sku', 'LIKE', "%{$term}%");
        });
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '') {
                switch ($field) {
                    case 'category':
                        $query->where('category_id', $value);
                        break;
                    case 'price_min':
                        $query->where('price', '>=', $value);
                        break;
                    case 'price_max':
                        $query->where('price', '<=', $value);
                        break;
                    case 'in_stock':
                        $query->where('stock_quantity', '>', 0);
                        break;
                }
            }
        }

        return $query;
    }
}
```

## Next Steps

- **[Query Building](./query-building.md)** - Advanced query construction
- **[Relationships](../relationships/overview.md)** - Working with related data
- **[Performance](../advanced/performance.md)** - Optimization techniques
