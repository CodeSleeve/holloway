# Complete Examples

This guide provides comprehensive, real-world examples of Holloway datamapper implementations. Each example demonstrates best practices, proper architecture, and practical patterns you can adapt for your own projects.

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

    public function hydrate($record, $relations = null)
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

    public function hydrate($record, $relations = null)
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

### Services

```php
<?php

// Blog Service - Orchestrates business operations
class BlogService
{
    private UserMapper $userMapper;
    private PostMapper $postMapper;
    private CategoryMapper $categoryMapper;

    public function __construct()
    {
        $this->userMapper = Holloway::instance()->getMapper(User::class);
        $this->postMapper = Holloway::instance()->getMapper(Post::class);
        $this->categoryMapper = Holloway::instance()->getMapper(Category::class);
    }

    public function createPost(int $authorId, string $title, string $content, ?int $categoryId = null): Post
    {
        // Validate author can create posts
        $author = $this->userMapper->findOrFail($authorId);
        
        if (!$author->canPublishPosts()) {
            throw new UnauthorizedException('User cannot create posts');
        }

        // Create post
        $post = new Post($title, $content, $authorId);
        
        // Assign category if provided
        if ($categoryId) {
            $category = $this->categoryMapper->findOrFail($categoryId);
            $post->assignToCategory($category);
        }
        
        // Store post
        $this->postMapper->store($post);
        
        return $post;
    }

    public function publishPost(int $postId): Post
    {
        $post = $this->postMapper->with('author')->findOrFail($postId);
        
        if (!$post->author->canPublishPosts()) {
            throw new UnauthorizedException('Author cannot publish posts');
        }
        
        $post->publish();
        $this->postMapper->store($post);
        
        return $post;
    }

    public function getPublishedPosts(int $page = 1, int $perPage = 10): Collection
    {
        return $this->postMapper
            ->published()
            ->with(['author.profile', 'category', 'tags'])
            ->orderBy('published_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getPostsByAuthor(int $authorId, bool $publishedOnly = true): Collection
    {
        $query = $this->postMapper->where('author_id', $authorId);
        
        if ($publishedOnly) {
            $query->published();
        }
        
        return $query->with(['category', 'tags'])
                    ->orderBy('created_at', 'desc')
                    ->get();
    }

    public function incrementPostViews(int $postId): void
    {
        $post = $this->postMapper->findOrFail($postId);
        $post->incrementViewCount();
        $this->postMapper->store($post);
    }

    public function getPopularPosts(int $limit = 10): Collection
    {
        return $this->postMapper
            ->published()
            ->popular()
            ->with(['author.profile', 'category'])
            ->orderBy('view_count', 'desc')
            ->limit($limit)
            ->get();
    }
}

// User Service
class UserService
{
    private UserMapper $userMapper;

    public function __construct()
    {
        $this->userMapper = Holloway::instance()->getMapper(User::class);
    }

    public function registerUser(string $name, string $email, string $password): User
    {
        // Check if email already exists
        if ($this->userMapper->where('email', $email)->exists()) {
            throw new ValidationException('Email already registered');
        }

        // Create user
        $user = new User($name, new Email($email));
        
        // Store user
        $this->userMapper->store($user);
        
        // Send verification email (would be handled by event listener)
        event(new UserRegistered($user));
        
        return $user;
    }

    public function verifyUserEmail(int $userId): void
    {
        $user = $this->userMapper->findOrFail($userId);
        $user->verifyEmail();
        $this->userMapper->store($user);
        
        event(new UserEmailVerified($user));
    }

    public function promoteUserToAuthor(int $userId): void
    {
        $user = $this->userMapper->findOrFail($userId);
        
        if (!$user->getEmailVerifiedAt()) {
            throw new ValidationException('Cannot promote unverified user');
        }
        
        $user->promoteToAuthor();
        $this->userMapper->store($user);
    }

    public function getUserDashboard(int $userId): array
    {
        $user = $this->userMapper
            ->with(['publishedPosts', 'comments', 'profile'])
            ->findOrFail($userId);
        
        return [
            'user' => $user,
            'stats' => [
                'published_posts' => $user->publishedPosts->count(),
                'total_comments' => $user->comments->count(),
                'total_views' => $user->publishedPosts->sum('view_count'),
            ]
        ];
    }
}
```

### Controllers (Laravel)

