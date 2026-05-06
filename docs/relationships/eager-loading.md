# Eager Loading

Eager loading is a crucial performance optimization technique in Holloway that allows you to load related entities in a single query, avoiding the N+1 query problem that can severely impact application performance.

## Table of Contents

- [Understanding the N+1 Problem](#understanding-the-n1-problem)
- [Basic Eager Loading](#basic-eager-loading)
- [Nested Eager Loading](#nested-eager-loading)
- [Conditional Eager Loading](#conditional-eager-loading)
- [Custom Eager Loading](#custom-eager-loading)
- [Counting Relationships](#counting-relationships)
- [Performance Considerations](#performance-considerations)
- [Best Practices](#best-practices)

## Understanding the N+1 Problem

The N+1 query problem occurs when you load a collection of entities and then access a relationship on each entity individually:

```php
// This creates the N+1 problem
$posts = $postMapper->all();
foreach ($posts as $post) {
    echo $post->author->name; // Each iteration triggers a separate query
}
// Result: 1 query for posts + N queries for authors = N+1 queries
```

Without eager loading, this would execute:
1. One query to fetch all posts
2. One additional query for each post to fetch its author

## Basic Eager Loading

Use the `with()` method to eager load relationships:

```php
// Load posts with their authors in a single optimized query set
$posts = $postMapper->with('author')->all();

foreach ($posts as $post) {
    echo $post->author->name; // No additional queries!
}
```

### Multiple Relationships

Load multiple relationships simultaneously:

```php
$posts = $postMapper
    ->with(['author', 'category', 'tags'])
    ->all();

foreach ($posts as $post) {
    echo $post->author->name;
    echo $post->category->title;
    foreach ($post->tags as $tag) {
        echo $tag->name;
    }
}
```

### Relationship-Specific Constraints

Apply constraints to eager loaded relationships:

```php
// Only load published posts with their active comments
$posts = $postMapper
    ->with(['comments' => function($query) {
        $query->where('status', 'approved')
              ->orderBy('created_at', 'desc');
    }])
    ->where('status', 'published')
    ->get();
```

## Nested Eager Loading

Load relationships of relationships using dot notation:

```php
// Load posts with authors and their profiles
$posts = $postMapper
    ->with('author.profile')
    ->all();

foreach ($posts as $post) {
    echo $post->author->profile->bio;
}
```

### Complex Nested Loading

```php
// Load posts with multiple nested relationships
$posts = $postMapper
    ->with([
        'author.profile',
        'author.company',
        'category.parent',
        'comments.author',
        'tags.category'
    ])
    ->all();
```

### Nested Constraints

Apply constraints at different nesting levels:

```php
$posts = $postMapper
    ->with([
        'comments' => function($query) {
            $query->where('status', 'approved')
                  ->with(['author' => function($authorQuery) {
                      $authorQuery->where('is_verified', true);
                  }]);
        }
    ])
    ->get();
```

## Conditional Eager Loading

Use `when()` to conditionally eager load based on runtime conditions:

```php
$includeComments = request('include_comments', false);

$posts = $postMapper
    ->when($includeComments, function($query) {
        $query->with('comments.author');
    })
    ->all();
```

### User Permission-Based Loading

```php
$posts = $postMapper
    ->with('author')
    ->when($user->canViewPrivateData(), function($query) {
        $query->with(['author.email', 'author.phone']);
    })
    ->all();
```

## Custom Eager Loading

Define custom eager loading logic in your mappers:

```php
class PostMapper extends Mapper
{
    public function withPopularComments()
    {
        return $this->with(['comments' => function($query) {
            $query->where('likes_count', '>', 10)
                  ->orderBy('likes_count', 'desc')
                  ->limit(5);
        }]);
    }

    public function withRecentActivity()
    {
        return $this->with([
            'comments' => function($query) {
                $query->where('created_at', '>', now()->subDays(7));
            },
            'likes' => function($query) {
                $query->where('created_at', '>', now()->subDays(7));
            }
        ]);
    }
}

// Usage
$posts = $postMapper->withPopularComments()->get();
$activePosts = $postMapper->withRecentActivity()->get();
```

## Counting Relationships

The `withCount()` method allows you to count related records without loading them, which is significantly more efficient when you only need counts rather than the full data.

### Basic Counting

```php
// Count a single relationship
$users = $userMapper->withCount('posts')->get();

foreach ($users as $user) {
    echo "{$user->name} has {$user->posts_count} posts";
}
```

The count is added as an attribute on the entity using the relationship name in snake_case with a `_count` suffix.

### Multiple Counts

Count multiple relationships in a single query:

```php
$users = $userMapper->withCount(['posts', 'comments', 'likes'])->get();

foreach ($users as $user) {
    echo "Posts: {$user->posts_count}, ";
    echo "Comments: {$user->comments_count}, ";
    echo "Likes: {$user->likes_count}";
}
```

### Counting with Constraints

Apply conditions to count queries:

```php
// Count only published posts
$users = $userMapper->withCount([
    'posts' => function($query) {
        $query->where('published', true);
    }
])->get();

echo $users->first()->posts_count; // Only counts published posts
```

### Multiple Counts with Different Constraints

Use aliasing to count the same relationship with different conditions:

```php
$users = $userMapper->withCount([
    'posts',
    'posts as published_posts' => function($query) {
        $query->where('published', true);
    },
    'posts as draft_posts' => function($query) {
        $query->where('published', false);
    }
])->get();

foreach ($users as $user) {
    echo "Total: {$user->posts_count}, ";
    echo "Published: {$user->published_posts}, ";
    echo "Drafts: {$user->draft_posts}";
}
```

### Custom Count Aliases

Customize the count column name using the `as` syntax:

```php
$posts = $postMapper->withCount('comments as total_comments')->get();

echo $posts->first()->total_comments; // Uses custom alias
```

### Counting All Relationship Types

`withCount()` works with all standard relationship types:

```php
// HasMany relationships
$users = $userMapper->withCount('posts')->get();
echo $users->first()->posts_count;

// HasOne relationships
$users = $userMapper->withCount('profile')->get();
echo $users->first()->profile_count; // 1 or 0

// BelongsTo relationships
$posts = $postMapper->withCount('author')->get();
echo $posts->first()->author_count; // Always 1 if exists

// BelongsToMany relationships
$users = $userMapper->withCount('roles')->get();
echo $users->first()->roles_count;
```

### Combining Counts with Eager Loading

You can combine `withCount()` with `with()` to both count and load relationships:

```php
$users = $userMapper
    ->withCount('posts')
    ->with(['posts' => function($query) {
        $query->latest()->limit(5);
    }])
    ->get();

foreach ($users as $user) {
    echo "Total posts: {$user->posts_count}";
    echo "Latest 5 posts:";
    foreach ($user->posts as $post) {
        echo $post->title;
    }
}
```

The count includes all posts, but only the 5 latest are actually loaded.

### Scopes and Global Scopes

Global scopes (like `SoftDeletes`) are automatically applied to count queries:

```php
// Soft deleted comments are excluded from count automatically
$posts = $postMapper->withCount('comments')->get();
echo $posts->first()->comments_count; // Only non-deleted comments
```

### Custom Relationship Counts

Custom relationships can support `withCount()` by providing a count closure:

```php
class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        $this->custom(
            'activeOrders',
            function($query, Collection $users) {
                return $query->from('orders')
                    ->where('status', 'active')
                    ->whereIn('user_id', $users->pluck('id'))
                    ->get();
            },
            fn(stdClass $user, stdClass $order) => $user->id === $order->user_id,
            Order::class,
            false,
            function($query, string $parentTable, string $parentKey) {
                return $query
                    ->selectRaw('count(*)')
                    ->from('orders')
                    ->where('status', 'active')
                    ->whereColumn('user_id', '=', "$parentTable.$parentKey");
            }
        );
    }
}

// Now you can count the custom relationship
$users = $userMapper->withCount('activeOrders')->get();
echo $users->first()->active_orders_count;
```

### Performance Benefits

Using `withCount()` is significantly more efficient than loading relationships just to count them:

```php
// Efficient: Only counts, doesn't load data
$users = $userMapper->withCount('posts')->get();
echo $users->first()->posts_count;

// Inefficient: Loads all posts just to count them
$users = $userMapper->with('posts')->get();
echo count($users->first()->posts); // Loaded unnecessary data
```

### Limitations

- **Nested counts not supported**: You cannot use dot notation with `withCount()` (e.g., `withCount('posts.comments')` is not supported)
- **Custom relationships require count closure**: Custom relationships must provide a count closure to support `withCount()`

```php
// Not supported
$users = $userMapper->withCount('posts.comments')->get(); // Won't work

// Instead, count at each level
$users = $userMapper
    ->withCount('posts')
    ->with(['posts' => function($query) {
        $query->withCount('comments');
    }])
    ->get();

foreach ($users as $user) {
    echo "User posts: {$user->posts_count}";
    foreach ($user->posts as $post) {
        echo "Post comments: {$post->comments_count}";
    }
}
```

## Performance Considerations

### Selectivity in Eager Loading

Only load what you need:

```php
// Good: Specific fields
$posts = $postMapper
    ->with(['author' => function($query) {
        $query->select(['id', 'name', 'email']);
    }])
    ->get();

// Avoid: Loading all fields when you only need a few
$posts = $postMapper->with('author')->get();
```

### Limiting Relationships

Use limits to prevent loading too much data:

```php
$posts = $postMapper
    ->with(['comments' => function($query) {
        $query->latest()->limit(5);
    }])
    ->get();
```

### Memory Management

For large datasets, consider using chunks with eager loading:

```php
$postMapper
    ->with(['author', 'category'])
    ->chunk(100, function($posts) {
        foreach ($posts as $post) {
            // Process each post with its loaded relationships
            processPost($post);
        }
    });
```

## Best Practices

### 1. Profile Your Queries

Always monitor query counts and execution time:

```php
// Enable query logging in development
DB::enableQueryLog();

$posts = $postMapper->with('author.profile')->get();

// Check executed queries
$queries = DB::getQueryLog();
echo "Executed " . count($queries) . " queries";
```

### 2. Use Eager Loading Strategically

```php
// Good: Load relationships you know you'll use
$posts = $postMapper
    ->with(['author', 'category'])
    ->paginate(20);

// Avoid: Loading relationships you might not use
$posts = $postMapper
    ->with(['author', 'category', 'comments', 'tags', 'likes'])
    ->paginate(20);
```

### 3. Optimize Relationship Queries

```php
// Define efficient relationship loading in your mappers
class PostMapper extends Mapper
{
    public function withEssentials()
    {
        return $this->with([
            'author:id,name,avatar',
            'category:id,name,slug'
        ]);
    }

    public function withEngagement()
    {
        return $this->withCount(['comments', 'likes', 'shares']);
    }
}
```

### 4. Use Indexes for Eager Loading

Ensure your database has proper indexes for relationship queries:

```sql
-- For HasMany relationships
CREATE INDEX idx_posts_author_id ON posts(author_id);

-- For BelongsToMany relationships
CREATE INDEX idx_post_tags_post_id ON post_tags(post_id);
CREATE INDEX idx_post_tags_tag_id ON post_tags(tag_id);
```

## Debugging Eager Loading

### Query Analysis

```php
// Log queries to understand what's being executed
DB::listen(function($query) {
    Log::info($query->sql, $query->bindings);
});

$posts = $postMapper->with('author')->get();
```

### Memory Usage Monitoring

```php
$memoryBefore = memory_get_usage();

$posts = $postMapper->with(['author', 'comments'])->get();

$memoryAfter = memory_get_usage();
$memoryUsed = $memoryAfter - $memoryBefore;

echo "Memory used: " . number_format($memoryUsed / 1024 / 1024, 2) . " MB";
```

Eager loading is essential for building performant applications with Holloway. By understanding and implementing these patterns, you can significantly reduce database queries and improve your application's response times.

---

> **Note:**
>
> Holloway does not currently support automatic lazy loading of relationships via proxies or magic properties. All relationships must be explicitly specified using `with()` or similar methods when querying. Attempting to access an unloaded relationship property will not trigger an automatic database query. This design ensures predictable performance and avoids accidental N+1 query issues, but requires you to plan your data loading strategy up front.
