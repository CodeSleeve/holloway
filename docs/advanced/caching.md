# Entity Caching

Holloway's entity caching system is a critical performance optimization that implements the Identity Map pattern. It ensures that each database record creates only one entity instance per request, provides dirty tracking for efficient updates, and eliminates redundant entity creation.

## How Entity Caching Works

### The Identity Map Pattern

The Entity Cache implements Martin Fowler's Identity Map pattern:

```php
// Without caching (problematic):
$user1 = $userMapper->find(1);
$user2 = $userMapper->find(1);
// $user1 and $user2 are different objects for the same database record

// With caching (correct):
$user1 = $userMapper->find(1);
$user2 = $userMapper->find(1);
// $user1 and $user2 are the same object instance
var_dump($user1 === $user2); // true
```

### Cache Lifecycle

```php
// 1. First load - creates cache entry
$user = $userMapper->find(1);
// Cache: [1 => ['id' => 1, 'name' => 'John', 'email' => 'john@example.com']]

// 2. Subsequent loads - returns cached instance
$sameUser = $userMapper->find(1);
// No new entity created, returns existing instance

// 3. Updates - cache tracks changes
$user->updateEmail('newemail@example.com');
$userMapper->store($user);
// Cache updated with new data

// 4. Deletion - cache entry removed
$userMapper->remove($user);
// Cache: [] (entry removed)
```

## Entity Cache Architecture

### Cache Structure

```php
class EntityCache
{
    private string $primaryKey;
    private array $cache = [];
    
    public function set($identifier, array $attributes): void
    {
        $this->cache[$identifier] = $attributes;
    }
    
    public function get($identifier): ?array
    {
        return $this->cache[$identifier] ?? null;
    }
    
    public function has($identifier): bool
    {
        return isset($this->cache[$identifier]);
    }
    
    public function remove($identifier): void
    {
        unset($this->cache[$identifier]);
    }
    
    public function flush(): void
    {
        $this->cache = [];
    }
}
```

### Cache Key Strategy

```php
class UserMapper extends Mapper
{
    protected string $primaryKey = 'id';
    
    // Cache uses primary key as identifier
    public function getIdentifier($entity): mixed
    {
        return $entity->getId();
    }
    
    public function setIdentifier($entity, $identifier): void
    {
        $entity->setId($identifier);
    }
}

// For composite keys
class OrderItemMapper extends Mapper
{
    public function getIdentifier($entity): string
    {
        return $entity->getOrderId() . ':' . $entity->getProductId();
    }
}
```

## Cache Benefits

### 1. Prevents Duplicate Entity Creation

```php
// Load user multiple times
$user1 = $userMapper->find(1);
$user2 = $userMapper->with('posts')->find(1);
$user3 = $userMapper->where('id', 1)->first();

// All three variables reference the same object
var_dump($user1 === $user2 === $user3); // true

// Memory efficient - only one User entity exists
echo "Memory usage: " . memory_get_usage();
```

### 2. Dirty Tracking for Efficient Updates

```php
// Initial load - cache stores original attributes
$user = $userMapper->find(1);
// Cache: [1 => ['id' => 1, 'name' => 'John', 'email' => 'john@example.com']]

// Modify entity
$user->updateName('John Smith');
$user->updateEmail('johnsmith@example.com');

// Store operation compares with cache
$userMapper->store($user);
// Only changed attributes are updated in database:
// UPDATE users SET name = 'John Smith', email = 'johnsmith@example.com' WHERE id = 1

// Cache updated with new values
// Cache: [1 => ['id' => 1, 'name' => 'John Smith', 'email' => 'johnsmith@example.com']]
```

### 3. Relationship Consistency

```php
// Load user with posts
$user = $userMapper->with('posts')->find(1);

// Load specific post
$post = $postMapper->find($user->posts->first()->getId());

// The post's author relationship points to the same cached user instance
var_dump($user === $post->author); // true (when author is loaded)
```

## Cache Management

### Automatic Cache Management

```php
class Mapper
{
    protected function storeEntity($entity): bool
    {
        $identifier = $this->getIdentifier($entity);
        $cached = $this->entityCache->get($identifier);
        
        if ($cached) {
            // Update: Compare with cache for dirty detection
            $attributes = $this->dehydrate($entity);
            
            if ($attributes !== $cached) {
                // Only update if entity has changed
                $this->updateInDatabase($identifier, $attributes);
                $this->entityCache->set($identifier, $attributes);
            }
        } else {
            // Insert: New entity
            $attributes = $this->dehydrate($entity);
            $id = $this->insertIntoDatabase($attributes);
            $this->setIdentifier($entity, $id);
            $this->entityCache->set($id, $attributes);
        }
        
        return true;
    }
}
```

