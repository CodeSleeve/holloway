# Query Scopes

Query scopes in Holloway provide a clean, reusable way to encapsulate common query constraints and logic. They promote code reuse, improve readability, and maintain consistency across your application.

## Table of Contents

- [Understanding Scopes](#understanding-scopes)
- [Global Scopes](#global-scopes)
- [Local Scopes](#local-scopes)
- [Advanced Scope Patterns](#advanced-scope-patterns)
- [Scope Composition](#scope-composition)
- [Parameterized Global Scopes](#parameterized-global-scopes)
- [Soft Delete Scopes](#soft-delete-scopes)
- [Testing Scopes](#testing-scopes)
- [Performance Considerations](#performance-considerations)
- [Real-World Patterns from Production](#real-world-patterns-from-production-application)
  - [Complex Search Scopes with when()](#complex-search-scopes-with-when-helper)
  - [Multi-Table Joins](#multi-table-joins-with-complex-filtering)
  - [Subqueries for Complex Matching](#subqueries-for-complex-matching)
  - [PostgreSQL-Specific Features](#postgresql-specific-features)
- [Best Practices](#best-practices)
- [Common Patterns](#common-patterns)

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

## Real-World Patterns from Production Application

A production multi-tenant SaaS application provides production-tested examples of advanced scope patterns. These demonstrate best practices for complex filtering, joins, and database-specific features.

### Complex Search Scopes with `when()` Helper

The `when()` helper applies constraints conditionally, making scopes clean and flexible:

```php
class ClientMapper extends Mapper
{
    /**
     * Comprehensive search scope with multiple optional filters
     */
    public function scopeSearch($query, array $filters)
    {
        $sortBy = $filters['sort_by'] ?? 'updated_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $locationId = $filters['location_id'] ?? null;
        $clientCompanyId = $filters['client_company_id'] ?? null;
        $statuses = $filters['statuses'] ?? [];
        $isDecisionMaker = $filters['is_decision_maker'] ?? null;
        $search = $filters['search'] ?? '';

        return $query
            // Text search across multiple columns
            ->when($search, function($query, $search) {
                $search = strtolower($search);

                $query->where(function($query) use ($search) {
                    $query->whereRaw('LOWER(clients.first_name) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(clients.last_name) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(clients.email) LIKE ?', ["%$search%"]);
                });
            })
            // Join-based filtering
            ->when($locationId, function($query, $locationId) {
                $clientIds = DB::table('clients_locations')
                    ->where('location_id', $locationId)
                    ->pluck('client_id');
                $query->whereIn('clients.id', $clientIds);
            })
            // Simple where clause
            ->when($clientCompanyId, function($query, $clientCompanyId) {
                $query->where('clients.client_company_id', $clientCompanyId);
            })
            // Array-based filtering
            ->when($statuses, fn($query) => $query->whereIn('clients.status', $statuses))
            // Boolean filtering with null check
            ->when($isDecisionMaker !== null && $isDecisionMaker !== '', function($query) use ($isDecisionMaker) {
                $query->where('clients.is_decision_maker', (bool) $isDecisionMaker);
            })
            ->orderBy($sortBy, $sortDirection);
    }
}
```

**Key patterns:**
- Extract all filter values with defaults at the top
- Use `when()` for conditional constraints - cleaner than if/else
- Wrap text searches in nested `where()` for proper grouping
- Use `whereRaw()` with parameter binding for case-insensitive search
- Validate booleans before applying (`!== null && !== ''`)

### Multi-Table Joins with Complex Filtering

When searching across relationships, use joins for better performance:

```php
class ServiceJobMapper extends Mapper
{
    public function scopeSearch($query, array $filters)
    {
        $sortBy = $filters['sort_by'] ?? 'updated_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $search = $filters['search'] ?? '';
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $locationId = $filters['location_id'] ?? null;
        $clientId = $filters['client_id'] ?? null;
        $serviceId = $filters['service_id'] ?? null;
        $unbilled = $filters['unbilled'] ?? null;
        $types = $filters['types'] ?? [];
        $statuses = $filters['statuses'] ?? [];

        return $query
            // Establish joins once
            ->join('services', 'service_jobs.service_id', '=', 'services.id')
            ->join('clients', 'services.client_id', '=', 'clients.id')
            ->leftJoin('locations', 'services.location_id', '=', 'locations.id')
            ->select('service_jobs.*')
            // Date range filtering
            ->when($startDate && $endDate, fn($query) => 
                $query->whereBetween('service_jobs.scheduled_start_date', [
                    $startDate->toDateTimeString(), 
                    $endDate->toDateTimeString()
                ]))
            // Simple foreign key filters
            ->when($clientId, fn($query) => $query->where('services.client_id', '=', $clientId))
            ->when($serviceId, fn($query) => $query->where('services.id', '=', $serviceId))
            ->when($locationId, fn($query) => $query->where('services.location_id', '=', $locationId))
            // Null check filter
            ->when($unbilled, fn($query) => $query->whereNull('service_jobs.billed_on'))
            // Array extraction with filtering
            ->when(count($types), function($query) use ($types) {
                $query->whereIn('services.service_type_id', array_map(fn($type) => $type['id'], $types));
            })
            // Simple array filter
            ->when(count($statuses), function($query) use ($statuses) {
                $query->whereIn('service_jobs.status', $statuses);
            })
            // Search across joined tables
            ->when($search, function($query, $search) {
                $search = strtolower($search);

                $query->where(function($query) use ($search) {
                    $query->whereRaw('LOWER(services.name) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(services.description) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(services.name) = ?', [$search])
                        ->orWhereRaw('LOWER(locations.name) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(clients.first_name) LIKE ?', ["%$search%"])
                        ->orWhereRaw('LOWER(clients.last_name) LIKE ?', ["%$search%"]);
                });
            })
            ->orderBy($sortBy, $sortDirection);
    }
}
```

**Key patterns:**
- Establish all joins at the beginning
- Use `select('service_jobs.*')` to avoid ambiguous column names
- Fully qualify column names in joins (`service_jobs.service_id`)
- Use `leftJoin` when the relationship is optional
- Check array count before applying `whereIn` for efficiency

### Subqueries for Complex Matching

Use subqueries when filtering by many-to-many relationships:

```php
class UserMapper extends Mapper
{
    public function scopeSearch($query, array $filters)
    {
        $roleIds = $filters['role_ids'] ?? [];
        $scheduledStartDate = $filters['scheduled_start_date'] ?? null;
        $estimatedCompletionDate = $filters['estimated_completion_date'] ?? null;

        return $query
            // Subquery for many-to-many filtering
            ->when($roleIds, function($query, $roleIds) {
                $query->whereIn('users.id', function($query) use ($roleIds) {
                    $query->select('user_id')
                        ->from('tenants_users')
                        ->whereIn('role_id', $roleIds);
                });
            })
            // Combine with other scopes
            ->when($scheduledStartDate && $estimatedCompletionDate, function($query) use ($scheduledStartDate, $estimatedCompletionDate) {
                $query->availableForDateRange($scheduledStartDate, $estimatedCompletionDate);
            })
            ->orderBy('updated_at', 'desc');
    }
}
```

### PostgreSQL-Specific Features

This example leverages PostgreSQL's advanced features:

```php
class UserMapper extends Mapper
{
    /**
     * Check availability using PostgreSQL tsrange (timestamp range) overlap
     */
    public function scopeAvailableForDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereNotExists(function($query) use ($startDate, $endDate) {
            $query->from('service_jobs_users')
                ->whereColumn('service_jobs_users.user_id', 'users.id')
                ->whereRaw("tsrange(?, ?) && tsrange(scheduled_start_date, estimated_completion_date)", [
                    $startDate,
                    $endDate
                ]);
        });
    }
}
```

**PostgreSQL features:**
- `tsrange()` - Timestamp range type
- `&&` operator - Range overlap detection
- `whereNotExists()` - Efficient "not in" queries

### Simple Status Scopes

Not all scopes need to be complex:

```php
class ServiceJobMapper extends Mapper
{
    public function scopeCompleted($query): Builder
    {
        return $query->where('status', '=', ServiceJob::STATUS_COMPLETED);
    }

    public function scopeUnbilled($query): Builder
    {
        return $query->whereNull('billed_on');
    }
}

class UserMapper extends Mapper
{
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTechnicians($query)
    {
        return $query->select('users.*')
            ->join('tenants_users', 'users.id', '=', 'tenants_users.user_id')
            ->join('roles', 'tenants_users.role_id', '=', 'roles.id')
            ->where('roles.name', 'technician');
    }
}
```

### Using DB Facade for Pivot Queries

When dealing with many-to-many relationships, the DB facade is often cleaner:

```php
use Illuminate\Support\Facades\DB;

class ClientMapper extends Mapper
{
    public function scopeByLocation($query, int $locationId)
    {
        $clientIds = DB::table('clients_locations')
            ->where('location_id', $locationId)
            ->pluck('client_id');
            
        return $query->whereIn('clients.id', $clientIds);
    }
}
```

**When to use DB facade:**
- Simple pivot table queries
- Need only IDs for a `whereIn()`
- Avoiding complex relationship loading overhead

### Combining Scopes in Practice

```php
// In a controller or service
$serviceJobMapper = app(ServiceJobMapper::class);

// Simple scope chaining
$completedJobs = $serviceJobMapper->completed()
    ->unbilled()
    ->get();

// Complex search scope
$filteredJobs = $serviceJobMapper->search([
    'search' => 'lawn mowing',
    'start_date' => now()->subDays(30),
    'end_date' => now(),
    'statuses' => ['scheduled', 'in_progress'],
    'client_id' => 123,
    'unbilled' => true,
    'sort_by' => 'scheduled_start_date',
    'sort_direction' => 'asc'
])->with('service', 'assignedTechnicians')->get();

// User filtering with role and availability
$userMapper = app(UserMapper::class);
$availableTechs = $userMapper->search([
    'role_ids' => [2], // Technician role
    'scheduled_start_date' => $jobStart,
    'estimated_completion_date' => $jobEnd
])->active()->get();
```

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
