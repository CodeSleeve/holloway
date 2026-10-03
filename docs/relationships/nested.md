# Nested Relationships

Relationships can be nested with dot notation (`with('posts.comments')`), and each level can take its own constraints.

## Table of Contents

- [Understanding Nested Relationships](#understanding-nested-relationships)
- [Deep Loading with Constraints](#deep-loading-with-constraints)
- [Polymorphic Nested Relationships](#polymorphic-nested-relationships)
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

## Next Steps

- **[Eager Loading](./eager-loading.md)** - Optimizing relationship loading
- **[Custom Relationships](./custom.md)** - Building specialized relationship logic