```php
<?php

// Blog Controller
class BlogController extends Controller
{
    private BlogService $blogService;

    public function __construct(BlogService $blogService)
    {
        $this->blogService = $blogService;
    }

    public function index(Request $request)
    {
        $page = $request->get('page', 1);
        $posts = $this->blogService->getPublishedPosts($page, 12);
        
        return view('blog.index', compact('posts'));
    }

    public function show($slug)
    {
        $postMapper = Holloway::instance()->getMapper(Post::class);
        
        $post = $postMapper
            ->with(['author.profile', 'category', 'approvedComments.author'])
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();
        
        // Increment view count
        $this->blogService->incrementPostViews($post->getId());
        
        return view('blog.show', compact('post'));
    }

    public function store(CreatePostRequest $request)
    {
        $post = $this->blogService->createPost(
            auth()->id(),
            $request->title,
            $request->content,
            $request->category_id
        );
        
        return redirect()
            ->route('posts.show', $post->getSlug())
            ->with('success', 'Post created successfully');
    }

    public function publish($id)
    {
        $post = $this->blogService->publishPost($id);
        
        return redirect()
            ->route('posts.show', $post->getSlug())
            ->with('success', 'Post published successfully');
    }
}

// User Dashboard Controller  
class DashboardController extends Controller
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function show()
    {
        $dashboard = $this->userService->getUserDashboard(auth()->id());
        
        return view('dashboard.show', $dashboard);
    }
}
```

## Example 2: E-commerce Order Management

A comprehensive e-commerce order system with products, customers, and complex business rules.

### Core Entities

```php
<?php

// Order Entity
class Order
{
    private ?int $id = null;
    private string $orderNumber;
    private int $customerId;
    private OrderStatus $status;
    private Money $subtotal;
    private Money $taxAmount;
    private Money $shippingAmount;
    private Money $totalAmount;
    private DateTime $createdAt;
    private ?DateTime $shippedAt = null;
    private Collection $items;
    private ?Customer $customer = null;
    private ?ShippingAddress $shippingAddress = null;
    private ?Payment $payment = null;

    public function __construct(int $customerId)
    {
        $this->orderNumber = $this->generateOrderNumber();
        $this->customerId = $customerId;
        $this->status = OrderStatus::Pending;
        $this->subtotal = Money::zero();
        $this->taxAmount = Money::zero();
        $this->shippingAmount = Money::zero();
        $this->totalAmount = Money::zero();
        $this->createdAt = new DateTime();
        $this->items = new Collection();
    }

    public function addItem(Product $product, int $quantity, Money $unitPrice): void
    {
        if ($this->status !== OrderStatus::Pending) {
            throw new InvalidOperationException('Cannot modify confirmed order');
        }
        
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }
        
        $item = new OrderItem($product, $quantity, $unitPrice);
        $this->items->push($item);
        $this->recalculateAmounts();
    }

    public function removeItem(int $itemIndex): void
    {
        if ($this->status !== OrderStatus::Pending) {
            throw new InvalidOperationException('Cannot modify confirmed order');
        }
        
        $this->items->forget($itemIndex);
        $this->recalculateAmounts();
    }

    public function confirm(): void
    {
        if ($this->items->isEmpty()) {
            throw new InvalidOperationException('Cannot confirm empty order');
        }
        
        if (!$this->shippingAddress) {
            throw new InvalidOperationException('Shipping address required');
        }
        
        $this->status = OrderStatus::Confirmed;
        $this->recalculateAmounts();
    }

    public function ship(): void
    {
        if ($this->status !== OrderStatus::Confirmed) {
            throw new InvalidOperationException('Can only ship confirmed orders');
        }
        
        $this->status = OrderStatus::Shipped;
        $this->shippedAt = new DateTime();
    }

    public function cancel(): void
    {
        if (in_array($this->status, [OrderStatus::Shipped, OrderStatus::Delivered])) {
            throw new InvalidOperationException('Cannot cancel shipped/delivered order');
        }
        
        $this->status = OrderStatus::Cancelled;
    }

    private function recalculateAmounts(): void
    {
        $this->subtotal = $this->items->reduce(
            fn(Money $total, OrderItem $item) => $total->add($item->getTotal()),
            Money::zero()
        );
        
        $this->taxAmount = $this->subtotal->multiply(0.08); // 8% tax
        $this->shippingAmount = $this->calculateShipping();
        $this->totalAmount = $this->subtotal
            ->add($this->taxAmount)
            ->add($this->shippingAmount);
    }

    private function calculateShipping(): Money
    {
        if ($this->subtotal->greaterThanOrEqual(Money::fromString('100.00'))) {
            return Money::zero(); // Free shipping over $100
        }
        
        return Money::fromString('9.99');
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-' . strtoupper(uniqid());
    }

    // Getters and setters...
}

// OrderItem Entity
class OrderItem
{
    private Product $product;
    private int $quantity;
    private Money $unitPrice;

    public function __construct(Product $product, int $quantity, Money $unitPrice)
    {
        $this->product = $product;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
    }

    public function getTotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }

    public function getProduct(): Product { return $this->product; }
    public function getQuantity(): int { return $this->quantity; }
    public function getUnitPrice(): Money { return $this->unitPrice; }
}

// Money Value Object
class Money
{
    private int $amount; // Store as cents to avoid float precision issues
    private string $currency;

    private function __construct(int $amount, string $currency = 'USD')
    {
        $this->amount = $amount;
        $this->currency = $currency;
    }

    public static function fromString(string $amount, string $currency = 'USD'): self
    {
        return new self((int) round(floatval($amount) * 100), $currency);
    }

    public static function zero(string $currency = 'USD'): self
    {
        return new self(0, $currency);
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function multiply(float $multiplier): self
    {
        return new self((int) round($this->amount * $multiplier), $this->currency);
    }

    public function greaterThanOrEqual(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount >= $other->amount;
    }

    public function toFloat(): float
    {
        return $this->amount / 100.0;
    }

    public function toString(): string
    {
        return number_format($this->toFloat(), 2);
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch');
        }
    }
}

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
```

