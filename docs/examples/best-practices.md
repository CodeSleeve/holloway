# Best Practices

This guide covers advanced patterns, performance optimizations, and proven techniques for building robust applications with Holloway's datamapper architecture.

## Architecture Best Practices

### Entity Design

#### Keep Entities Pure
```php
// Good - Pure domain logic
class Order
{
    private OrderStatus $status;
    private Collection $items;
    private Money $total;

    public function addItem(OrderItem $item): void
    {
        if ($this->status->isClosed()) {
            throw new InvalidOperationException('Cannot modify closed order');
        }
        
        $this->items->add($item);
        $this->recalculateTotal();
    }

    public function close(): void
    {
        if ($this->items->isEmpty()) {
            throw new InvalidOperationException('Cannot close empty order');
        }
        
        $this->status = OrderStatus::Closed;
        $this->closedAt = new DateTime();
    }

    private function recalculateTotal(): void
    {
        $this->total = $this->items->sum(fn($item) => $item->getSubtotal());
    }
}

// Avoid - Persistence concerns in entity
class OrderBad
{
    public function save(): void
    {
        // Don't do this - persistence belongs in mappers
        app(OrderMapper::class)->save($this);
    }
}
```

#### Use Value Objects
```php
class Email
{
    private string $value;

    public function __construct(string $email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        $this->value = strtolower($email);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getDomain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
    }
}

class Money
{
    private int $amountInCents;
    private string $currency;

    public function __construct(float $amount, string $currency = 'USD')
    {
        $this->amountInCents = (int)round($amount * 100);
        $this->currency = $currency;
    }

    public function add(Money $other): Money
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch');
        }
        
        return new Money(
            ($this->amountInCents + $other->amountInCents) / 100,
            $this->currency
        );
    }
}
```

### Mapper Organization

#### Single Responsibility
```php
// Good - Focused mapper
class UserMapper extends Mapper
{
    public function findByEmail(string $email): ?User
    {
        return $this->query()->where('email', $email)->first();
    }

    public function findActiveUsers(): Collection
    {
        return $this->query()->where('active', true)->get();
    }
}

// Consider separate specialized mappers for complex queries
class UserAnalyticsMapper extends Mapper
{
    protected string $table = 'users';

    public function getUserEngagementStats(DateTime $from, DateTime $to): array
    {
        return $this->query()
            ->selectRaw('
                COUNT(*) as total_users,
                AVG(login_count) as avg_logins,
                SUM(time_spent) as total_time
            ')
            ->whereBetween('last_active_at', [$from, $to])
            ->first();
    }
}
```

#### Repository Pattern Integration
```php
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function save(User $user): void;
    public function delete(User $user): void;
}

class UserRepository implements UserRepositoryInterface
{
    public function __construct(private UserMapper $mapper) {}

    public function findById(int $id): ?User
    {
        return $this->mapper->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->mapper->findByEmail($email);
    }

    public function save(User $user): void
    {
        $this->mapper->save($user);
    }

    public function delete(User $user): void
    {
        $this->mapper->delete($user);
    }

    public function findActiveUsersWithProfile(): Collection
    {
        return $this->mapper->query()
            ->where('active', true)
            ->with('profile')
            ->get();
    }
}
```

## Performance Optimization

### Query Optimization

#### Selective Loading
```php
class PostMapper extends Mapper
{
    public function findForListing(): Collection
    {
        // Only load what's needed for list view
        return $this->query()
            ->select(['id', 'title', 'excerpt', 'published_at', 'author_id'])
            ->with([
                'author' => function($query) {
                    $query->select(['id', 'name', 'avatar']);
                }
            ])
            ->where('published', true)
            ->orderBy('published_at', 'desc')
            ->get();
    }

    public function findForDetail(int $id): ?Post
    {
        // Load everything needed for detail view
        return $this->query()
            ->where('id', $id)
            ->with([
                'author.profile',
                'category',
                'tags',
                'comments' => function($query) {
                    $query->where('approved', true)
                          ->orderBy('created_at', 'desc')
                          ->limit(10);
                }
            ])
            ->first();
    }
}
```

