# Soft Deletes

> **Note:** The timestamp for `deleted_at` is set using your mapper's `currentTime()` method. You can override this method to control how soft delete times are generated (e.g., for custom time zones or deterministic tests).

Holloway provides soft delete functionality that allows you to "delete" entities without removing them from the database. Soft-deleted records are automatically excluded from queries unless you explicitly opt in.

## Table of Contents

- [Understanding Soft Deletes](#understanding-soft-deletes)
- [Enabling Soft Deletes](#enabling-soft-deletes)
- [Basic Operations](#basic-operations)
- [Querying Soft Deleted Entities](#querying-soft-deleted-entities)
- [Restoring Entities](#restoring-entities)
- [Permanent Deletion](#permanent-deletion)
- [Relationships and Soft Deletes](#relationships-and-soft-deletes)
- [Soft Delete Events](#soft-delete-events)
- [Best Practices](#best-practices)

## Understanding Soft Deletes

Soft deletes work by setting a timestamp on a `deleted_at` column instead of removing the row. All subsequent queries from that mapper automatically exclude rows where `deleted_at` is not null.

### Database Schema

Add a nullable `deleted_at` column to your table:

```sql
ALTER TABLE posts ADD COLUMN deleted_at TIMESTAMP NULL;
```

### Benefits

- **Data recovery**: Restore accidentally deleted records
- **Audit trails**: Keep a complete history of all rows
- **Referential integrity**: Avoid cascading foreign key issues
- **User experience**: Provide "undo delete" functionality

## Enabling Soft Deletes

Add the `SoftDeletes` trait to your **mapper class** (not the entity), then register `SoftDeletingScope` as a global scope in the constructor:

```php
<?php

use CodeSleeve\Holloway\Mapper;
use CodeSleeve\Holloway\SoftDeletes;
use CodeSleeve\Holloway\SoftDeletingScope;

class PostMapper extends Mapper
{
    use SoftDeletes;

    protected string $entityClassName = Post::class;
    protected string $table = 'posts';

    public function __construct()
    {
        parent::__construct();

        static::addGlobalScope(new SoftDeletingScope());
    }

    // ...
}
```

The `SoftDeletes` trait provides the `$isSoftDeleting` flag and helper methods used internally. `SoftDeletingScope` must be explicitly registered — it is responsible for:

1. Automatically adding `WHERE deleted_at IS NULL` to every query
2. Adding `withTrashed()`, `onlyTrashed()`, and `withoutTrashed()` macros to the query builder

### Custom Column Name

To use a column name other than `deleted_at`, define a constant on your mapper:

```php
class PostMapper extends Mapper
{
    use SoftDeletes;

    const DELETED_AT = 'archived_at';
}
```

### Entity Side

The `SoftDeletes` trait lives on the mapper. Your entity class just needs a property for the timestamp; add an `isTrashed()` helper if you need it:

```php
class Post
{
    protected ?string $deleted_at = null;

    public function isTrashed(): bool
    {
        return $this->deleted_at !== null;
    }
}
```

## Basic Operations

### Soft Deleting an Entity

Call `remove()` on the mapper. When `$isSoftDeleting` is true (set by the `SoftDeletes` trait), `removeEntity()` updates `deleted_at` directly rather than issuing a `DELETE`.

```php
$post = $postMapper->find(1);

$postMapper->remove($post);

// The row still exists in the database with deleted_at set.
// Normal queries from this mapper will no longer return it.
```

### Bulk Soft Deletion

> **Note:** Bulk soft deletion via the builder (`->delete()`) is not currently reliable — an internal method name mismatch in `SoftDeletingScope` will cause a runtime error. Use mapper-level `remove()` in a loop for now:

```php
$posts = $postMapper
    ->where('status', 'draft')
    ->where('created_at', '<', now()->subMonths(6))
    ->get();

foreach ($posts as $post) {
    $postMapper->remove($post);
}
```

## Querying Soft Deleted Entities

### Default Behavior

All queries from a soft-deleting mapper exclude soft-deleted records automatically:

```php
$posts = $postMapper->all();       // only non-deleted posts
$posts = $postMapper->where('status', 'published')->get(); // same
```

### Including Soft Deleted Records

```php
// Include soft-deleted records alongside active ones
$allPosts = $postMapper->withTrashed()->get();

// Only soft-deleted records
$deletedPosts = $postMapper->onlyTrashed()->get();

// Explicitly exclude soft-deleted records (redundant, but available)
$activePosts = $postMapper->withoutTrashed()->get();
```

These macros can be combined with any other query builder methods:

```php
$recentlyDeleted = $postMapper
    ->onlyTrashed()
    ->where('deleted_at', '>', now()->subDays(30))
    ->orderBy('deleted_at', 'desc')
    ->get();
```

### Conditional Inclusion

```php
class PostMapper extends Mapper
{
    use SoftDeletes;

    public function forAdmin(): Builder
    {
        return $this->withTrashed();
    }

    public function archivedOlderThan(int $days): Collection
    {
        return $this->onlyTrashed()
            ->where('deleted_at', '<', now()->subDays($days))
            ->get();
    }
}
```

## Restoring Entities

### Single Entity

```php
$post = $postMapper->onlyTrashed()->find(1);

if ($post) {
    $postMapper->restore($post);
}
```

### Multiple Entities

Pass an iterable:

```php
$posts = $postMapper->onlyTrashed()
    ->where('author_id', $authorId)
    ->get();

$postMapper->restore($posts);
```

### Via the Query Builder

> **Note:** The builder `restore()` macro is not currently reliable due to the same internal method name mismatch as bulk deletion. Use mapper-level `restore()` instead (shown above).

## Permanent Deletion

Use `forceRemove()` to hard-delete an entity regardless of soft delete status:

```php
$post = $postMapper->withTrashed()->find(1);

$postMapper->forceRemove($post);
// Row is now gone from the database permanently
```

### Scheduled Cleanup

```php
class PostCleanupService
{
    public function purgeOldDeletions(int $daysOld = 90): void
    {
        $posts = $this->postMapper
            ->onlyTrashed()
            ->where('deleted_at', '<', now()->subDays($daysOld))
            ->get();

        foreach ($posts as $post) {
            $this->postMapper->forceRemove($post);
        }
    }
}
```

## Relationships and Soft Deletes

When a related mapper also uses `SoftDeletes` and has `SoftDeletingScope` registered, the scope applies automatically to its queries. Eager-loaded relationships respect their own mapper's global scopes.

```php
// Loads posts with only non-deleted comments (CommentMapper uses SoftDeletes)
$posts = $postMapper->with('comments')->get();

// Include soft-deleted comments explicitly
$posts = $postMapper
    ->with(['comments' => function($query) {
        $query->withTrashed();
    }])
    ->get();
```

### Cascading Soft Deletes

Holloway doesn't cascade soft deletes automatically. Handle this in your service layer:

```php
class PostService
{
    public function deletePost(Post $post): void
    {
        // Soft delete comments first (loop since bulk builder delete is not currently reliable)
        $comments = $this->commentMapper
            ->where('post_id', $post->getId())
            ->get();

        foreach ($comments as $comment) {
            $this->commentMapper->remove($comment);
        }

        // Then soft delete the post
        $this->postMapper->remove($post);
    }
}
```

## Soft Delete Events

The mapper fires standard persistence events around soft delete and restore operations. Use `registerPersistenceEvent()` to hook into them.

The `removing` event fires before a soft delete (just like a hard delete). Return `false` to cancel it:

```php
public function __construct()
{
    parent::__construct();

    $this->registerPersistenceEvent('removing', function(Post $post) {
        if ($post->hasActiveOrders()) {
            return false; // Cancels the soft delete
        }
    });

    $this->registerPersistenceEvent('removed', function(Post $post) {
        Cache::forget("post:{$post->getId()}");
        dispatch(new NotifyAuthorOfDeletion($post->getId()));
    });

    $this->registerPersistenceEvent('restoring', function(Post $post) {
        if (!$post->isEligibleForRestore()) {
            return false;
        }
    });

    $this->registerPersistenceEvent('restored', function(Post $post) {
        Cache::forget("post:{$post->getId()}");
    });
}
```

See [Events](./events.md) for full documentation on the event system.

## Best Practices

### Add a database index

```sql
-- Speeds up the automatic WHERE deleted_at IS NULL filter
CREATE INDEX idx_posts_deleted_at ON posts(deleted_at);
```

### Expose a scoped method for admin queries

```php
public function includingDeleted(): Builder
{
    return $this->withTrashed();
}
```

### Define a clear retention policy

```php
class PostMapper extends Mapper
{
    use SoftDeletes;

    const SOFT_DELETE_RETENTION_DAYS = 30;
    const HARD_DELETE_AFTER_DAYS = 90;
}
```

### Provide restore endpoints when soft deletes are user-visible

```php
class PostController
{
    public function destroy(int $id): JsonResponse
    {
        $post = $this->postMapper->findOrFail($id);
        $this->postMapper->remove($post);

        return response()->json([
            'message' => 'Post deleted.',
            'undo_url' => route('posts.restore', $id),
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $post = $this->postMapper->onlyTrashed()->find($id);

        if (!$post) {
            return response()->json(['error' => 'Not found.'], 404);
        }

        $this->postMapper->restore($post);

        return response()->json(['message' => 'Post restored.']);
    }
}
```
