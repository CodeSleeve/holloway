# Query Building

Holloway's query builder provides a fluent, Laravel-compatible interface for constructing database queries while maintaining the datamapper pattern's benefits. This guide covers everything from basic queries to advanced optimization techniques.

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

## Ordering and Limiting

### Order By

```php
// Simple ordering
$users = $userMapper->orderBy('name')->get();
$users = $userMapper->orderBy('created_at', 'desc')->get();

// Multiple order clauses
$users = $userMapper
    ->orderBy('role')
    ->orderBy('name')
    ->orderBy('created_at', 'desc')
    ->get();

// Raw order by
$users = $userMapper
    ->orderByRaw('FIELD(role, "admin", "moderator", "user")')
    ->get();

// Random ordering
$users = $userMapper->inRandomOrder()->get();

// Latest/oldest shortcuts
$users = $userMapper->latest()->get();        // ORDER BY created_at DESC
$users = $userMapper->latest('updated_at')->get();
$users = $userMapper->oldest()->get();        // ORDER BY created_at ASC
```

### Limiting Results

```php
// Take/limit
$users = $userMapper->take(10)->get();
$users = $userMapper->limit(10)->get();

// Skip/offset
$users = $userMapper->skip(20)->take(10)->get();
$users = $userMapper->offset(20)->limit(10)->get();

// First N results
$firstFive = $userMapper->orderBy('created_at')->take(5)->get();
```

## Aggregates and Calculations

### Count, Sum, Average

```php
// Count records
$totalUsers = $userMapper->count();
$activeUsers = $userMapper->where('active', true)->count();

// Sum values
$totalCredits = $userMapper->sum('credits');
$adminCredits = $userMapper->where('role', 'admin')->sum('credits');

// Average values
$avgCredits = $userMapper->avg('credits');
$avgAge = $userMapper->avg('age');

// Min/Max values
$oldestUser = $userMapper->min('created_at');
$newestUser = $userMapper->max('created_at');
$highestCredits = $userMapper->max('credits');
```

### Group By and Having

```php
// Group by with aggregates
$usersByRole = $userMapper
    ->select('role', DB::raw('COUNT(*) as count'))
    ->groupBy('role')
    ->get();

// Having clauses
$popularRoles = $userMapper
    ->select('role', DB::raw('COUNT(*) as count'))
    ->groupBy('role')
    ->having('count', '>', 10)
    ->get();

// Complex grouping
$monthlyStats = $userMapper
    ->select(
        DB::raw('YEAR(created_at) as year'),
        DB::raw('MONTH(created_at) as month'),
        DB::raw('COUNT(*) as total_users'),
        DB::raw('SUM(credits) as total_credits')
    )
    ->groupBy('year', 'month')
    ->orderBy('year', 'desc')
    ->orderBy('month', 'desc')
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

## Query Scopes

### Local Scopes

Define reusable query logic in your mapper:

```php
class UserMapper extends Mapper
{
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
    
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }
    
    public function scopeCreatedAfter($query, DateTime $date)
    {
        return $query->where('created_at', '>', $date->format('Y-m-d H:i:s'));
    }
    
    public function scopeWithRole($query, array $roles)
    {
        return $query->whereIn('role', $roles);
    }
    
    public function scopeHasEmail($query)
    {
        return $query->whereNotNull('email')
                    ->where('email', '!=', '');
    }
}

// Usage
$activeAdmins = $userMapper->active()->admins()->get();
$recentUsers = $userMapper->createdAfter(new DateTime('-30 days'))->get();
$staff = $userMapper->withRole(['admin', 'moderator'])->get();
$contactableUsers = $userMapper->hasEmail()->active()->get();
```

### Chaining Scopes

```php
// Complex scope combinations
$targetUsers = $userMapper
    ->active()
    ->hasEmail()
    ->createdAfter(new DateTime('-1 year'))
    ->withRole(['user', 'premium'])
    ->orderBy('last_login_at', 'desc')
    ->get();
```

### Dynamic Scopes

```php
class UserMapper extends Mapper
{
    public function scopeByStatus($query, string $status)
    {
        return match($status) {
            'active' => $query->where('active', true)->whereNull('banned_at'),
            'inactive' => $query->where('active', false),
            'banned' => $query->whereNotNull('banned_at'),
            'pending' => $query->whereNull('email_verified_at'),
            default => $query
        };
    }
    
    public function scopeSearch($query, string $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('name', 'LIKE', "%{$term}%")
              ->orWhere('email', 'LIKE', "%{$term}%")
              ->orWhere('username', 'LIKE', "%{$term}%");
        });
    }
}

// Usage
$users = $userMapper->byStatus('active')->search('john')->get();
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

## Raw Queries and Advanced Operations

### Raw Expressions

```php
// Raw select expressions
$users = $userMapper
    ->select('*', DB::raw('YEAR(created_at) as registration_year'))
    ->get();

// Raw where conditions
$users = $userMapper
    ->whereRaw('DATE(created_at) = CURDATE()')
    ->get();

// Raw having conditions
$users = $userMapper
    ->select('role', DB::raw('COUNT(*) as count'))
    ->groupBy('role')
    ->havingRaw('COUNT(*) > 5')
    ->get();

// Raw order by
$users = $userMapper
    ->orderByRaw('CASE WHEN role = "admin" THEN 1 ELSE 2 END')
    ->get();
```

