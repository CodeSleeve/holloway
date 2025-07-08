# Soft Deletes
> **Note:** The timestamp for `deleted_at` is set using your mapper's `currentTime()` method. You can override this method to control how soft delete times are generated (e.g., for custom time zones or deterministic tests).

Holloway provides comprehensive soft delete functionality that allows you to "delete" entities without actually removing them from the database. This is essential for maintaining data integrity, audit trails, and providing "undo" functionality in applications.

## Table of Contents

- [Understanding Soft Deletes](#understanding-soft-deletes)
- [Enabling Soft Deletes](#enabling-soft-deletes)
- [Basic Operations](#basic-operations)
- [Querying Soft Deleted Entities](#querying-soft-deleted-entities)
- [Restoring Entities](#restoring-entities)
- [Permanent Deletion](#permanent-deletion)
- [Relationships and Soft Deletes](#relationships-and-soft-deletes)
- [Advanced Patterns](#advanced-patterns)
- [Best Practices](#best-practices)

## Understanding Soft Deletes

Soft deletes work by adding a timestamp to a `deleted_at` column instead of actually removing the record from the database. When querying, soft deleted records are automatically excluded unless explicitly requested.

### Database Schema

First, add a `deleted_at` column to your table:

```sql
ALTER TABLE posts ADD COLUMN deleted_at TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN deleted_at TIMESTAMP NULL;
```

### Benefits of Soft Deletes

- **Data Recovery**: Easily restore accidentally deleted data
- **Audit Trails**: Maintain complete history of all records
- **Referential Integrity**: Avoid foreign key constraint issues
- **Gradual Cleanup**: Mark for deletion and clean up later
- **User Experience**: Provide "undo" functionality

## Enabling Soft Deletes

### Entity Configuration

Add the `SoftDeletes` trait to your entities:

```php
<?php

use Holloway\SoftDeletes;

class Post
{
    use SoftDeletes;

    protected $id;
    protected $title;
    protected $content;
    protected $author_id;
    protected $deleted_at;

    // Getters and setters...
    public function getDeletedAt(): ?DateTimeInterface
    {
        return $this->deleted_at;
    }

    public function setDeletedAt(?DateTimeInterface $deletedAt): void
    {
        $this->deleted_at = $deletedAt;
    }
}
```

### Mapper Configuration

Configure your mapper to handle soft deletes:

```php
<?php

class PostMapper extends Mapper
{
    protected $table = 'posts';
    protected $entityClass = Post::class;

    protected function configure(): void
    {
        $this->useSoftDeletes();
        
        // Configure fields
        $this->field('id')->primary();
        $this->field('title');
        $this->field('content');
        $this->field('author_id');
        $this->field('deleted_at')->nullable();
    }
}
```

## Basic Operations

### Soft Deleting Entities

```php
$post = $postMapper->find(1);

// Soft delete the post
$postMapper->delete($post);

// The post still exists in the database but with deleted_at timestamp
// Regular queries will no longer return this post
```

### Checking if Entity is Soft Deleted

```php
$post = $postMapper->withTrashed()->find(1);

if ($post->isTrashed()) {
    echo "This post has been soft deleted";
}

if ($post->isNotTrashed()) {
    echo "This post is active";
}
```

### Bulk Soft Deletion

```php
// Soft delete multiple posts
$postMapper
    ->where('status', 'draft')
    ->where('created_at', '<', now()->subMonths(6))
    ->delete();

// Soft delete by IDs
$postMapper->whereIn('id', [1, 2, 3, 4])->delete();
```

## Querying Soft Deleted Entities

### Default Behavior

By default, all queries exclude soft deleted entities:

```php
// Only returns non-deleted posts
$posts = $postMapper->all();

// Only returns non-deleted posts matching criteria
$activePosts = $postMapper
    ->where('status', 'published')
    ->get();
```

### Including Soft Deleted Entities

```php
// Include soft deleted entities in results
$allPosts = $postMapper->withTrashed()->all();

// Filter to only soft deleted entities
$deletedPosts = $postMapper->onlyTrashed()->all();

// Mixed queries
$recentPosts = $postMapper
    ->withTrashed()
    ->where('created_at', '>', now()->subDays(30))
    ->get();
```

### Conditional Soft Delete Queries

```php
class PostMapper extends Mapper
{
    public function includeDeleted(bool $include = false)
    {
        return $include ? $this->withTrashed() : $this;
    }

    public function getArchivedPosts()
    {
        return $this->onlyTrashed()
            ->where('deleted_at', '<', now()->subDays(30))
            ->get();
    }
}

// Usage
$posts = $postMapper->includeDeleted($user->canViewDeleted())->get();
$archived = $postMapper->getArchivedPosts();
```

## Restoring Entities

### Basic Restoration

```php
$post = $postMapper->onlyTrashed()->find(1);

if ($post) {
    $postMapper->restore($post);
    // Post is now active again
}
```

### Bulk Restoration

```php
// Restore all soft deleted posts from a specific author
$postMapper
    ->onlyTrashed()
    ->where('author_id', $authorId)
    ->restore();

// Restore posts deleted in the last 24 hours
$postMapper
    ->onlyTrashed()
    ->where('deleted_at', '>', now()->subDay())
    ->restore();
```

### Conditional Restoration

```php
class PostMapper extends Mapper
{
    public function restoreIfRecent($id, int $hours = 24)
    {
        $post = $this->onlyTrashed()->find($id);
        
        if ($post && $post->getDeletedAt() > now()->subHours($hours)) {
            $this->restore($post);
            return true;
        }
        
        return false;
    }
}

// Usage
if ($postMapper->restoreIfRecent(1, 48)) {
    echo "Post restored successfully";
} else {
    echo "Post cannot be restored (too old or not found)";
}
```

## Permanent Deletion

### Force Delete

Permanently remove entities from the database:

```php
$post = $postMapper->withTrashed()->find(1);

// Permanently delete (cannot be undone)
$postMapper->forceDelete($post);
```

### Bulk Force Deletion

```php
// Permanently delete all posts soft deleted over 30 days ago
$postMapper
    ->onlyTrashed()
    ->where('deleted_at', '<', now()->subDays(30))
    ->forceDelete();
```

### Scheduled Cleanup

```php
class PostCleanupService
{
    public function cleanupOldPosts(int $daysOld = 30): int
    {
        return $this->postMapper
            ->onlyTrashed()
            ->where('deleted_at', '<', now()->subDays($daysOld))
            ->forceDelete();
    }
}

// Usage in a scheduled job
$cleaned = $cleanupService->cleanupOldPosts(90);
Log::info("Permanently deleted {$cleaned} old posts");
```

## Relationships and Soft Deletes

### Automatic Relationship Handling

```php
class PostMapper extends Mapper
{
    protected function configure(): void
    {
        $this->useSoftDeletes();
        
        // Comments relationship automatically respects soft deletes
        $this->hasMany('comments', CommentMapper::class, 'post_id');
        
        // Author relationship (assuming User also uses soft deletes)
        $this->belongsTo('author', UserMapper::class, 'author_id');
    }
}
```

### Querying Related Soft Deleted Entities

```php
// Get posts with their non-deleted comments
$posts = $postMapper->with('comments')->get();

// Get posts with all comments (including soft deleted)
$posts = $postMapper
    ->with(['comments' => function($query) {
        $query->withTrashed();
    }])
    ->get();

// Get only posts that have soft deleted comments
$posts = $postMapper
    ->whereHas('comments', function($query) {
        $query->onlyTrashed();
    })
    ->get();
```

### Cascading Soft Deletes

```php
class PostMapper extends Mapper
{
    public function deleteWithComments(Post $post): void
    {
        // Soft delete all comments first
        $this->commentMapper
            ->where('post_id', $post->getId())
            ->delete();
        
        // Then soft delete the post
        $this->delete($post);
    }
}
```

### Relationship Constraints with Soft Deletes

```php
class CommentMapper extends Mapper
{
    protected function configure(): void
    {
        $this->useSoftDeletes();
        
        // Only relate to non-deleted posts
        $this->belongsTo('post', PostMapper::class, 'post_id')
             ->where('deleted_at', null);
    }
}
```

## Advanced Patterns

### Soft Delete Events

```php
class PostMapper extends Mapper
{
    protected function beforeSoftDelete(Post $post): bool
    {
        // Perform actions before soft deleting
        event(new PostDeleting($post));
        
        // Return false to prevent deletion
        return $post->canBeDeleted();
    }
    
    protected function afterSoftDelete(Post $post): void
    {
        // Perform actions after soft deleting
        event(new PostDeleted($post));
        
        // Notify author
        $this->notificationService->notifyAuthor($post);
    }
    
    protected function afterRestore(Post $post): void
    {
        event(new PostRestored($post));
    }
}
```

### Version-Aware Soft Deletes

```php
class VersionedPostMapper extends Mapper
{
    public function deleteVersion(Post $post, string $reason = null): void
    {
        $post->setDeletionReason($reason);
        $post->setDeletedBy(auth()->id());
        
        $this->delete($post);
    }
    
    public function getDeletedByUser(int $userId)
    {
        return $this->onlyTrashed()
            ->where('deleted_by', $userId)
            ->get();
    }
}
```

### Soft Delete with Status Tracking

```php
class Post
{
    use SoftDeletes;
    
    const STATUS_ACTIVE = 'active';
    const STATUS_ARCHIVED = 'archived';
    const STATUS_DELETED = 'deleted';
    
    protected $status = self::STATUS_ACTIVE;
    
    public function archive(): void
    {
        $this->status = self::STATUS_ARCHIVED;
    }
    
    public function getEffectiveStatus(): string
    {
        if ($this->isTrashed()) {
            return self::STATUS_DELETED;
        }
        
        return $this->status;
    }
}
```

## Best Practices

### 1. Plan Your Soft Delete Strategy

```php
// Define clear policies
class DeletionPolicy
{
    const SOFT_DELETE_RETENTION_DAYS = 30;
    const AUTO_CLEANUP_DAYS = 90;
    
    public static function shouldSoftDelete(string $entityType): bool
    {
        return in_array($entityType, [
            'posts', 'comments', 'users', 'orders'
        ]);
    }
}
```

### 2. Provide User Feedback

```php
class PostController
{
    public function destroy(int $id)
    {
        $post = $this->postMapper->find($id);
        
        if (!$post) {
            return response()->json(['error' => 'Post not found'], 404);
        }
        
        $this->postMapper->delete($post);
        
        return response()->json([
            'message' => 'Post deleted successfully',
            'undo_url' => route('posts.restore', $id),
            'undo_expires' => now()->addHours(24)->toISOString()
        ]);
    }
    
    public function restore(int $id)
    {
        $post = $this->postMapper->onlyTrashed()->find($id);
        
        if (!$post) {
            return response()->json(['error' => 'Post not found'], 404);
        }
        
        $this->postMapper->restore($post);
        
        return response()->json(['message' => 'Post restored successfully']);
    }
}
```

### 3. Monitor Soft Delete Usage

```php
class SoftDeleteMetrics
{
    public function getDeletionStats(): array
    {
        return [
            'active_posts' => $this->postMapper->count(),
            'deleted_posts' => $this->postMapper->onlyTrashed()->count(),
            'recently_deleted' => $this->postMapper
                ->onlyTrashed()
                ->where('deleted_at', '>', now()->subDays(7))
                ->count(),
        ];
    }
}
```

### 4. Handle Performance Implications

```php
// Add indexes for soft delete queries
CREATE INDEX idx_posts_deleted_at ON posts(deleted_at);
CREATE INDEX idx_posts_active ON posts(deleted_at, created_at) WHERE deleted_at IS NULL;
```

### 5. Document Soft Delete Behavior

```php
/**
 * Post entity with soft delete capability
 * 
 * Soft deleted posts:
 * - Are excluded from normal queries
 * - Can be restored within 30 days
 * - Are permanently deleted after 90 days
 * - Cascade deletion to comments
 */
class Post
{
    use SoftDeletes;
    
    // Implementation...
}
```

Soft deletes provide a powerful way to handle data deletion while maintaining flexibility and safety. By following these patterns and best practices, you can implement robust soft delete functionality that enhances your application's data integrity and user experience.
