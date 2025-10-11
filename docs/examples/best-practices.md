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
class UserMapper extends Mapper
{
    public function findByEmail(string $email): ?User
    {
        return $this->query()->where('email', $email)->first();
    }
}
```
> For more complex queries, simply add additional methods to your mapper as needed.


#### Persistence
To persist an entity, simply call:
```php
$mapper->store($entity);
```
Holloway handles all persistence logic for you. No repository or service layer is required unless you want to add additional business logic.

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


### Testing Patterns
// Using legacy factory syntax (pre-Laravel 8)
$user = factory(User::class)->create();
$post = factory(Post::class)->create();
> Define your factories in `database/factories/*.php` as per Laravel legacy conventions.


### Comprehensive Testing
// Using legacy factory syntax (pre-Laravel 8)
$post = factory(Post::class)->create();

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
        // Using legacy factory syntax (pre-Laravel 8)
        factory(User::class, 10000)->create();
        
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


<!-- Configuration management section removed: Holloway does not currently support a config/holloway.php file or related environment variables. If configuration becomes available in the future, document it here. -->

These best practices provide a foundation for building maintainable, performant, and robust applications with Holloway. Adapt them to your specific use case and requirements.

## Next Steps

- **[Complete Examples](./complete-examples.md)** - Practical implementation examples