### Subqueries

```php
// Subquery in where clause
$users = $userMapper
    ->where('id', '>', function($query) {
        $query->select(DB::raw('AVG(id)'))
              ->from('users')
              ->where('active', true);
    })
    ->get();

// Exists subqueries
$usersWithPosts = $userMapper
    ->whereExists(function($query) {
        $query->select(DB::raw(1))
              ->from('posts')
              ->whereColumn('posts.user_id', 'users.id');
    })
    ->get();
```

### Unions

```php
// Union queries
$admins = $userMapper->where('role', 'admin');
$moderators = $userMapper->where('role', 'moderator');

$staff = $admins->union($moderators)->get();

// Union all
$allStaff = $admins->unionAll($moderators)->get();
```

## Performance Optimization

### Select Specific Columns

```php
// Only select needed columns
$users = $userMapper
    ->select(['id', 'name', 'email'])
    ->where('active', true)
    ->get();

// Exclude large columns
$users = $userMapper
    ->select(['*'])
    ->addSelect(DB::raw('LEFT(bio, 100) as bio_preview'))
    ->get();
```

### Index Optimization

```php
// Use indexed columns in where clauses
$users = $userMapper
    ->where('email', $email)           // Indexed column
    ->where('active', true)            // Indexed column
    ->first();

// Avoid functions on indexed columns
// Bad: whereRaw('UPPER(email) = ?', [strtoupper($email)])
// Good: where('email', $email) // assuming email is stored normalized
```

### Query Hints and Optimization

```php
// Force index usage (MySQL)
$users = $userMapper
    ->from(DB::raw('users FORCE INDEX (idx_email_active)'))
    ->where('email', 'LIKE', '%@example.com')
    ->where('active', true)
    ->get();

// Explain query performance
DB::enableQueryLog();
$users = $userMapper->where('active', true)->get();
$queries = DB::getQueryLog();
// Analyze the generated SQL
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

### Relationship Existence Queries

```php
// Users who have posts
$usersWithPosts = $userMapper->has('posts')->get();

// Users who have published posts
$usersWithPublishedPosts = $userMapper
    ->whereHas('posts', function($query) {
        $query->where('published', true);
    })
    ->get();

// Users who don't have posts
$usersWithoutPosts = $userMapper->doesntHave('posts')->get();

// Count relationships
$usersWithManyPosts = $userMapper
    ->has('posts', '>=', 10)
    ->get();
```

## Error Handling and Debugging

### Query Debugging

```php
// Log all queries
DB::enableQueryLog();

$users = $userMapper
    ->where('active', true)
    ->with('posts')
    ->get();

$queries = DB::getQueryLog();
foreach ($queries as $query) {
    Log::info('Query: ' . $query['query']);
    Log::info('Bindings: ' . json_encode($query['bindings']));
    Log::info('Time: ' . $query['time'] . 'ms');
}

// Dump SQL for current query
$query = $userMapper->where('active', true);
dd($query->toSql(), $query->getBindings());
```

### Exception Handling

```php
try {
    $user = $userMapper->findOrFail(999);
} catch (ModelNotFoundException $e) {
    // Handle not found
    return response()->json(['error' => 'User not found'], 404);
}

try {
    $users = $userMapper
        ->where('invalid_column', 'value')
        ->get();
} catch (QueryException $e) {
    // Handle database errors
    Log::error('Database query failed: ' . $e->getMessage());
    return response()->json(['error' => 'Database error'], 500);
}
```

## Best Practices

### 1. Use Appropriate Methods

```php
// Good: Use exists() for existence checks
if ($userMapper->where('email', $email)->exists()) {
    // Email already taken
}

// Avoid: Loading full record just to check existence
if ($userMapper->where('email', $email)->first()) {
    // Inefficient - loads entire record
}
```

### 2. Optimize Relationship Loading

```php
// Good: Eager load relationships you'll use
$users = $userMapper->with(['posts', 'profile'])->get();
foreach ($users as $user) {
    echo $user->profile->bio;
    echo count($user->posts);
}

// Avoid: Forgetting to load needed relationships
$users = $userMapper->get();
foreach ($users as $user) {
    echo $user->profile->bio;   // NULL - relationship not loaded
    echo count($user->posts);   // 0 - relationship not loaded
}
```

### 3. Use Chunking for Large Datasets

```php
// Good: Process large datasets in chunks
$userMapper->chunk(1000, function($users) {
    foreach ($users as $user) {
        $this->processUser($user);
    }
});

// Avoid: Loading everything into memory
$users = $userMapper->all(); // Could cause memory issues
foreach ($users as $user) {
    $this->processUser($user);
}
```

### 4. Leverage Database Capabilities

```php
// Good: Use database aggregation
$averageCredits = $userMapper->where('active', true)->avg('credits');

// Avoid: PHP aggregation of large datasets
$users = $userMapper->where('active', true)->get();
$averageCredits = $users->avg('credits'); // Inefficient for large datasets
```

## Next Steps

- **[Persistence Operations](./persistence.md)** - Learn entity storage and lifecycle management
- **[Scopes](./scopes.md)** - Master global and local scopes
- **[Relationship Loading](../relationships/eager-loading.md)** - Optimize relationship queries
