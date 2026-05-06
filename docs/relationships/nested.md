# Nested Relationships

Holloway provides powerful support for handling complex nested relationships, allowing you to efficiently work with deeply related data structures while maintaining clean architecture and optimal performance.

## Table of Contents

- [Understanding Nested Relationships](#understanding-nested-relationships)
- [Deep Loading with Constraints](#deep-loading-with-constraints)
- [Conditional Nested Loading](#conditional-nested-loading)
- [Polymorphic Nested Relationships](#polymorphic-nested-relationships)
- [Tree Structures and Hierarchical Data](#tree-structures-and-hierarchical-data)
- [Aggregation in Nested Relationships](#aggregation-in-nested-relationships)
- [Performance Optimization for Nested Relationships](#performance-optimization-for-nested-relationships)
- [Advanced Nested Patterns](#advanced-nested-patterns)
- [Testing Nested Relationships](#testing-nested-relationships)
- [Best Practices](#best-practices)
- [Common Pitfalls](#common-pitfalls)
- [Next Steps](#next-steps)

## Understanding Nested Relationships

Nested relationships occur when entities have relationships that themselves contain relationships, creating hierarchical or interconnected data structures.

### Common Patterns

```php
// User -> Posts -> Comments -> Replies
$user->posts->first()->comments->replies

// Category -> Subcategories -> Products -> Reviews
$category->subcategories->products->reviews

// Organization -> Departments -> Teams -> Members
$organization->departments->teams->members
```

## Deep Loading with Constraints

Load nested relationships with specific constraints at each level:

```php
class PostMapper extends Mapper
{
    public function findWithNestedData(int $postId): ?Post
    {
        return $this->query()
            ->where('id', $postId)
            ->with([
                'author' => function($query) {
                    $query->where('active', true);
                },
                'comments' => function($query) {
                    $query->where('approved', true)
                          ->orderBy('created_at', 'desc')
                          ->limit(10);
                },
                'comments.author' => function($query) {
                    $query->select(['id', 'name', 'avatar']);
                },
                'comments.replies' => function($query) {
                    $query->where('approved', true)
                          ->limit(5);
                },
                'category.parent'
            ])
            ->first();
    }
}
```

## Conditional Nested Loading

Load different nested relationships based on context:

```php
class UserMapper extends Mapper
{
    public function findForProfile(int $userId): ?User
    {
        return $this->query()
            ->where('id', $userId)
            ->with([
                'profile',
                'posts' => function($query) {
                    $query->where('published', true)
                          ->orderBy('created_at', 'desc')
                          ->limit(5);
                },
                'posts.featuredImage',
                'posts.category'
            ])
            ->first();
    }

    public function findForDashboard(int $userId): ?User
    {
        return $this->query()
            ->where('id', $userId)
            ->with([
                'profile',
                'posts' => function($query) {
                    $query->orderBy('created_at', 'desc')
                          ->limit(10);
                },
                'posts.comments' => function($query) {
                    $query->where('created_at', '>', now()->subDays(7));
                },
                'notifications' => function($query) {
                    $query->where('read', false)
                          ->orderBy('created_at', 'desc');
                }
            ])
            ->first();
    }
}
```

## Polymorphic Nested Relationships

Holloway doesn't provide a built-in polymorphic relationship type, but you can model polymorphic associations using `customMany()` or `customOne()`. Define the loading and matching logic yourself based on a type discriminator column.

```php
class CommentMapper extends Mapper
{
    public function defineRelations(): void
    {
        $this->hasMany('replies', Comment::class, 'parent_id');
        $this->belongsTo('author', User::class, 'user_id');

        // Polymorphic "commentable" — loads the parent post for each comment.
        // Extend this pattern per commentable_type as needed.
        $this->customOne('commentable',
            function($query, Collection $comments) {
                $ids = $comments
                    ->where('commentable_type', 'post')
                    ->pluck('commentable_id');

                return $query->from('posts')->whereIn('id', $ids)->get();
            },
            fn(stdClass $comment, stdClass $post) =>
                $comment->commentable_type === 'post' && $comment->commentable_id === $post->id,
            Post::class
        );
    }

    public function findWithNestedContext(int $commentId): ?Comment
    {
        return $this->query()
            ->where('id', $commentId)
            ->with([
                'commentable', // The post/video/product being commented on
                'author.profile',
                'replies' => function($query) {
                    $query->where('approved', true)
                          ->orderBy('created_at', 'asc');
                },
                'replies.author.profile'
            ])
            ->first();
    }
}
```

## Tree Structures and Hierarchical Data

Efficiently handle nested tree structures:

```php
class CategoryMapper extends Mapper
{
    public function defineRelations(): void
    {
        $this->belongsTo('parent', Category::class, 'parent_id');
        $this->hasMany('children', Category::class, 'parent_id');
        $this->hasMany('products', Product::class, 'category_id');
    }

    public function findCategoryTree(int $rootId): ?Category
    {
        return $this->query()
            ->where('id', $rootId)
            ->with([
                'children' => function($query) {
                    $query->orderBy('sort_order');
                },
                'children.children' => function($query) {
                    $query->orderBy('sort_order');
                },
                'children.children.products' => function($query) {
                    $query->where('active', true)
                          ->orderBy('name');
                }
            ])
            ->first();
    }

    public function findWithAncestors(int $categoryId): ?Category
    {
        $category = $this->find($categoryId);
        if (!$category) {
            return null;
        }

        // Load the full ancestry chain
        $ancestors = collect();
        $current = $category;
        
        while ($current->getParentId()) {
            $parent = $this->query()
                ->where('id', $current->getParentId())
                ->with('parent')
                ->first();
            
            if ($parent) {
                $ancestors->prepend($parent);
                $current = $parent;
            } else {
                break;
            }
        }

        $category->setAncestors($ancestors);
        return $category;
    }
}
```

## Aggregation in Nested Relationships

Include aggregated data from nested relationships:

```php
class PostMapper extends Mapper
{
    public function findWithStats(int $postId): ?Post
    {
        $post = $this->query()
            ->where('id', $postId)
            ->with([
                'author.profile',
                'category',
                'comments' => function($query) {
                    $query->where('approved', true);
                },
                'comments.author'
            ])
            ->first();

        if ($post) {
            // Add computed statistics
            $post->setCommentCount($post->getComments()->count());
            $post->setUniqueCommenters(
                $post->getComments()
                     ->map(fn($comment) => $comment->getAuthor()->getId())
                     ->unique()
                     ->count()
            );
        }

        return $post;
    }

    public function findPopularPostsWithEngagement(): Collection
    {
        return $this->query()
            ->where('published', true)
            ->where('published_at', '>=', now()->subDays(30))
            ->with([
                'author',
                'category',
                'comments' => function($query) {
                    $query->where('approved', true);
                },
                'likes' => function($query) {
                    $query->where('created_at', '>=', now()->subDays(30));
                }
            ])
            ->get()
            ->map(function($post) {
                $post->setEngagementScore(
                    ($post->getComments()->count() * 2) + 
                    $post->getLikes()->count()
                );
                return $post;
            })
            ->sortByDesc('engagement_score')
            ->take(10);
    }
}
```

## Performance Optimization for Nested Relationships

### Selective Loading

Only load what you need at each level:

```php
class OrderMapper extends Mapper
{
    public function findForInvoice(int $orderId): ?Order
    {
        return $this->query()
            ->where('id', $orderId)
            ->with([
                'customer' => function($query) {
                    $query->select(['id', 'name', 'email', 'billing_address']);
                },
                'items' => function($query) {
                    $query->select(['id', 'order_id', 'product_id', 'quantity', 'price']);
                },
                'items.product' => function($query) {
                    $query->select(['id', 'name', 'sku']);
                },
                'payments' => function($query) {
                    $query->where('status', 'completed');
                }
            ])
            ->first();
    }
}
```

### Batch Loading Prevention

Avoid N+1 queries in nested scenarios:

```php
class TeamMapper extends Mapper
{
    public function findTeamsWithMemberProjects(): Collection
    {
        // Load teams with all nested data in efficient queries
        $teams = $this->query()
            ->with([
                'members.profile',
                'members.projects' => function($query) {
                    $query->where('active', true);
                },
                'members.projects.client'
            ])
            ->get();

        return $teams;
    }
}
```

### Caching Nested Structures

Cache complex nested relationship results:

```php
class ProductMapper extends Mapper
{
    public function findWithCompleteDetails(int $productId): ?Product
    {
        $cacheKey = "product.complete.{$productId}";
        
        return cache()->remember($cacheKey, 3600, function() use ($productId) {
            return $this->query()
                ->where('id', $productId)
                ->with([
                    'category.parent',
                    'brand',
                    'variants.images',
                    'reviews' => function($query) {
                        $query->where('approved', true)
                              ->orderBy('created_at', 'desc')
                              ->limit(10);
                    },
                    'reviews.author.profile',
                    'relatedProducts.category',
                    'specifications'
                ])
                ->first();
        });
    }
}
```

## Advanced Nested Patterns

### Circular Reference Handling

Manage entities that reference each other:

```php
class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        $this->belongsTo('manager', User::class, 'manager_id');
        $this->hasMany('directReports', User::class, 'manager_id');
        $this->belongsTo('department', Department::class, 'department_id');
    }

    public function findOrganizationChart(int $userId, int $depth = 3): ?User
    {
        if ($depth <= 0) {
            return $this->find($userId);
        }

        return $this->query()
            ->where('id', $userId)
            ->with([
                'manager' => function($query) use ($depth) {
                    if ($depth > 1) {
                        $query->with('manager');
                    }
                },
                'directReports' => function($query) use ($depth) {
                    if ($depth > 1) {
                        $query->with('directReports');
                    }
                },
                'department'
            ])
            ->first();
    }
}
```

### Dynamic Nested Loading

Load relationships based on user permissions or context:

```php
class ProjectMapper extends Mapper
{
    public function findForUser(int $projectId, User $user): ?Project
    {
        $query = $this->query()
            ->where('id', $projectId)
            ->with(['client', 'category']);

        // Load different nested data based on user role
        if ($user->isAdmin()) {
            $query->with([
                'team.members.profile',
                'budget.transactions',
                'timeEntries.user',
                'documents' => function($q) {
                    $q->orderBy('created_at', 'desc');
                }
            ]);
        } elseif ($user->isManager()) {
            $query->with([
                'team.members' => function($q) {
                    $q->select(['id', 'name', 'email']);
                },
                'timeEntries' => function($q) {
                    $q->where('billable', true);
                },
                'documents' => function($q) {
                    $q->where('confidential', false);
                }
            ]);
        } else {
            $query->with([
                'team.members' => function($q) {
                    $q->select(['id', 'name']);
                },
                'documents' => function($q) {
                    $q->where('public', true);
                }
            ]);
        }

        return $query->first();
    }
}
```

## Testing Nested Relationships

### Factory Setup for Complex Structures

```php
// Using legacy factory syntax (pre-Laravel 8)
// Define your factories in database/factories/*.php
// Example:
// $factory->define(Post::class, function (Faker $faker) {
//     return [
//         'title' => $faker->sentence,
//         'content' => $faker->paragraphs(3, true),
//         'author_id' => factory(User::class)->create()->id,
//         'category_id' => factory(Category::class)->create()->id,
//     ];
// });
```

### Testing Nested Loading

```php
class NestedRelationshipTest extends TestCase
{
    public function testDeepNestedLoading(): void
    {
        // Arrange
        // Using legacy factory syntax (pre-Laravel 8)
        $post = factory(Post::class)->create();

        // Act
        $loadedPost = app(PostMapper::class)->findWithNestedData($post->getId());

        // Assert
        $this->assertNotNull($loadedPost);
        $this->assertNotNull($loadedPost->getAuthor()->getProfile());
        $this->assertNotNull($loadedPost->getCategory()->getParent());
        $this->assertCount(3, $loadedPost->getComments());
        
        foreach ($loadedPost->getComments() as $comment) {
            $this->assertNotNull($comment->getAuthor());
            $this->assertGreaterThanOrEqual(0, $comment->getReplies()->count());
        }
    }
}
```

## Best Practices

1. **Plan Your Loading Strategy** - Design relationships with performance in mind
2. **Use Selective Loading** - Only load fields you need at each level
3. **Implement Caching** - Cache complex nested structures appropriately
4. **Handle Circular References** - Be careful with bidirectional relationships
5. **Test Performance** - Monitor query counts and execution time
6. **Document Complexity** - Make complex nested patterns clear to other developers

## Common Pitfalls

- **Over-eager Loading** - Loading too much data unnecessarily
- **N+1 Query Traps** - Missing eager loading in nested scenarios
- **Memory Issues** - Loading large nested datasets without pagination
- **Circular Dependencies** - Infinite loops in bidirectional relationships
- **Inconsistent States** - Nested data getting out of sync

## Next Steps

- **[Eager Loading](./eager-loading.md)** - Optimizing relationship loading
- **[Custom Relationships](./custom.md)** - Building specialized relationship logic