#### Efficient Pagination
```php
class PostMapper extends Mapper
{
    public function paginateWithCursor(int $limit = 20, ?int $cursor = null): Collection
    {
        $query = $this->query()
            ->select(['id', 'title', 'published_at', 'author_id'])
            ->where('published', true)
            ->orderBy('id', 'desc')
            ->limit($limit + 1); // +1 to check if there are more

        if ($cursor) {
            $query->where('id', '<', $cursor);
        }

        $results = $query->get();
        $hasMore = $results->count() > $limit;
        
        if ($hasMore) {
            $results->pop(); // Remove the extra item
        }

        return $results->map(function($post) use ($hasMore) {
            $post->setHasMore($hasMore);
            return $post;
        });
    }
}
```

### Caching Strategies

#### Entity Caching
```php
class UserMapper extends Mapper
{
    private const CACHE_TTL = 3600; // 1 hour

    public function find(int $id): ?User
    {
        $cacheKey = "user.{$id}";
        
        return cache()->remember($cacheKey, self::CACHE_TTL, function() use ($id) {
            return parent::find($id);
        });
    }

    public function save(User $user): void
    {
        parent::save($user);
        
        // Invalidate cache
        if ($user->getId()) {
            cache()->forget("user.{$user->getId()}");
        }
    }

    public function findWithProfile(int $id): ?User
    {
        $cacheKey = "user.profile.{$id}";
        
        return cache()->remember($cacheKey, self::CACHE_TTL, function() use ($id) {
            return $this->query()
                ->where('id', $id)
                ->with('profile')
                ->first();
        });
    }
}
```

#### Query Result Caching
```php
class PostMapper extends Mapper
{
    public function findPopular(int $limit = 10): Collection
    {
        $cacheKey = "posts.popular.{$limit}";
        
        return cache()->remember($cacheKey, 1800, function() use ($limit) {
            return $this->query()
                ->where('published', true)
                ->orderBy('view_count', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    public function findTrending(int $days = 7): Collection
    {
        $cacheKey = "posts.trending.{$days}";
        
        return cache()->remember($cacheKey, 900, function() use ($days) {
            return $this->query()
                ->where('created_at', '>=', now()->subDays($days))
                ->orderByRaw('(view_count + comment_count * 2) DESC')
                ->limit(20)
                ->get();
        });
    }
}
```

### Memory Management

#### Chunked Processing
```php
class UserMapper extends Mapper
{
    public function processAllUsers(callable $callback): void
    {
        $this->query()
            ->chunk(1000, function($users) use ($callback) {
                foreach ($users as $user) {
                    $callback($user);
                }
                
                // Clear entity cache to prevent memory buildup
                $this->clearEntityCache();
            });
    }

    public function exportToCSV(string $filename): void
    {
        $file = fopen($filename, 'w');
        
        // Write header
        fputcsv($file, ['ID', 'Name', 'Email', 'Created At']);
        
        $this->query()
            ->select(['id', 'name', 'email', 'created_at'])
            ->chunk(1000, function($users) use ($file) {
                foreach ($users as $user) {
                    fputcsv($file, [
                        $user->getId(),
                        $user->getName(),
                        $user->getEmail(),
                        $user->getCreatedAt()->format('Y-m-d H:i:s')
                    ]);
                }
            });
        
        fclose($file);
    }
}
```

## Error Handling

### Domain Exceptions
```php
class OrderException extends DomainException {}
class InvalidOrderStateException extends OrderException {}
class InsufficientInventoryException extends OrderException {}

class Order
{
    public function ship(): void
    {
        if (!$this->status->canShip()) {
            throw new InvalidOrderStateException(
                "Order {$this->id} cannot be shipped in {$this->status} state"
            );
        }
        
        foreach ($this->items as $item) {
            if (!$item->getProduct()->hasStock($item->getQuantity())) {
                throw new InsufficientInventoryException(
                    "Insufficient stock for product {$item->getProduct()->getSku()}"
                );
            }
        }
        
        $this->status = OrderStatus::Shipped;
        $this->shippedAt = new DateTime();
    }
}
```