### Order Mapper

```php
<?php

class OrderMapper extends Mapper
{
    protected string $table = 'orders';
    protected string $entityClassName = Order::class;
    protected bool $hasTimestamps = true;

    public function defineRelations(): void
    {
        $this->belongsTo('customer', Customer::class);
        $this->hasMany('items', OrderItem::class);
        $this->hasOne('payment', Payment::class);
        $this->hasOne('shippingAddress', ShippingAddress::class);
        
        // Custom relationship for order timeline
        $this->customMany('timeline', function($query, $orders) {
            return $query->from('order_events')
                ->whereIn('order_id', $orders->pluck('id'))
                ->orderBy('created_at', 'asc')
                ->get();
        }, function($order, $event) {
            return $order->id == $event->order_id;
        }, OrderEvent::class);
    }

    public function dehydrate($entity): array
    {
        return [
            'id' => $entity->getId(),
            'order_number' => $entity->getOrderNumber(),
            'customer_id' => $entity->getCustomerId(),
            'status' => $entity->getStatus()->value,
            'subtotal' => $entity->getSubtotal()->toFloat(),
            'tax_amount' => $entity->getTaxAmount()->toFloat(),
            'shipping_amount' => $entity->getShippingAmount()->toFloat(),
            'total_amount' => $entity->getTotalAmount()->toFloat(),
            'shipped_at' => $entity->getShippedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function hydrate($record, $relations = null)
    {
        $order = new Order($record->customer_id);
        
        if (isset($record->id)) {
            $order->setId($record->id);
        }
        
        $order->setOrderNumber($record->order_number);
        $order->setStatus(OrderStatus::from($record->status));
        $order->setSubtotal(Money::fromString($record->subtotal));
        $order->setTaxAmount(Money::fromString($record->tax_amount));
        $order->setShippingAmount(Money::fromString($record->shipping_amount));
        $order->setTotalAmount(Money::fromString($record->total_amount));
        
        if ($record->shipped_at) {
            $order->setShippedAt(new DateTime($record->shipped_at));
        }
        
        if (isset($record->created_at)) {
            $order->setCreatedAt(new DateTime($record->created_at));
        }
        
        // Load relationships
        if ($relations) {
            if (isset($relations['customer'])) {
                $order->setCustomer($relations['customer']);
            }
            
            if (isset($relations['items'])) {
                $order->setItems($relations['items']);
            }
            
            if (isset($relations['payment'])) {
                $order->setPayment($relations['payment']);
            }
            
            if (isset($relations['shippingAddress'])) {
                $order->setShippingAddress($relations['shippingAddress']);
            }
        }
        
        return $order;
    }

    // Query scopes
    public function scopeByStatus($query, OrderStatus $status)
    {
        return $query->where('status', $status->value);
    }

    public function scopePending($query)
    {
        return $query->where('status', OrderStatus::Pending->value);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', OrderStatus::Confirmed->value);
    }

    public function scopeShipped($query)
    {
        return $query->where('status', OrderStatus::Shipped->value);
    }

    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByDateRange($query, DateTime $start, DateTime $end)
    {
        return $query->whereBetween('created_at', [
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        ]);
    }
}
```

### Order Service