### Manual Cache Control

```php
$userMapper = Holloway::instance()->getMapper(User::class);

// Check cache status
$cacheCount = $userMapper->getNumberOfCachedEntities();
echo "Cached entities: {$cacheCount}";

// Clear mapper's cache
$userMapper->clearEntityCache();

// Clear all caches
Holloway::instance()->flushEntityCache();
```

### Cache in Chunk Operations

```php
// Automatic cache flushing prevents memory leaks
$userMapper->chunk(1000, function($users) {
    foreach ($users as $user) {
        $this->processUser($user);
    }
    // Cache is automatically flushed after each chunk
});

// Manual cache management in custom operations
public function bulkProcess(): void
{
    $users = $this->userMapper->where('status', 'pending')->get();
    
    foreach ($users as $user) {
        $this->processUser($user);
        
        // Flush cache every 100 entities to manage memory
        if (count($processedUsers) % 100 === 0) {
            $this->userMapper->clearEntityCache();
        }
    }
}
```

## Cache Configuration

### Custom Cache Implementation

```php
class UserMapper extends Mapper
{
    public function __construct()
    {
        parent::__construct();
        
        // Custom cache configuration
        $this->entityCache = new EntityCache($this->primaryKey);
    }
    
    // Custom entity instantiation
    public function instantiateEntity(array $attributes)
    {
        // Use dependency injection container
        return app()->make($this->entityClassName, $attributes);
    }
}
```

### Cache Key Customization

```php
class TenantAwareMapper extends Mapper
{
    public function getIdentifier($entity): string
    {
        // Multi-tenant cache keys
        $tenantId = auth()->user()->tenant_id;
        $entityId = $entity->getId();
        return "{$tenantId}:{$entityId}";
    }
    
    protected function getCacheKey($id): string
    {
        $tenantId = auth()->user()->tenant_id;
        return "{$tenantId}:{$id}";
    }
}
```

## Performance Characteristics

### Memory Usage

```php
// Memory efficient entity reuse
$users = $userMapper->limit(1000)->get();
$firstBatch = memory_get_usage();

// Loading same users again doesn't increase memory significantly
$sameUsers = $userMapper->limit(1000)->get();
$secondBatch = memory_get_usage();

echo "Memory increase: " . ($secondBatch - $firstBatch) . " bytes";
// Very small increase due to entity reuse
```

### Query Reduction

```php
// Without caching: Multiple queries for same data
$user1 = $userMapper->find(1);  // Query 1
$user2 = $userMapper->find(1);  // Query 2
$user3 = $userMapper->find(1);  // Query 3

// With caching: Single query, cached results
$user1 = $userMapper->find(1);  // Query 1
$user2 = $userMapper->find(1);  // Cache hit
$user3 = $userMapper->find(1);  // Cache hit
```

### Update Efficiency

```php
// Only changed attributes are updated
$user = $userMapper->find(1);
// Cache: ['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'status' => 'active']

$user->updateName('John Smith'); // Only name changed

$userMapper->store($user);
// UPDATE users SET name = 'John Smith', updated_at = '...' WHERE id = 1
// (email and status not included in UPDATE because they didn't change)
```

## Cache in Relationships

### Relationship Entity Caching

```php
// Load user with posts
$user = $userMapper->with('posts')->find(1);

// Each post is cached in the PostMapper's cache
$postMapper = Holloway::instance()->getMapper(Post::class);
$cachedPostCount = $postMapper->getNumberOfCachedEntities();
echo "Cached posts: {$cachedPostCount}";

// Loading specific post returns cached instance
$firstPost = $user->posts->first();
$samePost = $postMapper->find($firstPost->getId());
var_dump($firstPost === $samePost); // true
```

### Cross-Relationship Consistency

```php
// Load users with posts and their authors
$users = $userMapper->with('posts.author')->get();

// All author relationships point to cached user instances
foreach ($users as $user) {
    foreach ($user->posts as $post) {
        // $post->author is the same cached instance as $user (if same user)
        if ($post->getAuthorId() === $user->getId()) {
            var_dump($post->author === $user); // true
        }
    }
}
```

## Cache Debugging

### Cache Inspection

```php
class UserMapper extends Mapper
{
    public function debugCache(): array
    {
        return [
            'count' => $this->entityCache->count(),
            'keys' => array_keys($this->entityCache->all()),
            'memory' => memory_get_usage(),
        ];
    }
    
    public function getCacheContents(): array
    {
        return $this->entityCache->all();
    }
}

// Usage
$userMapper = Holloway::instance()->getMapper(User::class);
$users = $userMapper->limit(10)->get();

$debug = $userMapper->debugCache();
print_r($debug);
```

