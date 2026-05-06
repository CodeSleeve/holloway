# Standard Relationships

Holloway supports four standard relationship types that mirror common database relationship patterns. This guide provides comprehensive coverage of HasOne, HasMany, BelongsTo, and BelongsToMany relationships.

## Table of Contents

- [HasOne Relationships](#hasone-relationships)
- [HasMany Relationships](#hasmany-relationships)
- [BelongsTo Relationships](#belongsto-relationships)
- [BelongsToMany Relationships](#belongstomany-relationships)
- [Advanced Relationship Configurations](#advanced-relationship-configurations)
- [Relationship Loading Patterns](#relationship-loading-patterns)
- [Common Patterns and Use Cases](#common-patterns-and-use-cases)
- [Troubleshooting Common Issues](#troubleshooting-common-issues)
- [Best Practices](#best-practices)
- [Next Steps](#next-steps)

## HasOne Relationships

A HasOne relationship represents a one-to-one connection where one entity "has one" related entity.

### Basic HasOne Definition

```php
class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        // User has one profile
        $this->hasOne('profile', UserProfile::class);
        
        // With custom keys
        $this->hasOne('profile', UserProfile::class, 'user_id', 'id');
        
        // Full specification
        $this->hasOne(
            'profile',              // Relationship name
            UserProfile::class,     // Related entity
            'user_id',             // Foreign key on profiles table
            'id'                   // Local key on users table
        );
    }
}
```

### HasOne Usage

```php
$userMapper = Holloway::instance()->getMapper(User::class);

// Load user with profile
$user = $userMapper->with('profile')->find(1);
$profileBio = $user->profile->bio;

// Profile will be null if not loaded or doesn't exist
$user = $userMapper->find(1);
$profile = $user->profile; // null - not loaded

// Load multiple users with profiles
$users = $userMapper->with('profile')->get();
foreach ($users as $user) {
    if ($user->profile) {
        echo $user->profile->bio;
    }
}
```

### HasOne Entity Implementation

```php
class User
{
    private ?UserProfile $profile = null;
    
    public function getProfile(): ?UserProfile
    {
        return $this->profile;
    }
    
    public function setProfile(?UserProfile $profile): void
    {
        $this->profile = $profile;
    }
}

class UserProfile
{
    private string $bio;
    private string $avatar;
    private int $userId;
    
    public function __construct(string $bio, string $avatar, int $userId)
    {
        $this->bio = $bio;
        $this->avatar = $avatar;
        $this->userId = $userId;
    }
    
    // Getters...
}
```

## HasMany Relationships

A HasMany relationship represents a one-to-many connection where one entity "has many" related entities.

### Basic HasMany Definition

```php
class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        // User has many posts
        $this->hasMany('posts', Post::class);
        
        // With custom keys
        $this->hasMany('posts', Post::class, 'author_id', 'id');
        
        // Multiple hasMany relationships
        $this->hasMany('posts', Post::class);
        $this->hasMany('comments', Comment::class);
        $this->hasMany('orders', Order::class, 'customer_id');
    }
}
```

### HasMany Usage

```php
$userMapper = Holloway::instance()->getMapper(User::class);

// Load user with posts
$user = $userMapper->with('posts')->find(1);
echo "User has " . count($user->posts) . " posts";

foreach ($user->posts as $post) {
    echo $post->title;
}

// Load with constraints
$user = $userMapper->with([
    'posts' => function($query) {
        $query->where('published', true)
              ->orderBy('created_at', 'desc')
              ->limit(5);
    }
])->find(1);

// Load multiple relationships
$users = $userMapper->with(['posts', 'comments'])->get();
```

### HasMany Entity Implementation

```php
class User
{
    private Collection $posts;
    private Collection $comments;
    
    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
        $this->posts = new Collection();
        $this->comments = new Collection();
    }
    
    public function getPosts(): Collection
    {
        return $this->posts;
    }
    
    public function setPosts(Collection $posts): void
    {
        $this->posts = $posts;
    }
    
    public function addPost(Post $post): void
    {
        $this->posts->push($post);
    }
    
    public function getPublishedPosts(): Collection
    {
        return $this->posts->filter(function(Post $post) {
            return $post->isPublished();
        });
    }
}
```

## BelongsTo Relationships

A BelongsTo relationship represents the inverse of a HasOne or HasMany relationship.

### Basic BelongsTo Definition

```php
class PostMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Post belongs to user (author)
        $this->belongsTo('author', User::class);
        
        // With custom keys
        $this->belongsTo('author', User::class, 'author_id', 'id');
        
        // Multiple belongsTo relationships
        $this->belongsTo('author', User::class);
        $this->belongsTo('category', Category::class);
        $this->belongsTo('editor', User::class, 'edited_by', 'id');
    }
}
```

### BelongsTo Usage

```php
$postMapper = Holloway::instance()->getMapper(Post::class);

// Load post with author
$post = $postMapper->with('author')->find(1);
echo "Written by: " . $post->author->name;

// Load multiple posts with authors
$posts = $postMapper->with(['author', 'category'])->get();
foreach ($posts as $post) {
    echo "{$post->title} by {$post->author->name} in {$post->category->name}";
}

// Nested loading
$posts = $postMapper->with('author.profile')->get();
foreach ($posts as $post) {
    $authorBio = $post->author->profile->bio;
}
```

### BelongsTo Entity Implementation

```php
class Post
{
    private string $title;
    private string $content;
    private int $authorId;
    private ?User $author = null;
    private ?Category $category = null;
    
    public function __construct(string $title, string $content, int $authorId)
    {
        $this->title = $title;
        $this->content = $content;
        $this->authorId = $authorId;
    }
    
    public function getAuthor(): ?User
    {
        return $this->author;
    }
    
    public function setAuthor(?User $author): void
    {
        $this->author = $author;
        if ($author) {
            $this->authorId = $author->getId();
        }
    }
    
    public function getAuthorId(): int
    {
        return $this->authorId;
    }
}
```

## BelongsToMany Relationships

A BelongsToMany relationship represents a many-to-many connection using a pivot table.

### Basic BelongsToMany Definition

```php
class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        // User belongs to many roles
        $this->belongsToMany('roles', Role::class);
        
        // With custom pivot table
        $this->belongsToMany('roles', Role::class, 'user_roles');
        
        // Full specification
        $this->belongsToMany(
            'roles',                // Relationship name
            Role::class,           // Related entity
            'user_roles',          // Pivot table name
            'user_id',            // Local key in pivot table
            'role_id'             // Foreign key in pivot table
        );
        
        // Multiple many-to-many relationships
        $this->belongsToMany('roles', Role::class);
        $this->belongsToMany('permissions', Permission::class);
        $this->belongsToMany('groups', Group::class, 'group_members');
    }
}
```

### BelongsToMany Usage

```php
$userMapper = Holloway::instance()->getMapper(User::class);

// Load user with roles
$user = $userMapper->with('roles')->find(1);
foreach ($user->roles as $role) {
    echo $role->name;
}

// Check if user has specific role
$hasAdminRole = $user->roles->contains(function(Role $role) {
    return $role->getName() === 'admin';
});

// Load with constraints
$user = $userMapper->with([
    'roles' => function($query) {
        $query->where('active', true)
              ->orderBy('priority', 'desc');
    }
])->find(1);

// Multiple many-to-many relationships
$users = $userMapper->with(['roles', 'permissions', 'groups'])->get();
```

### BelongsToMany Entity Implementation

```php
class User
{
    private Collection $roles;
    private Collection $permissions;
    
    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
        $this->roles = new Collection();
        $this->permissions = new Collection();
    }
    
    public function getRoles(): Collection
    {
        return $this->roles;
    }
    
    public function setRoles(Collection $roles): void
    {
        $this->roles = $roles;
    }
    
    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains(function(Role $role) use ($roleName) {
            return $role->getName() === $roleName;
        });
    }
    
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles->contains(function(Role $role) use ($roleNames) {
            return in_array($role->getName(), $roleNames);
        });
    }
    
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }
}
```

## Advanced Relationship Configurations

### Custom Key Names

```php
class PostMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Custom foreign key names
        $this->belongsTo('author', User::class, 'created_by', 'id');
        $this->belongsTo('editor', User::class, 'modified_by', 'id');
        
        // Custom local key names
        $this->hasMany('posts', Post::class, 'author_id', 'user_uuid');
        
        // Both custom
        $this->belongsToMany(
            'tags',
            Tag::class,
            'post_tags',        // Custom pivot table
            'article_id',       // Custom local pivot key
            'tag_uuid'          // Custom foreign pivot key
        );
    }
}
```

### Relationship Constraints with Scopes

```php
class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        $this->hasMany('posts', Post::class);
        $this->hasMany('publishedPosts', Post::class);
    }
}

// Apply constraints when loading
$user = $userMapper->with([
    'publishedPosts' => function($query) {
        $query->where('status', 'published')
              ->where('published_at', '<=', now())
              ->orderBy('published_at', 'desc');
    }
])->find(1);
```

## Relationship Loading Patterns

### Conditional Loading

```php
// Load different relationships based on user role
$userMapper = Holloway::instance()->getMapper(User::class);

$relationships = ['profile'];
if ($currentUser->isAdmin()) {
    $relationships[] = 'adminNotes';
    $relationships[] = 'permissions';
}

$user = $userMapper->with($relationships)->find($userId);
```

### Selective Loading

```php
// Load only specific columns from related entities
$users = $userMapper->with([
    'profile' => function($query) {
        $query->select(['user_id', 'avatar', 'bio']); // Only needed columns
    },
    'roles' => function($query) {
        $query->select(['id', 'name', 'color'])
              ->where('active', true);
    }
])->get();
```

### Performance Optimization

```php
// Optimize for large datasets
$users = $userMapper
    ->select(['id', 'name', 'email']) // Limit main table columns
    ->with([
        'posts' => function($query) {
            $query->select(['id', 'user_id', 'title', 'published_at'])
                  ->where('published', true)
                  ->limit(5); // Limit relationship results
        }
    ])
    ->where('active', true)
    ->get();
```

## Common Patterns and Use Cases

### User Authorization System

```php
class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        $this->belongsToMany('roles', Role::class);
        $this->belongsToMany('permissions', Permission::class);
        $this->belongsToMany('groups', Group::class);
    }
}

class RoleMapper extends Mapper
{
    public function defineRelations(): void
    {
        $this->belongsToMany('permissions', Permission::class);
        $this->belongsToMany('users', User::class);
    }
}

// Load user with complete authorization context
$user = $userMapper->with([
    'roles.permissions',
    'permissions',
    'groups.permissions'
])->find($userId);
```

### Content Management System

```php
class PostMapper extends Mapper
{
    public function defineRelations(): void
    {
        $this->belongsTo('author', User::class);
        $this->belongsTo('category', Category::class);
        $this->belongsTo('editor', User::class, 'edited_by');
        $this->hasMany('comments', Comment::class);
        $this->belongsToMany('tags', Tag::class);
    }
}

// Load post with complete context
$post = $postMapper->with([
    'author.profile',
    'category',
    'editor',
    'comments.author',
    'tags'
])->find($postId);
```

### E-commerce System

```php
class OrderMapper extends Mapper
{
    public function defineRelations(): void
    {
        $this->belongsTo('customer', User::class);
        $this->hasMany('items', OrderItem::class);
        $this->hasOne('payment', Payment::class);
        $this->hasOne('shipping', ShippingInfo::class);
    }
}

class OrderItemMapper extends Mapper
{
    public function defineRelations(): void
    {
        $this->belongsTo('order', Order::class);
        $this->belongsTo('product', Product::class);
    }
}

// Load order with all related data
$order = $orderMapper->with([
    'customer.profile',
    'items.product',
    'payment',
    'shipping'
])->find($orderId);
```

## Troubleshooting Common Issues

### Missing Relationships

```php
// Problem: Relationship returns null/empty
$user = $userMapper->find(1);
$posts = $user->posts; // null or empty

// Solution: Ensure relationship is loaded
$user = $userMapper->with('posts')->find(1);
$posts = $user->posts; // Now properly loaded
```

### Incorrect Key Configuration

```php
// Problem: Relationship not loading due to wrong keys
$this->hasMany('posts', Post::class, 'wrong_key', 'id');

// Solution: Verify foreign and local keys
$this->hasMany('posts', Post::class, 'user_id', 'id');
//                                   ↑ correct foreign key
```

### Circular Reference Issues

```php
// Problem: Circular loading
$user = $userMapper->with('posts.author')->find(1);
// This could create circular references

// Solution: Be selective about nested loading
$user = $userMapper->with([
    'posts' => function($query) {
        $query->select(['id', 'user_id', 'title']); // Don't load author again
    }
])->find(1);
```

## Best Practices

### 1. Use Explicit Key Names

```php
// Good: Explicit and clear
$this->hasMany('posts', Post::class, 'author_id', 'id');

// Avoid: Relying on conventions (less clear)
$this->hasMany('posts', Post::class);
```

### 2. Load Only What You Need

```php
// Good: Selective loading
$user = $userMapper->with(['posts' => function($query) {
    $query->select(['id', 'user_id', 'title', 'published_at'])
          ->where('published', true)
          ->limit(10);
}])->find(1);

// Avoid: Loading everything
$user = $userMapper->with('posts')->find(1);
```

### 3. Use Constraints for Performance

```php
// Good: Database-level filtering
$user = $userMapper->with([
    'orders' => function($query) {
        $query->where('status', 'completed')
              ->where('created_at', '>', now()->subYear());
    }
])->find(1);

// Avoid: PHP-level filtering after loading all data
$user = $userMapper->with('orders')->find(1);
$recentOrders = $user->orders->filter(function($order) {
    return $order->status === 'completed' && 
           $order->created_at > now()->subYear();
});
```

## Next Steps

- **[Custom Relationships](./custom.md)** - Learn to create flexible custom relationship logic
- **[Eager Loading](./eager-loading.md)** - Master efficient relationship loading strategies
- **[Nested Relationships](./nested.md)** - Work with complex multi-level relationships