```php
<?php

class OrderService
{
    private OrderMapper $orderMapper;
    private ProductMapper $productMapper;
    private CustomerMapper $customerMapper;

    public function __construct()
    {
        $this->orderMapper = Holloway::instance()->getMapper(Order::class);
        $this->productMapper = Holloway::instance()->getMapper(Product::class);
        $this->customerMapper = Holloway::instance()->getMapper(Customer::class);
    }

    public function createOrder(int $customerId): Order
    {
        // Validate customer exists
        $this->customerMapper->findOrFail($customerId);
        
        $order = new Order($customerId);
        $this->orderMapper->store($order);
        
        return $order;
    }

    public function addItemToOrder(int $orderId, int $productId, int $quantity): Order
    {
        $order = $this->orderMapper->with('items')->findOrFail($orderId);
        $product = $this->productMapper->findOrFail($productId);
        
        // Check product availability
        if (!$product->isAvailable()) {
            throw new ValidationException('Product is not available');
        }
        
        if ($product->getStockQuantity() < $quantity) {
            throw new ValidationException('Insufficient stock');
        }
        
        $order->addItem($product, $quantity, $product->getPrice());
        $this->orderMapper->store($order);
        
        return $order;
    }

    public function confirmOrder(int $orderId, array $shippingAddress): Order
    {
        $order = $this->orderMapper->with(['items', 'customer'])->findOrFail($orderId);
        
        // Validate shipping address
        $address = new ShippingAddress(
            $shippingAddress['street'],
            $shippingAddress['city'],
            $shippingAddress['state'],
            $shippingAddress['zip'],
            $shippingAddress['country']
        );
        
        $order->setShippingAddress($address);
        $order->confirm();
        
        $this->orderMapper->store($order);
        
        // Reserve inventory
        $this->reserveInventory($order);
        
        // Send confirmation email
        event(new OrderConfirmed($order));
        
        return $order;
    }

    public function processPayment(int $orderId, array $paymentData): Order
    {
        $order = $this->orderMapper->with(['customer', 'items'])->findOrFail($orderId);
        
        if ($order->getStatus() !== OrderStatus::Confirmed) {
            throw new InvalidOperationException('Order must be confirmed before payment');
        }
        
        // Process payment (would integrate with payment gateway)
        $payment = $this->processPaymentGateway($order, $paymentData);
        
        if ($payment->isSuccessful()) {
            $order->setPayment($payment);
            $this->orderMapper->store($order);
            
            event(new PaymentProcessed($order, $payment));
        }
        
        return $order;
    }

    public function shipOrder(int $orderId, string $trackingNumber): Order
    {
        $order = $this->orderMapper->with('items.product')->findOrFail($orderId);
        
        $order->ship();
        $order->setTrackingNumber($trackingNumber);
        
        $this->orderMapper->store($order);
        
        // Update inventory
        $this->updateInventoryForShipment($order);
        
        // Send shipping notification
        event(new OrderShipped($order));
        
        return $order;
    }

    public function getOrderSummary(int $orderId): array
    {
        $order = $this->orderMapper
            ->with(['customer.profile', 'items.product', 'payment', 'shippingAddress'])
            ->findOrFail($orderId);
        
        return [
            'order' => $order,
            'summary' => [
                'item_count' => $order->getItems()->count(),
                'total_quantity' => $order->getItems()->sum('quantity'),
                'subtotal' => $order->getSubtotal(),
                'tax' => $order->getTaxAmount(),
                'shipping' => $order->getShippingAmount(),
                'total' => $order->getTotalAmount(),
            ]
        ];
    }

    private function reserveInventory(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            $product->reserveStock($item->getQuantity());
            $this->productMapper->store($product);
        }
    }

    private function updateInventoryForShipment(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            $product->reduceStock($item->getQuantity());
            $this->productMapper->store($product);
        }
    }

    private function processPaymentGateway(Order $order, array $paymentData): Payment
    {
        // Integration with payment gateway would go here
        // For example purposes, returning a successful payment
        return new Payment(
            $order->getTotalAmount(),
            PaymentMethod::CreditCard,
            PaymentStatus::Completed
        );
    }
}
```

These complete examples demonstrate:

1. **Rich Domain Models** - Entities with business logic and validation
2. **Proper Separation** - Clear boundaries between entities, mappers, and services
3. **Value Objects** - Using Email, Money, and other value objects for type safety
4. **Custom Relationships** - Complex data loading patterns
5. **Service Layer** - Orchestrating business operations
6. **Event Integration** - Domain events for side effects
7. **Error Handling** - Proper exception handling and validation
8. **Testing Strategies** - Separating domain logic from persistence logic

These patterns provide a solid foundation for building maintainable, testable applications with Holloway's datamapper architecture.

## Next Steps

- **[Migration Guide](./migration-guide.md)** - Moving from Eloquent to Holloway
- **[Best Practices](./best-practices.md)** - Advanced patterns and optimizations
- **[Testing Guide](../advanced/factories.md)** - Comprehensive testing strategies
