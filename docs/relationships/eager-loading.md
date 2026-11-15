# Eager Loading

Eager loading loads related entities up front, one query per relationship level, which avoids the N+1 query problem.

> **Note:** Holloway does not support lazy loading. Relationships must be requested up front with `with()`; accessing an unloaded relationship does not trigger a query.

## Table of Contents

- [Understanding the N+1 Problem](#understanding-the-n1-problem)
- [Basic Eager Loading](#basic-eager-loading)
- [Nested Eager Loading](#nested-eager-loading)
- [Conditional Eager Loading](#conditional-eager-loading)
- [Custom Eager Loading](#custom-eager-loading)

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

A constraint closure can add where clauses, ordering and scope changes. It can't change what is loaded, so `with()`, `without()` and `withoutDefaultLoads()` throw inside one. Put nested loads on the outer query instead (see [Nested Constraints](#nested-constraints)). See [Query Builder API](../references/query-builder-api.md) for everything a constraint can and can't do.

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

Give each level its own entry, using dot notation for the nested ones. The constraints of an entry apply to the last relationship in its name:

```php
$posts = $postMapper
    ->with([
        'comments' => function($query) {
            $query->where('status', 'approved');
        },
        'comments.author' => function($query) {
            $query->where('is_verified', true);
        },
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
                  ->orderBy('likes_count', 'desc');
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
