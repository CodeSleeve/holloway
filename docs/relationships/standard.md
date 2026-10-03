# Standard Relationships

Holloway supports four standard relationship types that mirror common database relationship patterns. This guide provides comprehensive coverage of HasOne, HasMany, BelongsTo, and BelongsToMany relationships.

## Table of Contents

- [HasOne Relationships](#hasone-relationships)
- [HasMany Relationships](#hasmany-relationships)
- [BelongsTo Relationships](#belongsto-relationships)
- [BelongsToMany Relationships](#belongstomany-relationships)
- [Advanced Relationship Configurations](#advanced-relationship-configurations)
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

## Next Steps

- **[Custom Relationships](./custom.md)** - Learn to create flexible custom relationship logic
- **[Eager Loading](./eager-loading.md)** - Master efficient relationship loading strategies
- **[Nested Relationships](./nested.md)** - Work with complex multi-level relationships
