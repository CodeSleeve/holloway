# Events and Observers

Holloway's event system provides a powerful way to decouple your application logic and respond to entity lifecycle events. This enables clean separation of concerns, easier testing, and flexible application architecture.

## Table of Contents

- [Understanding Events](#understanding-events)
- [Entity Lifecycle Events](#entity-lifecycle-events)
- [Creating Event Listeners](#creating-event-listeners)
- [Observer Pattern](#observer-pattern)
- [Custom Events](#custom-events)
- [Event-Driven Architecture](#event-driven-architecture)
- [Performance Considerations](#performance-considerations)
- [Best Practices](#best-practices)

## Understanding Events

Holloway's event system allows you to hook into various points in the entity lifecycle, enabling you to:

- Send notifications when entities are created/updated
- Maintain audit logs
- Clear caches when data changes
- Trigger business logic workflows
- Synchronize with external systems

### Event Flow

```
Entity Operation → Mapper → Event Dispatcher → Listeners → Side Effects
```

## Entity Lifecycle Events

Holloway provides several built-in events that fire during entity operations:

### Available Events

```php
// Before operations (can prevent the operation)
EntityCreating::class    // Before entity is created
EntityUpdating::class    // Before entity is updated
EntityDeleting::class    // Before entity is deleted
EntitySaving::class      // Before entity is saved (create or update)

// After operations (for side effects)
EntityCreated::class     // After entity is created
EntityUpdated::class     // After entity is updated
EntityDeleted::class     // After entity is deleted
EntitySaved::class       // After entity is saved (create or update)

// Special events
EntityRestoring::class   // Before soft deleted entity is restored
EntityRestored::class    // After soft deleted entity is restored
```

### Basic Event Listening

Register event listeners in your mapper:

```php
<?php

class PostMapper extends Mapper
{
    protected function configure(): void
    {
        $this->field('id')->primary();
        $this->field('title');
        $this->field('content');
        $this->field('slug');
        $this->field('published_at');
        
        // Register event listeners
        $this->addEventListener(EntityCreating::class, [$this, 'generateSlug']);
        $this->addEventListener(EntityCreated::class, [$this, 'sendNotification']);
        $this->addEventListener(EntityUpdating::class, [$this, 'updateSlugIfNeeded']);
    }
    
    public function generateSlug(EntityCreating $event): void
    {
        $post = $event->getEntity();
        
        if (empty($post->getSlug())) {
            $post->setSlug(Str::slug($post->getTitle()));
        }
    }
    
    public function sendNotification(EntityCreated $event): void
    {
        $post = $event->getEntity();
        
        if ($post->getPublishedAt()) {
            event(new PostPublished($post));
        }
    }
    
    public function updateSlugIfNeeded(EntityUpdating $event): void
    {
        $post = $event->getEntity();
        
        if ($event->isDirty('title') && !$event->isDirty('slug')) {
            $post->setSlug(Str::slug($post->getTitle()));
        }
    }
}
```

## Creating Event Listeners

### Dedicated Event Listeners

Create focused event listeners for complex logic:

```php
<?php

class PostEventListener
{
    public function __construct(
        private NotificationService $notifications,
        private CacheManager $cache,
        private AuditLogger $auditLogger
    ) {}
    
    public function handleCreated(EntityCreated $event): void
    {
        if (!$event->getEntity() instanceof Post) {
            return;
        }
        
        $post = $event->getEntity();
        
        // Clear relevant caches
        $this->cache->tags(['posts', 'author:' . $post->getAuthorId()])->flush();
        
        // Log creation
        $this->auditLogger->log('post.created', [
            'post_id' => $post->getId(),
            'title' => $post->getTitle(),
            'author_id' => $post->getAuthorId()
        ]);
        
        // Send notifications
        if ($post->isPublished()) {
            $this->notifications->notifySubscribers($post);
        }
    }
    
    public function handleUpdated(EntityUpdated $event): void
    {
        $post = $event->getEntity();
        $changes = $event->getChanges();
        
        // Handle publication
        if (isset($changes['published_at']) && $post->isPublished()) {
            $this->notifications->notifySubscribers($post);
        }
        
        // Clear caches
        $this->cache->forget("post:{$post->getId()}");
        
        // Log significant changes
        if (isset($changes['title']) || isset($changes['content'])) {
            $this->auditLogger->log('post.content_updated', [
                'post_id' => $post->getId(),
                'changes' => $changes
            ]);
        }
    }
    
    public function handleDeleted(EntityDeleted $event): void
    {
        $post = $event->getEntity();
        
        // Clear all related caches
        $this->cache->tags(['posts', 'author:' . $post->getAuthorId()])->flush();
        
        // Archive related data
        $this->archiveComments($post);
        
        // Log deletion
        $this->auditLogger->log('post.deleted', [
            'post_id' => $post->getId(),
            'title' => $post->getTitle()
        ]);
    }
    
    private function archiveComments(Post $post): void
    {
        // Archive comments logic
    }
}
```

### Register Listeners in Service Provider

```php
<?php

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $listener = app(PostEventListener::class);
        
        Event::listen(EntityCreated::class, [$listener, 'handleCreated']);
        Event::listen(EntityUpdated::class, [$listener, 'handleUpdated']);
        Event::listen(EntityDeleted::class, [$listener, 'handleDeleted']);
    }
}
```

## Observer Pattern

Use observers for a more structured approach to handling entity events:

### Creating an Observer

```php
<?php

class PostObserver
{
    public function __construct(
        private SlugGenerator $slugGenerator,
        private NotificationService $notifications,
        private SearchIndexer $searchIndexer
    ) {}
    
    /**
     * Handle the Post "creating" event.
     */
    public function creating(Post $post): void
    {
        // Generate slug if not provided
        if (empty($post->getSlug())) {
            $post->setSlug($this->slugGenerator->generate($post->getTitle()));
        }
        
        // Set default values
        if (!$post->getStatus()) {
            $post->setStatus('draft');
        }
    }
    
    /**
     * Handle the Post "created" event.
     */
    public function created(Post $post): void
    {
        // Index for search
        $this->searchIndexer->index($post);
        
        // Send notifications if published
        if ($post->isPublished()) {
            $this->notifications->notifyAuthorFollowers($post);
        }
    }
    
    /**
     * Handle the Post "updating" event.
     */
    public function updating(Post $post): void
    {
        // Update slug if title changed
        if ($post->isDirty('title') && !$post->isDirty('slug')) {
            $post->setSlug($this->slugGenerator->generate($post->getTitle()));
        }
    }
    
    /**
     * Handle the Post "updated" event.
     */
    public function updated(Post $post): void
    {
        // Update search index
        $this->searchIndexer->update($post);
        
        // Handle publication status change
        if ($post->wasChanged('published_at')) {
            if ($post->isPublished()) {
                $this->notifications->notifySubscribers($post);
            }
        }
    }
    
    /**
     * Handle the Post "deleting" event.
     */
    public function deleting(Post $post): bool
    {
        // Prevent deletion if post has active orders (example business rule)
        if ($post->hasActiveOrders()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Handle the Post "deleted" event.
     */
    public function deleted(Post $post): void
    {
        // Remove from search index
        $this->searchIndexer->remove($post);
        
        // Archive related data
        $this->archiveRelatedData($post);
    }
    
    /**
     * Handle the Post "restored" event.
     */
    public function restored(Post $post): void
    {
        // Re-index for search
        $this->searchIndexer->index($post);
        
        // Restore related data
        $this->restoreRelatedData($post);
    }
    
    private function archiveRelatedData(Post $post): void
    {
        // Implementation for archiving related data
    }
    
    private function restoreRelatedData(Post $post): void
    {
        // Implementation for restoring related data
    }
}
```

### Registering Observers

```php
<?php

class PostMapper extends Mapper
{
    protected function configure(): void
    {
        $this->field('id')->primary();
        $this->field('title');
        $this->field('content');
        $this->field('slug');
        $this->field('status');
        $this->field('published_at');
        
        // Register observer
        $this->observe(PostObserver::class);
    }
}
```

## Custom Events

Create domain-specific events for better semantics:

### Defining Custom Events

```php
<?php

class PostPublished
{
    public function __construct(
        public readonly Post $post,
        public readonly \DateTimeInterface $publishedAt
    ) {}
}

class PostFeatured
{
    public function __construct(
        public readonly Post $post,
        public readonly User $featuredBy
    ) {}
}

class PostViewCountReached
{
    public function __construct(
        public readonly Post $post,
        public readonly int $milestone
    ) {}
}
```

### Dispatching Custom Events

```php
<?php

class PostService
{
    public function publish(Post $post): void
    {
        $post->setPublishedAt(now());
        $post->setStatus('published');
        
        $this->postMapper->save($post);
        
        // Dispatch custom event
        event(new PostPublished($post, $post->getPublishedAt()));
    }
    
    public function feature(Post $post, User $user): void
    {
        $post->setFeatured(true);
        $post->setFeaturedAt(now());
        $post->setFeaturedBy($user->getId());
        
        $this->postMapper->save($post);
        
        event(new PostFeatured($post, $user));
    }
    
    public function incrementViewCount(Post $post): void
    {
        $newCount = $post->incrementViewCount();
        $this->postMapper->save($post);
        
        // Check for milestones
        $milestones = [100, 1000, 10000, 100000];
        
        foreach ($milestones as $milestone) {
            if ($newCount === $milestone) {
                event(new PostViewCountReached($post, $milestone));
                break;
            }
        }
    }
}
```

### Listening to Custom Events

```php
<?php

class PostMetricsListener
{
    public function handlePublished(PostPublished $event): void
    {
        // Update author statistics
        $this->updateAuthorStats($event->post);
        
        // Send to analytics
        $this->analytics->track('post.published', [
            'post_id' => $event->post->getId(),
            'author_id' => $event->post->getAuthorId(),
            'published_at' => $event->publishedAt->toISOString()
        ]);
    }
    
    public function handleFeatured(PostFeatured $event): void
    {
        // Notify author
        $this->notifications->send($event->post->getAuthor(), new PostFeaturedNotification($event->post));
        
        // Update featured posts cache
        $this->cache->forget('featured_posts');
    }
    
    public function handleViewMilestone(PostViewCountReached $event): void
    {
        // Award badges
        $this->badgeService->awardViewMilestone($event->post->getAuthor(), $event->milestone);
        
        // Send congratulations
        $this->notifications->send(
            $event->post->getAuthor(),
            new ViewMilestoneNotification($event->post, $event->milestone)
        );
    }
    
    private function updateAuthorStats(Post $post): void
    {
        // Update author's published post count
        $author = $post->getAuthor();
        $author->incrementPublishedPostCount();
        $this->userMapper->save($author);
    }
}
```

## Event-Driven Architecture

### Saga Pattern Implementation

```php
<?php

class OrderProcessingSaga
{
    public function __construct(
        private OrderMapper $orderMapper,
        private InventoryService $inventory,
        private PaymentService $payment,
        private ShippingService $shipping
    ) {}
    
    public function handleOrderCreated(OrderCreated $event): void
    {
        $order = $event->order;
        
        try {
            // Step 1: Reserve inventory
            $this->inventory->reserve($order);
            $order->setStatus('inventory_reserved');
            $this->orderMapper->save($order);
            
            event(new OrderInventoryReserved($order));
            
        } catch (InsufficientInventoryException $e) {
            $order->setStatus('failed_inventory');
            $this->orderMapper->save($order);
            
            event(new OrderProcessingFailed($order, 'insufficient_inventory'));
        }
    }
    
    public function handleInventoryReserved(OrderInventoryReserved $event): void
    {
        $order = $event->order;
        
        try {
            // Step 2: Process payment
            $this->payment->charge($order);
            $order->setStatus('payment_processed');
            $this->orderMapper->save($order);
            
            event(new OrderPaymentProcessed($order));
            
        } catch (PaymentFailedException $e) {
            // Compensate: Release reserved inventory
            $this->inventory->release($order);
            
            $order->setStatus('failed_payment');
            $this->orderMapper->save($order);
            
            event(new OrderProcessingFailed($order, 'payment_failed'));
        }
    }
    
    public function handlePaymentProcessed(OrderPaymentProcessed $event): void
    {
        $order = $event->order;
        
        try {
            // Step 3: Arrange shipping
            $this->shipping->schedule($order);
            $order->setStatus('shipped');
            $this->orderMapper->save($order);
            
            event(new OrderShipped($order));
            
        } catch (ShippingException $e) {
            // Compensate: Refund payment and release inventory
            $this->payment->refund($order);
            $this->inventory->release($order);
            
            $order->setStatus('failed_shipping');
            $this->orderMapper->save($order);
            
            event(new OrderProcessingFailed($order, 'shipping_failed'));
        }
    }
}
```

## Performance Considerations

### Asynchronous Event Processing

```php
<?php

class AsyncEventListener
{
    public function __construct(private QueueManager $queue) {}
    
    public function handleEntityCreated(EntityCreated $event): void
    {
        // Process immediately for critical operations
        $this->updateCache($event);
        
        // Queue time-consuming operations
        $this->queue->push(new SendNotificationsJob($event));
        $this->queue->push(new UpdateSearchIndexJob($event));
        $this->queue->push(new UpdateAnalyticsJob($event));
    }
    
    private function updateCache(EntityCreated $event): void
    {
        // Fast cache update
        Cache::forget("entity:{$event->getEntity()->getId()}");
    }
}
```

### Event Filtering

```php
<?php

class ConditionalEventListener
{
    public function handleEntityUpdated(EntityUpdated $event): void
    {
        $entity = $event->getEntity();
        $changes = $event->getChanges();
        
        // Only process significant changes
        $significantFields = ['title', 'content', 'status', 'published_at'];
        
        if (!array_intersect(array_keys($changes), $significantFields)) {
            return; // Skip processing for minor changes
        }
        
        $this->processSignificantUpdate($entity, $changes);
    }
    
    private function processSignificantUpdate($entity, array $changes): void
    {
        // Process only significant updates
    }
}
```

## Best Practices

### 1. Keep Event Listeners Focused

```php
// Good: Single responsibility
class PostCacheListener
{
    public function handleUpdated(EntityUpdated $event): void
    {
        if ($event->getEntity() instanceof Post) {
            $this->clearPostCaches($event->getEntity());
        }
    }
}

// Good: Separate concerns
class PostNotificationListener
{
    public function handleCreated(EntityCreated $event): void
    {
        if ($event->getEntity() instanceof Post) {
            $this->sendNotifications($event->getEntity());
        }
    }
}
```

### 2. Use Dependency Injection

```php
class PostEventListener
{
    public function __construct(
        private NotificationService $notifications,
        private CacheManager $cache,
        private Logger $logger
    ) {}
    
    // Event handling methods...
}
```

### 3. Handle Failures Gracefully

```php
class RobustEventListener
{
    public function handleEntityCreated(EntityCreated $event): void
    {
        try {
            $this->updateSearchIndex($event->getEntity());
        } catch (SearchIndexException $e) {
            // Log error but don't break the main flow
            $this->logger->error('Failed to update search index', [
                'entity_id' => $event->getEntity()->getId(),
                'error' => $e->getMessage()
            ]);
            
            // Queue for retry
            $this->queue->push(new RetrySearchIndexJob($event->getEntity()));
        }
    }
}
```

### 4. Test Event Listeners

```php
class PostEventListenerTest extends TestCase
{
    public function test_created_event_sends_notification(): void
    {
        $notification = Mockery::mock(NotificationService::class);
        $notification->shouldReceive('notifySubscribers')->once();
        
        $listener = new PostEventListener($notification, $this->cache, $this->logger);
        
        $post = new Post();
        $post->setPublished(true);
        
        $event = new EntityCreated($post);
        $listener->handleCreated($event);
    }
}
```

### 5. Document Event Contracts

```php
/**
 * Fired when a post is published for the first time
 * 
 * Listeners should handle:
 * - Notifying subscribers
 * - Updating search index
 * - Recording analytics
 * - Awarding author badges
 */
class PostPublished
{
    public function __construct(
        public readonly Post $post,
        public readonly \DateTimeInterface $publishedAt
    ) {}
}
```

Events and observers provide a powerful way to build loosely coupled, maintainable applications. By leveraging Holloway's event system, you can create clean, testable code that responds appropriately to changes in your domain entities.
