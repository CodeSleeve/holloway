# Events

Holloway fires string-based events at key points in the entity persistence lifecycle. You can listen to these events to send notifications, clear caches, maintain audit logs, or trigger downstream workflows.

## Table of Contents

- [Persistence Event Names](#persistence-event-names)
- [Registering Listeners](#registering-listeners)
- [Preventing Operations](#preventing-operations)
- [Soft Delete Events](#soft-delete-events)
- [Custom Domain Events](#custom-domain-events)
- [Event-Driven Architecture](#event-driven-architecture)
- [Best Practices](#best-practices)

## Persistence Event Names

Events are dispatched as strings in the format `"eventName: FullEntityClassName"`. The following events fire during `store()` and `remove()` operations:

| Event | When |
|-------|------|
| `storing` | Before create or update |
| `creating` | Before a new entity is inserted |
| `created` | After a new entity is inserted |
| `updating` | Before an existing entity is updated |
| `updated` | After an existing entity is updated |
| `stored` | After create or update completes |
| `removing` | Before an entity is removed |
| `removed` | After an entity is removed |

For mappers using the `SoftDeletes` trait, two additional events fire during `restore()`:

| Event | When |
|-------|------|
| `restoring` | Before a soft-deleted entity is restored |
| `restored` | After a soft-deleted entity is restored |

## Registering Listeners

Use `registerPersistenceEvent()` on the mapper to register a listener for a named event. The callback receives the entity as its only argument.

```php
class PostMapper extends Mapper
{
    public function __construct()
    {
        parent::__construct();

        $this->registerPersistenceEvent('created', function(Post $post) {
            // Runs after a new post is inserted
            Cache::tags(['posts'])->flush();
        });

        $this->registerPersistenceEvent('updated', function(Post $post) {
            Cache::forget("post:{$post->getId()}");
        });

        $this->registerPersistenceEvent('removed', function(Post $post) {
            Cache::tags(['posts', "author:{$post->getAuthorId()}"])->flush();
        });
    }
}
```

You can also register listeners from a service provider or anywhere after the mapper is resolved from the container:

```php
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $postMapper = app(PostMapper::class);

        $postMapper->registerPersistenceEvent('created', function(Post $post) {
            app(AuditLogger::class)->log('post.created', ['id' => $post->getId()]);
        });
    }
}
```

## Preventing Operations

Return `false` from a `storing`, `creating`, `updating`, or `removing` listener to abort the operation. The mapper method will return `false`.

```php
$this->registerPersistenceEvent('removing', function(Post $post) {
    if ($post->hasActiveOrders()) {
        return false; // Prevents the remove
    }
});

// In calling code
if (!$postMapper->remove($post)) {
    // Removal was prevented
}
```

Returning `false` from `stored`, `created`, `updated`, or `removed` has no effect — the operation has already completed.

## Soft Delete Events

Mappers using `SoftDeletes` fire `restoring` and `restored` around calls to `restore()`. You can cancel a restore by returning `false` from a `restoring` listener.

```php
$this->registerPersistenceEvent('restoring', function(Post $post) {
    if (!$post->isEligibleForRestore()) {
        return false;
    }
});

$this->registerPersistenceEvent('restored', function(Post $post) {
    Cache::forget("post:{$post->getId()}");
});
```

## Custom Domain Events

For domain-level events — things that happen within your application logic, not just at the persistence layer — define and dispatch your own event classes. This is a Laravel pattern that Holloway doesn't need to know about.

```php
// Define your event
class PostPublished
{
    public function __construct(
        public readonly Post $post,
        public readonly \DateTimeInterface $publishedAt,
    ) {}
}

// Dispatch from your service layer
class PostService
{
    public function publish(Post $post): void
    {
        $post->setPublishedAt(now());
        $post->setStatus('published');

        $this->postMapper->store($post);

        event(new PostPublished($post, $post->getPublishedAt()));
    }
}

// Listen in your service provider
Event::listen(PostPublished::class, function(PostPublished $event) {
    app(SearchIndexer::class)->index($event->post);
    app(NotificationService::class)->notifySubscribers($event->post);
});
```

This keeps domain logic in your service/entity layer and decoupled from the mapper.

## Event-Driven Architecture

For complex workflows that span multiple services, a saga or process manager pattern works well alongside custom events:

```php
class OrderProcessingSaga
{
    public function __construct(
        private OrderMapper $orderMapper,
        private InventoryService $inventory,
        private PaymentService $payment,
        private ShippingService $shipping,
    ) {}

    public function handleOrderCreated(OrderCreated $event): void
    {
        $order = $event->order;

        try {
            $this->inventory->reserve($order);
            $order->setStatus('inventory_reserved');
            $this->orderMapper->store($order);

            event(new OrderInventoryReserved($order));

        } catch (InsufficientInventoryException $e) {
            $order->setStatus('failed_inventory');
            $this->orderMapper->store($order);

            event(new OrderProcessingFailed($order, 'insufficient_inventory'));
        }
    }

    public function handleInventoryReserved(OrderInventoryReserved $event): void
    {
        $order = $event->order;

        try {
            $this->payment->charge($order);
            $order->setStatus('payment_processed');
            $this->orderMapper->store($order);

            event(new OrderPaymentProcessed($order));

        } catch (PaymentFailedException $e) {
            $this->inventory->release($order);
            $order->setStatus('failed_payment');
            $this->orderMapper->store($order);

            event(new OrderProcessingFailed($order, 'payment_failed'));
        }
    }
}
```

## Best Practices

### Keep listeners focused

```php
// Good: one responsibility per listener
$this->registerPersistenceEvent('created', function(Post $post) {
    Cache::tags(['posts'])->flush();
});

$this->registerPersistenceEvent('created', function(Post $post) {
    app(AuditLogger::class)->log('post.created', ['id' => $post->getId()]);
});
```

### Queue slow work

Persistence event listeners run synchronously inside the `store()` / `remove()` call. Keep them fast — queue anything that can wait.

```php
$this->registerPersistenceEvent('created', function(Post $post) {
    // Fast: clear cache inline
    Cache::forget("post:{$post->getId()}");

    // Slow: queue for background processing
    dispatch(new UpdateSearchIndexJob($post->getId()));
    dispatch(new SendPublicationNotificationsJob($post->getId()));
});
```

### Handle failures gracefully

A listener that throws an exception will bubble up and abort the persistence call. Wrap side effects that shouldn't block persistence.

```php
$this->registerPersistenceEvent('created', function(Post $post) {
    try {
        app(SearchIndexer::class)->index($post);
    } catch (SearchIndexException $e) {
        logger()->error('Search index failed', ['post_id' => $post->getId()]);
        dispatch(new RetrySearchIndexJob($post->getId()));
    }
});
```

### Use domain events for business logic

Mapper persistence events are for infrastructure concerns (caching, logging, syncing). Business logic — state transitions, notifications tied to domain rules — belongs in domain events dispatched from your service layer.

```php
// Infrastructure concern: belongs in persistence event
$this->registerPersistenceEvent('removed', fn($post) => Cache::forget("post:{$post->getId()}"));

// Business concern: belongs in service layer as a domain event
class PostService
{
    public function archive(Post $post): void
    {
        $post->archive();
        $this->postMapper->store($post);

        event(new PostArchived($post)); // domain event, not persistence event
    }
}
```
