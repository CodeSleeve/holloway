# Complete Examples

One end-to-end example: a blog's entities and mappers.

## Table of Contents

- [Blog entities and mappers](#example-1-blog-management-system)
- [Next Steps](#next-steps)

## Example 1: Blog Management System

A complete blog system with users, posts, comments, and categories.

### Entities

```php
<?php

// User Entity
class User
{
    private ?int $id = null;
    private string $name;
    private Email $email;
    private UserRole $role;
    private bool $active;
    private DateTime $createdAt;
    private ?DateTime $emailVerifiedAt = null;
    private ?UserProfile $profile = null;
    private Collection $posts;
    private Collection $comments;

    public function __construct(string $name, Email $email, UserRole $role = UserRole::Member)
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Name cannot be empty');
        }
        
        $this->name = $name;
        $this->email = $email;
        $this->role = $role;
        $this->active = true;
        $this->createdAt = new DateTime();
        $this->posts = new Collection();
        $this->comments = new Collection();
    }

    public function promoteToAdmin(): void
    {
        if (!$this->emailVerifiedAt) {
            throw new InvalidOperationException('Cannot promote unverified user');
        }
        
        $this->role = UserRole::Admin;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function verifyEmail(): void
    {
        $this->emailVerifiedAt = new DateTime();
    }

    public function canPublishPosts(): bool
    {
        return $this->active && 
               $this->emailVerifiedAt !== null && 
               in_array($this->role, [UserRole::Author, UserRole::Admin]);
    }

    // Getters and setters...
    public function getId(): ?int { return $this->id; }
    public function setId(int $id): void { $this->id = $id; }
    public function getName(): string { return $this->name; }
    public function getEmail(): Email { return $this->email; }
    public function getRole(): UserRole { return $this->role; }
    public function isActive(): bool { return $this->active; }
    public function getCreatedAt(): DateTime { return $this->createdAt; }
    public function setCreatedAt(DateTime $createdAt): void { $this->createdAt = $createdAt; }
    public function getProfile(): ?UserProfile { return $this->profile; }
    public function setProfile(?UserProfile $profile): void { $this->profile = $profile; }
    public function getPosts(): Collection { return $this->posts; }
    public function setPosts(Collection $posts): void { $this->posts = $posts; }
    public function getComments(): Collection { return $this->comments; }
    public function setComments(Collection $comments): void { $this->comments = $comments; }
}

// Post Entity
class Post
{
    private ?int $id = null;
    private string $title;
    private string $content;
    private string $slug;
    private PostStatus $status;
    private int $authorId;
    private ?int $categoryId = null;
    private DateTime $createdAt;
    private ?DateTime $publishedAt = null;
    private int $viewCount = 0;
    private ?User $author = null;
    private ?Category $category = null;
    private Collection $comments;
    private Collection $tags;

    public function __construct(string $title, string $content, int $authorId)
    {
        if (empty($title) || empty($content)) {
            throw new InvalidArgumentException('Title and content are required');
        }
        
        $this->title = $title;
        $this->content = $content;
        $this->slug = Str::slug($title);
        $this->status = PostStatus::Draft;
        $this->authorId = $authorId;
        $this->createdAt = new DateTime();
        $this->comments = new Collection();
        $this->tags = new Collection();
    }

    public function publish(): void
    {
        if ($this->status === PostStatus::Published) {
            throw new InvalidOperationException('Post is already published');
        }
        
        $this->status = PostStatus::Published;
        $this->publishedAt = new DateTime();
    }

    public function unpublish(): void
    {
        $this->status = PostStatus::Draft;
        $this->publishedAt = null;
    }

    public function incrementViewCount(): void
    {
        $this->viewCount++;
    }

    public function assignToCategory(?Category $category): void
    {
        $this->category = $category;
        $this->categoryId = $category?->getId();
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published && 
               $this->publishedAt !== null;
    }

    // Getters and setters...
}

// Supporting Value Objects and Enums
enum UserRole: string
{
    case Member = 'member';
    case Author = 'author'; 
    case Admin = 'admin';
}

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}

class Email
{
    private readonly string $value;

    public function __construct(string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        $this->value = strtolower($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getDomain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

### Mappers

```php
<?php

// User Mapper
class UserMapper extends Mapper
{
    protected string $table = 'users';
    protected string $entityClassName = User::class;
    protected bool $hasTimestamps = true;

    public function defineRelations(): void
    {
        $this->hasOne('profile', UserProfile::class);
        $this->hasMany('posts', Post::class, 'author_id');
        $this->hasMany('comments', Comment::class, 'author_id');
        
        // Custom relationship for published posts only
        $this->customMany('publishedPosts', function($query, $users) {
            return $query->from('posts')
                ->whereIn('author_id', $users->pluck('id'))
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->orderBy('published_at', 'desc')
                ->get();
        }, function($user, $post) {
            return $user->id == $post->author_id;
        }, Post::class);
    }

    public function dehydrate($entity): array
    {
        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'email' => $entity->getEmail()->getValue(),
            'role' => $entity->getRole()->value,
            'active' => $entity->isActive(),
            'email_verified_at' => $entity->getEmailVerifiedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function hydrate(stdClass $record, Collection $relations)
    {
        $user = new User(
            $record->name,
            new Email($record->email),
            UserRole::from($record->role)
        );
        
        if (isset($record->id)) {
            $user->setId($record->id);
        }
        
        if (isset($record->created_at)) {
            $user->setCreatedAt(new DateTime($record->created_at));
        }
        
        if ($record->email_verified_at) {
            $user->setEmailVerifiedAt(new DateTime($record->email_verified_at));
        }
        
        if (!$record->active) {
            $user->deactivate();
        }
        
        // Load relationships
        if ($relations) {
            if (isset($relations['profile'])) {
                $user->setProfile($relations['profile']);
            }
            
            if (isset($relations['posts'])) {
                $user->setPosts($relations['posts']);
            }
            
            if (isset($relations['comments'])) {
                $user->setComments($relations['comments']);
            }
            
            if (isset($relations['publishedPosts'])) {
                $user->setPublishedPosts($relations['publishedPosts']);
            }
        }
        
        return $user;
    }

    // Query scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopeByRole($query, UserRole $role)
    {
        return $query->where('role', $role->value);
    }

    public function scopeAuthors($query)
    {
        return $query->whereIn('role', [UserRole::Author->value, UserRole::Admin->value]);
    }
}

// Post Mapper
class PostMapper extends Mapper
{
    protected string $table = 'posts';
    protected string $entityClassName = Post::class;
    protected bool $hasTimestamps = true;

    public function defineRelations(): void
    {
        $this->belongsTo('author', User::class, 'author_id');
        $this->belongsTo('category', Category::class);
        $this->hasMany('comments', Comment::class);
        $this->belongsToMany('tags', Tag::class, 'post_tags');
        
        // Custom relationship for approved comments
        $this->customMany('approvedComments', function($query, $posts) {
            return $query->from('comments')
                ->whereIn('post_id', $posts->pluck('id'))
                ->where('approved', true)
                ->orderBy('created_at', 'asc')
                ->get();
        }, function($post, $comment) {
            return $post->id == $comment->post_id;
        }, Comment::class);
    }

    public function dehydrate($entity): array
    {
        return [
            'id' => $entity->getId(),
            'title' => $entity->getTitle(),
            'content' => $entity->getContent(),
            'slug' => $entity->getSlug(),
            'status' => $entity->getStatus()->value,
            'author_id' => $entity->getAuthorId(),
            'category_id' => $entity->getCategoryId(),
            'published_at' => $entity->getPublishedAt()?->format('Y-m-d H:i:s'),
            'view_count' => $entity->getViewCount(),
        ];
    }

    public function hydrate(stdClass $record, Collection $relations)
    {
        $post = new Post(
            $record->title,
            $record->content,
            $record->author_id
        );
        
        if (isset($record->id)) {
            $post->setId($record->id);
        }
        
        $post->setSlug($record->slug);
        $post->setStatus(PostStatus::from($record->status));
        $post->setViewCount($record->view_count);
        
        if ($record->category_id) {
            $post->setCategoryId($record->category_id);
        }
        
        if ($record->published_at) {
            $post->setPublishedAt(new DateTime($record->published_at));
        }
        
        if (isset($record->created_at)) {
            $post->setCreatedAt(new DateTime($record->created_at));
        }
        
        // Load relationships
        if ($relations) {
            if (isset($relations['author'])) {
                $post->setAuthor($relations['author']);
            }
            
            if (isset($relations['category'])) {
                $post->assignToCategory($relations['category']);
            }
            
            if (isset($relations['comments'])) {
                $post->setComments($relations['comments']);
            }
            
            if (isset($relations['tags'])) {
                $post->setTags($relations['tags']);
            }
        }
        
        return $post;
    }

    // Query scopes
    public function scopePublished($query)
    {
        return $query->where('status', PostStatus::Published->value)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
    }

    public function scopeDrafts($query)
    {
        return $query->where('status', PostStatus::Draft->value);
    }

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopePopular($query, int $minViews = 100)
    {
        return $query->where('view_count', '>=', $minViews);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
```

## Next Steps

- **[Creating Mappers](../mappers/creating-mappers.md)** - Mapper configuration and the required methods
- **[Relationships](../relationships/overview.md)** - Defining and loading relationships