### Graceful Degradation
```php
class PostMapper extends Mapper
{
    public function findWithFallback(int $id): ?Post
    {
        try {
            // Try to load with all relationships
            return $this->query()
                ->where('id', $id)
                ->with(['author', 'category', 'tags', 'comments'])
                ->first();
        } catch (DatabaseException $e) {
            // Log error and fall back to basic entity
            logger()->error('Failed to load post with relationships', [
                'post_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->find($id);
        }
    }
}
```

## Testing Strategies

### Factory Organization
```php
class UserFactory extends Factory
{
    protected function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'role' => UserRole::Member,
            'active' => true,
            'created_at' => now(),
        ];
    }

    public function admin(): self
    {
        return $this->state(['role' => UserRole::Admin]);
    }

    public function inactive(): self
    {
        return $this->state(['active' => false]);
    }

    public function withProfile(): self
    {
        return $this->afterCreating(function(User $user) {
            UserProfileFactory::new()->for($user)->create();
        });
    }

    public function withPosts(int $count = 3): self
    {
        return $this->afterCreating(function(User $user) use ($count) {
            PostFactory::new()->for($user)->count($count)->create();
        });
    }
}
```

### Comprehensive Testing
```php
class PostMapperTest extends TestCase
{
    public function testFindWithRelationships(): void
    {
        // Arrange
        $post = PostFactory::new()
            ->withAuthor()
            ->withCategory()
            ->withComments(3)
            ->create();

        // Act
        $loadedPost = app(PostMapper::class)->findWithRelationships($post->getId());

        // Assert
        $this->assertNotNull($loadedPost);
        $this->assertInstanceOf(User::class, $loadedPost->getAuthor());
        $this->assertInstanceOf(Category::class, $loadedPost->getCategory());
        $this->assertCount(3, $loadedPost->getComments());
    }

    public function testCachingBehavior(): void
    {
        // Arrange
        $post = PostFactory::new()->create();
        cache()->flush();

        // Act - First call should hit database
        $first = app(PostMapper::class)->findCached($post->getId());
        
        // Act - Second call should hit cache
        $second = app(PostMapper::class)->findCached($post->getId());

        // Assert
        $this->assertEquals($first->getId(), $second->getId());
        $this->assertTrue(cache()->has("post.{$post->getId()}"));
    }
}
```

## Security Best Practices

### Input Validation
```php
class UserMapper extends Mapper
{
    public function createUser(array $data): User
    {
        $validatedData = $this->validateUserData($data);
        
        $user = new User(
            $validatedData['name'],
            new Email($validatedData['email']),
            UserRole::from($validatedData['role'] ?? 'member')
        );

        return $this->save($user);
    }

    private function validateUserData(array $data): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'role' => 'sometimes|in:admin,member,moderator'
        ];

        return validator($data, $rules)->validate();
    }
}
```

### SQL Injection Prevention
```php
class PostMapper extends Mapper
{
    public function search(string $term): Collection
    {
        // Always use parameter binding
        return $this->query()
            ->whereRaw('MATCH(title, content) AGAINST(? IN BOOLEAN MODE)', [$term])
            ->get();
    }

    public function findByIds(array $ids): Collection
    {
        // Validate and sanitize input
        $ids = array_filter($ids, 'is_numeric');
        
        if (empty($ids)) {
            return new Collection();
        }

        return $this->query()->whereIn('id', $ids)->get();
    }
}
```

## Monitoring and Debugging

### Query Logging
```php
class PostMapper extends Mapper
{
    protected bool $logQueries = true;

    public function findPopular(): Collection
    {
        $startTime = microtime(true);
        
        $result = $this->query()
            ->where('view_count', '>', 1000)
            ->orderBy('view_count', 'desc')
            ->get();
        
        if ($this->logQueries) {
            $duration = microtime(true) - $startTime;
            logger()->info('Query executed', [
                'mapper' => static::class,
                'method' => 'findPopular',
                'duration' => $duration,
                'count' => $result->count()
            ]);
        }
        
        return $result;
    }
}
```

