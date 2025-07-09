# Laravel Integration

Holloway provides seamless integration with Laravel, offering powerful datamapper functionality while leveraging Laravel's ecosystem. This guide covers installation, configuration, and usage patterns specific to Laravel applications.

## Installation

### Composer Installation

```bash
composer require holloway/datamapper
```

### Service Provider Registration

Add the service provider to your `config/app.php`:

```php
'providers' => [
    // Other service providers...
    Holloway\HollowayServiceProvider::class,
],
```

Or if using Laravel 5.5+, the service provider will be auto-discovered.

### Configuration Publishing

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Holloway\HollowayServiceProvider"
```

This creates `config/holloway.php` with default settings.

## Configuration

### Database Configuration

Holloway uses Laravel's database configuration by default:

```php
// config/holloway.php
return [
    'default_connection' => env('DB_CONNECTION', 'mysql'),
    
    'connections' => [
        // Uses Laravel's database.connections config by default
        // Override specific connections if needed
        'analytics' => [
            'driver' => 'mysql',
            'host' => env('ANALYTICS_DB_HOST', '127.0.0.1'),
            'database' => env('ANALYTICS_DB_DATABASE', 'analytics'),
            // ... other settings
        ],
    ],
    
    'cache' => [
        'store' => env('HOLLOWAY_CACHE_STORE', 'redis'),
        'prefix' => env('HOLLOWAY_CACHE_PREFIX', 'holloway:'),
        'ttl' => env('HOLLOWAY_CACHE_TTL', 3600),
    ],
];
```

### Environment Variables

```env
# .env file
HOLLOWAY_CACHE_ENABLED=true
HOLLOWAY_CACHE_STORE=redis
HOLLOWAY_CACHE_TTL=3600
HOLLOWAY_LOG_QUERIES=true
HOLLOWAY_CHUNK_SIZE=1000
```

## Service Provider Features

### Automatic Mapper Registration

The service provider automatically discovers and registers mappers:

```php
// Mappers are automatically bound to the container
class PostController extends Controller
{
    public function __construct(private PostMapper $posts) {}
    
    public function index()
    {
        return $this->posts->findPublished();
    }
}
```

### Repository Binding

Configure repository interfaces in the service provider:

```php
// In AppServiceProvider or a custom provider
public function register()
{
    $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    $this->app->bind(PostRepositoryInterface::class, PostRepository::class);
}
```

### Command Registration

Holloway provides helpful Artisan commands:

```bash
# Generate mapper
php artisan make:mapper PostMapper

# Generate entity  
php artisan make:entity Post

# Generate repository
php artisan make:repository PostRepository

# Clear mapper cache
php artisan holloway:cache:clear

# Show mapper statistics
php artisan holloway:stats
```

## Database Connections

### Multiple Connections

Configure mappers to use different database connections:

```php
class UserMapper extends Mapper
{
    protected string $connection = 'mysql';
    protected string $table = 'users';
}

class AnalyticsMapper extends Mapper
{
    protected string $connection = 'analytics';
    protected string $table = 'events';
}
```

### Read/Write Splitting

Configure read/write database splitting:

```php
// config/database.php
'mysql' => [
    'read' => [
        'host' => ['read-host-1', 'read-host-2'],
    ],
    'write' => [
        'host' => ['write-host'],
    ],
    'driver' => 'mysql',
    // ... other settings
],
```

Mappers automatically use read connections for queries and write connections for modifications.

## Caching Integration

### Laravel Cache Integration

Holloway integrates with Laravel's cache system:

```php
class PostMapper extends Mapper
{
    public function findPopular(): Collection
    {
        return cache()->remember('posts.popular', 3600, function() {
            return $this->query()
                ->where('view_count', '>', 1000)
                ->orderBy('view_count', 'desc')
                ->get();
        });
    }

    public function save(Post $post): void
    {
        parent::save($post);
        
        // Clear related cache
        cache()->forget('posts.popular');
        cache()->tags(['posts'])->flush();
    }
}
```

### Cache Tags Support

Use Laravel's cache tags for granular cache management:

```php
class PostMapper extends Mapper
{
    public function findByCategory(int $categoryId): Collection
    {
        return cache()->tags(['posts', "category.{$categoryId}"])
            ->remember("posts.category.{$categoryId}", 1800, function() use ($categoryId) {
                return $this->query()
                    ->where('category_id', $categoryId)
                    ->where('published', true)
                    ->get();
            });
    }

    public function save(Post $post): void
    {
        parent::save($post);
        
        // Clear category-specific cache
        cache()->tags(["category.{$post->getCategoryId()}"])->flush();
    }
}
```

## Pagination Integration

### Laravel Pagination

Holloway works seamlessly with Laravel's pagination:

```php
class PostMapper extends Mapper
{
    public function paginatePublished(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->where('published', true)
            ->orderBy('published_at', 'desc')
            ->paginate($perPage);
    }

    public function simplePaginatePublished(int $perPage = 15): Paginator
    {
        return $this->query()
            ->where('published', true)
            ->orderBy('published_at', 'desc')
            ->simplePaginate($perPage);
    }
}
```

### Custom Pagination

Create custom pagination logic:

```php
class PostController extends Controller
{
    public function index(Request $request, PostMapper $posts)
    {
        $page = $request->get('page', 1);
        $perPage = 10;
        
        $results = $posts->query()
            ->where('published', true)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();
            
        $total = $posts->query()->where('published', true)->count();
        
        return new LengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $page,
            ['path' => $request->url()]
        );
    }
}
```

## Event Integration

### Laravel Events

Integrate with Laravel's event system:

```php
class PostMapper extends Mapper
{
    public function save(Post $post): void
    {
        $isNew = !$post->getId();
        
        parent::save($post);
        
        if ($isNew) {
            event(new PostCreated($post));
        } else {
            event(new PostUpdated($post));
        }
    }