### Cache Hit/Miss Tracking

```php
class DiagnosticEntityCache extends EntityCache
{
    private int $hits = 0;
    private int $misses = 0;
    
    public function get($identifier): ?array
    {
        $result = parent::get($identifier);
        
        if ($result !== null) {
            $this->hits++;
            Log::debug("Cache HIT for identifier: {$identifier}");
        } else {
            $this->misses++;
            Log::debug("Cache MISS for identifier: {$identifier}");
        }
        
        return $result;
    }
    
    public function getStats(): array
    {
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hit_ratio' => $this->hits / ($this->hits + $this->misses),
        ];
    }
}
```

## Best Practices

### 1. Monitor Cache Usage

```php
class UserService
{
    public function getUsers(): Collection
    {
        $userMapper = Holloway::instance()->getMapper(User::class);
        
        $users = $userMapper->with('posts')->get();
        
        // Monitor cache efficiency
        $cacheCount = $userMapper->getNumberOfCachedEntities();
        if ($cacheCount > 1000) {
            Log::warning("High cache usage: {$cacheCount} cached entities");
        }
        
        return $users;
    }
}
```

### 2. Flush Cache Appropriately

```php
class BulkUserProcessor
{
    public function processLargeDataset(): void
    {
        $userMapper = Holloway::instance()->getMapper(User::class);
        
        $userMapper->chunk(1000, function($users) {
            foreach ($users as $user) {
                $this->processUser($user);
            }
            
            // Cache automatically flushed after each chunk
            // This prevents memory leaks in long-running processes
        });
    }
}
```

### 3. Be Aware of Cache Scope

```php
// Cache is per-mapper, per-request
class UserController
{
    public function show($id)
    {
        $userMapper = Holloway::instance()->getMapper(User::class);
        
        // First load
        $user = $userMapper->find($id);  // Database query
        
        // Subsequent loads in same request
        $sameUser = $userMapper->find($id);  // Cache hit
        
        return view('user.show', compact('user'));
    }
    
    // In different request, cache starts fresh
    public function edit($id)
    {
        $userMapper = Holloway::instance()->getMapper(User::class);
        
        // Fresh request = fresh cache
        $user = $userMapper->find($id);  // Database query (cache was cleared)
        
        return view('user.edit', compact('user'));
    }
}
```

### 4. Handle Cache Invalidation

```php
class UserMapper extends Mapper
{
    protected function removeEntity($entity): bool
    {
        $identifier = $this->getIdentifier($entity);
        
        // Perform database deletion
        $result = parent::removeEntity($entity);
        
        if ($result) {
            // Remove from cache after successful deletion
            $this->entityCache->remove($identifier);
            
            // Optionally clear related caches
            $this->clearRelatedCaches($entity);
        }
        
        return $result;
    }
    
    private function clearRelatedCaches($user): void
    {
        // Clear caches for related entities if needed
        $postMapper = Holloway::instance()->getMapper(Post::class);
        $postMapper->clearEntityCache();
    }
}
```

## Cache Limitations and Considerations

### Request Scope

```php
// Cache is request-scoped, not persistent across requests
class UserService
{
    public function getUser($id): User
    {
        // Cache only lasts for this request
        $user = $userMapper->find($id);
        
        // Next HTTP request will have empty cache
        return $user;
    }
}
```

### Memory Considerations

```php
// Large datasets can consume significant memory
class ReportGenerator
{
    public function generateLargeReport(): void
    {
        $userMapper = Holloway::instance()->getMapper(User::class);
        
        // This could cache 100,000 user entities
        $users = $userMapper->all();
        
        // Better approach: Use chunking
        $userMapper->chunk(1000, function($users) {
            $this->processChunk($users);
            // Cache automatically cleared after each chunk
        });
    }
}
```

### Concurrent Modifications

```php
// Cache doesn't handle concurrent modifications
class UserService
{
    public function updateUser($id, array $data): User
    {
        $user = $userMapper->find($id);  // Cached version
        
        // If another process modifies the user in the database,
        // this cache won't know about it until next request
        
        $user->updateFromArray($data);
        $userMapper->store($user);
        
        return $user;
    }
}
```

The entity cache is a powerful performance optimization that makes Holloway's datamapper pattern efficient and memory-conscious. Understanding its behavior is crucial for building high-performance applications with Holloway.

## Next Steps

- **[Soft Deletes](./soft-deletes.md)** - Implement soft deletion with cache integration
- **[Events & Hooks](./events.md)** - Use lifecycle events with cached entities
- **[Performance Optimization](../examples/best-practices.md)** - Advanced performance techniques