### Performance Monitoring
```php
class PostMapper extends Mapper
{
    public function findWithMetrics(int $id): ?Post
    {
        return app('metrics')->time('post.find.duration', function() use ($id) {
            $post = $this->find($id);
            
            if ($post) {
                app('metrics')->increment('post.find.hit');
            } else {
                app('metrics')->increment('post.find.miss');
            }
            
            return $post;
        });
    }
}
```

## Code Organization

### Service Layer Pattern
```php
class PostService
{
    public function __construct(
        private PostRepository $posts,
        private UserRepository $users,
        private NotificationService $notifications
    ) {}

    public function publishPost(int $postId, int $authorId): Post
    {
        $post = $this->posts->findById($postId);
        if (!$post) {
            throw new PostNotFoundException("Post {$postId} not found");
        }

        $author = $this->users->findById($authorId);
        if (!$author->canPublishPosts()) {
            throw new InsufficientPermissionsException();
        }

        $post->publish();
        $this->posts->save($post);

        $this->notifications->notifyFollowers($author, $post);

        return $post;
    }
}
```

### Command Pattern
```php
class CreatePostCommand
{
    public function __construct(
        public readonly string $title,
        public readonly string $content,
        public readonly int $authorId,
        public readonly ?int $categoryId = null
    ) {}
}

class CreatePostHandler
{
    public function __construct(
        private PostRepository $posts,
        private UserRepository $users,
        private CategoryRepository $categories
    ) {}

    public function handle(CreatePostCommand $command): Post
    {
        $author = $this->users->findById($command->authorId);
        if (!$author) {
            throw new UserNotFoundException();
        }

        $category = null;
        if ($command->categoryId) {
            $category = $this->categories->findById($command->categoryId);
        }

        $post = new Post(
            $command->title,
            $command->content,
            $author
        );

        if ($category) {
            $post->setCategory($category);
        }

        return $this->posts->save($post);
    }
}
```

## Performance Benchmarking

### Query Performance Testing
```php
class PerformanceTest extends TestCase
{
    public function testQueryPerformance(): void
    {
        // Arrange
        UserFactory::new()->count(10000)->create();
        
        // Act & Assert
        $this->assertQueryCountLessThan(5, function() {
            app(UserMapper::class)->findActiveUsersWithProfiles();
        });
        
        $this->assertExecutionTimeLessThan(100, function() {
            app(UserMapper::class)->findPopularUsers();
        });
    }

    private function assertQueryCountLessThan(int $max, callable $callback): void
    {
        $queryCount = 0;
        DB::listen(function() use (&$queryCount) {
            $queryCount++;
        });

        $callback();

        $this->assertLessThan($max, $queryCount, "Expected less than {$max} queries");
    }
}
```

## Production Deployment

### Configuration Management
```php
// config/holloway.php
return [
    'cache' => [
        'enabled' => env('HOLLOWAY_CACHE_ENABLED', true),
        'ttl' => env('HOLLOWAY_CACHE_TTL', 3600),
        'prefix' => env('HOLLOWAY_CACHE_PREFIX', 'holloway:'),
    ],
    
    'performance' => [
        'log_slow_queries' => env('HOLLOWAY_LOG_SLOW_QUERIES', true),
        'slow_query_threshold' => env('HOLLOWAY_SLOW_QUERY_THRESHOLD', 1000), // ms
        'chunk_size' => env('HOLLOWAY_CHUNK_SIZE', 1000),
    ],
    
    'features' => [
        'soft_deletes' => env('HOLLOWAY_SOFT_DELETES', true),
        'timestamps' => env('HOLLOWAY_TIMESTAMPS', true),
        'uuid_primary_keys' => env('HOLLOWAY_UUID_PRIMARY_KEYS', false),
    ],
];
```

These best practices provide a foundation for building maintainable, performant, and robust applications with Holloway. Adapt them to your specific use case and requirements.

## Next Steps

- **[Migration Guide](./migration-guide.md)** - Moving from other ORMs
- **[Testing Guide](../advanced/testing.md)** - Comprehensive testing strategies  
- **[Performance Guide](../advanced/performance.md)** - Advanced optimization techniques