    public function delete(Post $post): void
    {
        parent::delete($post);
        event(new PostDeleted($post));
    }
}

// Event classes
class PostCreated
{
    public function __construct(public Post $post) {}
}

class PostUpdated  
{
    public function __construct(public Post $post) {}
}
```

### Event Listeners

Create event listeners:

```php
class SendPostNotification
{
    public function handle(PostCreated $event): void
    {
        $post = $event->post;
        
        // Send notifications to subscribers
        Notification::send(
            $post->getAuthor()->getFollowers(),
            new NewPostNotification($post)
        );
    }
}

// Register in EventServiceProvider
protected $listen = [
    PostCreated::class => [
        SendPostNotification::class,
        UpdateSearchIndex::class,
    ],
];
```

## Queue Integration

### Queued Operations

Use Laravel queues for heavy operations:

```php
class PostMapper extends Mapper
{
    public function publishPost(Post $post): void
    {
        $post->publish();
        $this->store($post);
        
        // Queue heavy operations
        ProcessPostImages::dispatch($post);
        GeneratePostExcerpt::dispatch($post);
        NotifySubscribers::dispatch($post);
    }
}

// Queue jobs
class ProcessPostImages implements ShouldQueue
{
    public function __construct(private Post $post) {}
    
    public function handle(ImageProcessor $processor): void
    {
        $processor->generateThumbnails($this->post);
        $processor->optimizeImages($this->post);
    }
}
```

## Validation Integration

### Laravel Validation

Integrate with Laravel's validator:

```php
class PostMapper extends Mapper
{
    public function createPost(array $data): Post
    {
        $validated = validator($data, [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'tags' => 'array|max:10',
            'tags.*' => 'string|max:50',
        ])->validate();

        $post = new Post(
            $validated['title'],
            $validated['content']
        );

        if (isset($validated['category_id'])) {
            $category = app(CategoryMapper::class)->find($validated['category_id']);
            $post->setCategory($category);
        }

        return $this->store($post);
    }
}
```

### Form Requests

Use form requests for complex validation:

```php
class CreatePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:100',
            'category_id' => 'required|exists:categories,id',
            'featured_image' => 'nullable|image|max:2048',
            'tags' => 'array|max:10',
            'schedule_at' => 'nullable|date|after:now',
        ];
    }

    public function toEntity(): Post
    {
        return new Post(
            $this->validated('title'),
            $this->validated('content')
        );
    }
}

class PostController extends Controller
{
    public function store(CreatePostRequest $request, PostMapper $posts)
    {
        $post = $request->toEntity();
        
        // Set additional properties
        $post->setAuthor(auth()->user());
        
        if ($request->has('schedule_at')) {
            $post->scheduleFor($request->date('schedule_at'));
        }
        
        return $posts->store($post);
    }
}
```

## Middleware Integration

### Authentication Middleware

Integrate with Laravel's authentication:

```php
class PostController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['index', 'show']);
        $this->middleware('verified')->only(['store', 'update']);
    }
    
    public function store(Request $request, PostMapper $posts)
    {
        $post = new Post($request->title, $request->content);
        $post->setAuthor(auth()->user());
        
        return $posts->store($post);
    }
}
```

### Custom Middleware

Create custom middleware for Holloway operations:

```php
class SetTenantScope
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            $tenantId = auth()->user()->tenant_id;
            
            // Apply tenant scope to all mappers
            app(PostMapper::class)->addGlobalScope('tenant', new TenantScope($tenantId));
            app(UserMapper::class)->addGlobalScope('tenant', new TenantScope($tenantId));
        }
        
        return $next($request);
    }
}
```

## Testing Integration

### Laravel Testing Tools

Use Laravel's testing features with Holloway:

```php
class PostTest extends TestCase
{
    use RefreshDatabase;

    public function testCreatePost(): void
    {
        // Given
        $user = factory(User::class)->create();
        $this->actingAs($user);

        // When
        $response = $this->post('/posts', [
            'title' => 'Test Post',
            'content' => 'This is test content',
        ]);

        // Then
        $response->assertCreated();
        
        $post = app(PostMapper::class)->findByTitle('Test Post');
        $this->assertNotNull($post);
        $this->assertEquals($user->id, $post->getAuthor()->getId());
    }

    public function testPostPagination(): void
    {
        // Given
        // Using legacy factory syntax (pre-Laravel 8)
        factory(Post::class, 25)->create();

        // When
        $response = $this->get('/posts?page=2');

        // Then
        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'current_page',
            'total',
            'per_page'
        ]);
    }
}
```

### Database Factories

Integrate with Laravel factories:

```php
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence,
            'content' => $this->faker->paragraphs(3, true),
            'published' => true,
            'published_at' => now(),
            'author_id' => factory(User::class)->create()->id,
            'category_id' => factory(Category::class)->create()->id,
        ];
    }

    public function draft(): self
    {
        return $this->state([
            'published' => false,
            'published_at' => null,
        ]);
    }

    public function withComments(int $count = 3): self
    {
        return $this->afterCreating(function(Post $post) use ($count) {
            factory(Comment::class, $count)->create(['post_id' => $post->id]);
        });
    }
}
```

## Next Steps

- **[Service Provider Configuration](./service-provider.md)** - Detailed service provider setup
- **[Database Connections](./connections.md)** - Advanced connection management  
- **[Pagination Strategies](./pagination.md)** - Pagination patterns and optimization
- **[Artisan Commands](./commands.md)** - Custom command development
