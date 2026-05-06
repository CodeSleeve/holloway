# Relationship Overview

Holloway's relationship system provides powerful and flexible ways to define connections between your entities. Unlike Active Record patterns, relationships in Holloway are explicitly defined in mappers and loaded through an optimized query system.

## Table of Contents

- [Relationship Types](#relationship-types)
- [How Relationships Work](#how-relationships-work)
- [Relationship Loading Strategy](#relationship-loading-strategy)
- [Relationship Tree System](#relationship-tree-system)
- [Relationship Configuration](#relationship-configuration)
- [Relationship Constraints](#relationship-constraints)
- [Performance Characteristics](#performance-characteristics)
- [Advanced Relationship Features](#advanced-relationship-features)
- [Best Practices](#best-practices)
- [Debugging Relationships](#debugging-relationships)
- [Next Steps](#next-steps)

## Relationship Types

Holloway supports all standard relationship types plus custom relationships for complex scenarios:

### Standard Relationships

| Type | Description | Use Case |
|------|-------------|----------|
| **HasOne** | One-to-one relationship | User has one profile |
| **HasMany** | One-to-many relationship | User has many posts |
| **BelongsTo** | Inverse one-to-many | Post belongs to user |
| **BelongsToMany** | Many-to-many with pivot | User belongs to many roles |

### Custom Relationships

| Type | Description | Use Case |
|------|-------------|----------|
| **Custom** | Flexible custom logic | Complex queries, aggregations |
| **CustomMany** | Custom returning collection | Related data with special logic |
| **CustomOne** | Custom returning single entity | Computed relationships |

## How Relationships Work

### Definition Phase

Relationships are defined in the mapper's `defineRelations()` method:

```php
class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        $this->hasOne('profile', UserProfile::class);
        $this->hasMany('posts', Post::class);
        $this->belongsTo('company', Company::class);
        $this->belongsToMany('roles', Role::class);
    }
}
```

### Loading Phase

Relationships are loaded using the `with()` method:

```php
// Single relationship
$user = $userMapper->with('posts')->find(1);

// Multiple relationships
$user = $userMapper->with(['posts', 'profile', 'roles'])->find(1);

// Nested relationships
$user = $userMapper->with('posts.comments.author')->find(1);
```

### Access Phase

Loaded relationships are accessible as properties on your entities:

```php
// Access loaded relationships
foreach ($user->posts as $post) {
    echo $post->title;
    
    foreach ($post->comments as $comment) {
        echo $comment->author->name;
    }
}
```

## Relationship Loading Strategy

### Eager Loading (Recommended)

Load relationships upfront with optimized queries:

```php
// Single query for users, single query for posts
$users = $userMapper->with('posts')->get();

// N+1 problem solved
foreach ($users as $user) {
    foreach ($user->posts as $post) { // No additional queries
        echo $post->title;
    }
}
```

### Lazy Loading (Not Supported)

Unlike Active Record patterns, Holloway doesn't support lazy loading. This is intentional:

- **Prevents N+1 queries** by making relationship loading explicit
- **Improves performance** through batched loading
- **Enhances predictability** - you know exactly when queries execute

## Relationship Tree System

Holloway uses a sophisticated tree system to optimize relationship loading:

### Tree Building

```php
// This relationship string:
'posts.comments.author'

// Becomes this tree structure:
[
    'posts' => [
        'name' => 'posts',
        'constraints' => function() {},
        'relationship' => PostRelationship,
        'children' => [
            'comments' => [
                'name' => 'comments',
                'constraints' => function() {},
                'relationship' => CommentRelationship,
                'children' => [
                    'author' => [
                        'name' => 'author',
                        'constraints' => function() {},
                        'relationship' => AuthorRelationship,
                        'children' => []
                    ]
                ]
            ]
        ]
    ]
]
```

### Optimized Loading Process

1. **Tree Traversal** - Load data for each level of the tree
2. **Batch Queries** - Single query per relationship level
3. **Entity Creation** - Convert loaded data to entities
4. **Attachment** - Attach related entities to parents

```php
// For 'posts.comments.author' on 10 users:
// Query 1: Load 10 users
// Query 2: Load all posts for these users
// Query 3: Load all comments for these posts  
// Query 4: Load all authors for these comments
// Total: 4 queries regardless of data volume
```

## Relationship Configuration

### Basic Configuration

All relationships accept optional parameters for customization:

```php
public function defineRelations(): void
{
    // Basic: Uses conventions
    $this->hasMany('posts', Post::class);
    
    // Custom keys: Specify foreign and local keys
    $this->hasMany('posts', Post::class, 'author_id', 'id');
    
    // Pivot table: For many-to-many relationships
    $this->belongsToMany('roles', Role::class, 'user_roles', 'user_id', 'role_id');
}
```

### Naming Conventions

Holloway follows Laravel-style conventions but allows full customization:

```php
// HasMany: 
// Foreign key: {singular_table}_id (user_id)
// Local key: {primary_key} (id)

// BelongsTo:
// Foreign key: {singular_related_table}_id (company_id)  
// Local key: {primary_key} (id)

// BelongsToMany:
// Pivot table: {table1}_{table2} (alphabetical order)
// Local pivot key: {singular_local_table}_id
// Foreign pivot key: {singular_foreign_table}_id
```

## Relationship Constraints

Apply constraints to relationship queries:

```php
// Load only published posts
$user = $userMapper->with([
    'posts' => function($query) {
        $query->where('status', 'published')
              ->orderBy('created_at', 'desc');
    }
])->find(1);

// Load comments with their authors
$user = $userMapper->with([
    'posts.comments' => function($query) {
        $query->where('approved', true);
    },
    'posts.comments.author'
])->find(1);
```

## Performance Characteristics

### Query Efficiency

```php
// Without relationships (1 query)
$users = $userMapper->get(); // SELECT * FROM users

// With relationships (4 queries total)
$users = $userMapper->with('posts.comments.author')->get();
// Query 1: SELECT * FROM users
// Query 2: SELECT * FROM posts WHERE user_id IN (...)  
// Query 3: SELECT * FROM comments WHERE post_id IN (...)
// Query 4: SELECT * FROM users WHERE id IN (...) // authors
```

### Memory Optimization

- **Entity Caching** - Each database record creates only one entity instance
- **Batch Loading** - All relationships loaded in single queries per level
- **Selective Loading** - Only requested relationships are loaded

### Caching Integration

```php
class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        $this->hasMany('posts', Post::class);
    }
    
    // Entities are cached automatically
    public function findWithPosts($id)
    {
        $user = $this->with('posts')->find($id);
        
        // Second call uses cached entities
        $sameUser = $this->with('posts')->find($id);
        
        // $user and $sameUser are the same instances
        return $user;
    }
}
```

## Advanced Relationship Features

### Global Scopes in Relationships

Global scopes are automatically applied to relationship queries:

```php
class PostMapper extends Mapper
{
    public function __construct()
    {
        parent::__construct();
        
        // Global scope applied to all post queries
        static::addGlobalScope('published', function($builder) {
            $builder->where('status', 'published');
        });
    }
}

// Only published posts are loaded
$user = $userMapper->with('posts')->find(1);

// Override global scope for specific relationship
$user = $userMapper->with([
    'posts' => function($query) {
        $query->withoutGlobalScope('published');
    }
])->find(1);
```

### Soft Deletes in Relationships

When a related mapper uses `SoftDeletes` and has `SoftDeletingScope` registered, soft-deleted entities are automatically excluded from relationship queries:

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

// Soft deleted posts are excluded
$user = $userMapper->with('posts')->find(1);

// Include soft deleted posts
$user = $userMapper->with([
    'posts' => function($query) {
        $query->withTrashed();
    }
])->find(1);
```

## Best Practices

### 1. Define Relationships Explicitly

```php
// Good: Explicit relationship definition
public function defineRelations(): void
{
    $this->hasMany('posts', Post::class, 'author_id', 'id');
    $this->hasOne('profile', UserProfile::class, 'user_id', 'id');
}

// Avoid: Relying only on conventions (less clear)
public function defineRelations(): void
{
    $this->hasMany('posts', Post::class);
    $this->hasOne('profile', UserProfile::class);
}
```

### 2. Use Specific Relationship Loading

```php
// Good: Load only needed relationships
$user = $userMapper->with(['posts', 'profile'])->find(1);

// Avoid: Loading unnecessary relationships
$user = $userMapper->with(['posts', 'profile', 'roles', 'permissions'])->find(1);
```

### 3. Apply Constraints at Database Level

```php
// Good: Filter at database level
$user = $userMapper->with([
    'posts' => function($query) {
        $query->where('status', 'published')
              ->where('created_at', '>=', now()->subDays(30));
    }
])->find(1);

// Avoid: Filtering in PHP after loading all data
$user = $userMapper->with('posts')->find(1);
$recentPosts = $user->posts->filter(function($post) {
    return $post->status === 'published' && 
           $post->created_at >= now()->subDays(30);
});
```

### 4. Handle Missing Relationships Gracefully

```php
// In your entity hydration
public function hydrate(stdClass $record, Collection $relations)
{
    $entity = new User($record->name, $record->email);
    
    // Handle potentially missing relationships
    if ($relations && isset($relations['profile'])) {
        $entity->setProfile($relations['profile']);
    }
    
    if ($relations && isset($relations['posts'])) {
        $entity->setPosts($relations['posts']);
    } else {
        // Don't set posts if not loaded to avoid confusion
        // Entity should indicate whether posts were loaded
    }
    
    return $entity;
}
```

## Debugging Relationships

### Relationship Tree Inspection

```php
$query = $userMapper->with('posts.comments.author');
$tree = $query->getTree();

// Inspect the relationship tree structure
var_dump($tree->getLoads());
```

### Query Logging

```php
// Enable query logging
DB::enableQueryLog();

$users = $userMapper->with('posts.comments')->get();

// View executed queries
$queries = DB::getQueryLog();
foreach ($queries as $query) {
    echo $query['query'] . "\n";
}
```

## Next Steps

- **[Standard Relationships](./standard.md)** - Learn HasOne, HasMany, BelongsTo, BelongsToMany
- **[Custom Relationships](./custom.md)** - Create flexible custom relationship logic
- **[Eager Loading](./eager-loading.md)** - Master efficient relationship loading
- **[Nested Relationships](./nested.md)** - Work with complex nested relationship structures
