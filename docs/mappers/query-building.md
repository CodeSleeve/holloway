# Query Building

Holloway's query builder provides a fluent, Laravel-compatible interface for constructing database queries while maintaining the datamapper pattern's benefits. This guide covers everything from basic queries to advanced optimization techniques.

## Table of Contents

- [Basic Query Operations](#basic-query-operations)
- [Pagination](#pagination)
- [Chunking Large Datasets](#chunking-large-datasets)
- [Global Scopes](#global-scopes)
- [Query Builder with Relationships](#query-builder-with-relationships)
- [Next Steps](#next-steps)

## Basic Query Operations

### Simple Queries

```php
$userMapper = Holloway::instance()->getMapper(User::class);

// Find by primary key
$user = $userMapper->find(1);
$users = $userMapper->find([1, 2, 3]); // Multiple IDs

// Find or fail (throws exception if not found)
$user = $userMapper->findOrFail(1);

// Get all records
$users = $userMapper->all();

// Get first record
$user = $userMapper->first();
$user = $userMapper->firstOrFail();

// Check existence
$exists = $userMapper->exists();
$count = $userMapper->count();
```

### Where Clauses

```php
// Basic where clauses
$users = $userMapper->where('active', true)->get();
$users = $userMapper->where('created_at', '>', '2023-01-01')->get();
$users = $userMapper->where('email', 'LIKE', '%@example.com')->get();

// Multiple conditions
$users = $userMapper
    ->where('active', true)
    ->where('role', 'admin')
    ->where('created_at', '>', now()->subDays(30))
    ->get();

// Or conditions
$users = $userMapper
    ->where('role', 'admin')
    ->orWhere('role', 'moderator')
    ->get();

// Where in/not in
$users = $userMapper->whereIn('id', [1, 2, 3])->get();
$users = $userMapper->whereNotIn('role', ['banned', 'suspended'])->get();

// Null checks
$users = $userMapper->whereNull('deleted_at')->get();
$users = $userMapper->whereNotNull('email_verified_at')->get();

// Date queries
$users = $userMapper->whereDate('created_at', '2023-01-01')->get();
$users = $userMapper->whereMonth('created_at', 1)->get();
$users = $userMapper->whereYear('created_at', 2023)->get();

// Between queries
$users = $userMapper->whereBetween('created_at', ['2023-01-01', '2023-12-31'])->get();
```

### Advanced Where Conditions

```php
// Grouped conditions
$users = $userMapper
    ->where('active', true)
    ->where(function($query) {
        $query->where('role', 'admin')
              ->orWhere('role', 'moderator');
    })
    ->get();

// Raw where clauses
$users = $userMapper
    ->whereRaw('YEAR(created_at) = ?', [2023])
    ->get();

// JSON column queries (MySQL/PostgreSQL)
$users = $userMapper
    ->where('settings->theme', 'dark')
    ->where('settings->notifications->email', true)
    ->get();
```

## Pagination

### Simple Pagination

```php
// Simple paginate (Previous/Next only)
$users = $userMapper->simplePaginate(15);

// Full pagination with page numbers
$users = $userMapper->paginate(15);

// Custom page
$users = $userMapper->paginate(15, ['*'], 'page', 2);

// With query parameters preserved
$users = $userMapper
    ->where('active', true)
    ->orderBy('name')
    ->paginate(15);

// Access pagination data
echo "Page {$users->currentPage()} of {$users->lastPage()}";
echo "Showing {$users->count()} of {$users->total()} users";

// Iterate results
foreach ($users as $user) {
    echo $user->name;
}
```

### Custom Pagination

```php
// Custom per-page from request
$perPage = min(request('per_page', 15), 100); // Max 100 per page
$users = $userMapper->paginate($perPage);

// Pagination with relationships
$users = $userMapper
    ->with(['posts', 'profile'])
    ->where('active', true)
    ->paginate(20);
```

## Chunking Large Datasets

### Basic Chunking

```php
// Process large datasets in chunks
$userMapper->chunk(1000, function($users) {
    foreach ($users as $user) {
        // Process each user
        $this->processUser($user);
    }
});

// Chunk with query conditions
$userMapper
    ->where('active', true)
    ->where('created_at', '<', now()->subYear())
    ->chunk(500, function($users) {
        // Process inactive users
        foreach ($users as $user) {
            $this->archiveUser($user);
        }
    });
```

### Chunk by ID

```php
// More memory efficient for large datasets
$userMapper->chunkById(1000, function($users) {
    foreach ($users as $user) {
        $this->processUser($user);
    }
});

// Custom ID column
$userMapper->chunkById(1000, function($users) {
    // Process users
}, 'custom_id');
```

### Chunk with Relationships

```php
// Chunk with eager loaded relationships
$userMapper
    ->with(['posts', 'profile'])
    ->chunk(100, function($users) {
        foreach ($users as $user) {
            // Process user with loaded relationships
            $this->generateReport($user);
        }
    });
```

## Global Scopes

Automatically apply conditions to all queries:

```php
class UserMapper extends Mapper
{
    public function __construct()
    {
        parent::__construct();
        
        // Multi-tenant scope
        static::addGlobalScope('tenant', function($builder) {
            if (auth()->check()) {
                $builder->where('tenant_id', auth()->user()->tenant_id);
            }
        });
        
        // Soft delete scope (if not using SoftDeletes trait)
        static::addGlobalScope('notDeleted', function($builder) {
            $builder->whereNull('deleted_at');
        });
        
        // Active users only
        static::addGlobalScope('active', function($builder) {
            $builder->where('active', true);
        });
    }
}

// Remove global scope for specific queries
$allUsers = $userMapper->newQueryWithoutScope('active')->get();
$deletedUsers = $userMapper->newQueryWithoutScope('notDeleted')
                          ->whereNotNull('deleted_at')
                          ->get();

// Remove multiple scopes
$rawUsers = $userMapper->newQuery()
                      ->withoutGlobalScope('active')
                      ->withoutGlobalScope('tenant')
                      ->get();
```

## Query Builder with Relationships

### Relationship Constraints

```php
// Load users with specific post conditions
$users = $userMapper
    ->with(['posts' => function($query) {
        $query->where('published', true)
              ->where('created_at', '>', now()->subDays(30))
              ->orderBy('created_at', 'desc');
    }])
    ->get();

// Multiple relationship constraints
$users = $userMapper
    ->with([
        'posts' => function($query) {
            $query->where('published', true);
        },
        'profile' => function($query) {
            $query->select(['user_id', 'avatar', 'bio']);
        },
        'roles' => function($query) {
            $query->where('active', true);
        }
    ])
    ->get();
```

### Relationship Counting

Use `withCount()` to count related records without loading them:

```php
// Count a single relationship (adds a posts_count column)
$users = $userMapper->withCount('posts')->get();

// Count multiple relationships
$users = $userMapper->withCount(['posts', 'comments', 'likes'])->get();

// With constraints
$users = $userMapper->withCount([
    'posts' => function($query) {
        $query->where('published', true);
    }
])->get();

// With aliases
$users = $userMapper->withCount([
    'posts as total_posts',
    'posts as published_posts' => function($query) {
        $query->where('published', true);
    }
])->get();
```

Counts work with HasOne, HasMany, BelongsTo and BelongsToMany relationships, including relationships to the same table (such as a category's children). The column is named after the relationship in snake_case with a `_count` suffix, or the alias given with `as`. Custom relationships support it only if you give them a count closure.

**Where the count ends up.** The count is an extra column on the record that is handed to your mapper's `hydrate()`. It isn't a column of your table, so it's up to `hydrate()` where it goes on the entity, and `dehydrate()` must leave it out so that `store()` doesn't try to write it.

**Constraints.** The closure receives the related mapper's query builder. Use unqualified column names, as above: for a relationship to the same table, the related table is aliased inside the count, so a column qualified with the table's own name would refer to the parent query. The related mapper's global scopes (such as soft deletes) are applied after your constraints, and can be removed with `withoutGlobalScope()`.

## Next Steps

- **[Persistence Operations](./persistence.md)** - Learn entity storage and lifecycle management
- **[Scopes](./scopes.md)** - Master global and local scopes
- **[Relationship Loading](../relationships/eager-loading.md)** - Optimize relationship queries
