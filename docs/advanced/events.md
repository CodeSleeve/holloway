# Events

Holloway fires string-based events at key points in the entity persistence lifecycle. You can listen to these events to send notifications, clear caches, maintain audit logs, or trigger downstream workflows.

## Table of Contents

- [Persistence Event Names](#persistence-event-names)
- [Registering Listeners](#registering-listeners)
- [Preventing Operations](#preventing-operations)
- [Soft Delete Events](#soft-delete-events)

## Persistence Event Names

Events are dispatched as strings in the format `"holloway.eventName: FullEntityClassName"`, for example `"holloway.stored: App\Entities\Post"`. The `holloway.` prefix keeps them separate from other events on the shared dispatcher, so `Event::listen('holloway.*', ...)` listens to all of them. The following events fire during `store()` and `remove()` operations:

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

Return `false` from a `storing`, `creating`, `updating`, `removing` or `restoring` listener to cancel the operation, just as with Eloquent's model events. The mapper method returns `false` and nothing is written.

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

A few things to know:

- **Return nothing from "before" listeners that shouldn't cancel.** As with Eloquent, the first non-null value a `storing`, `creating`, `updating`, `removing` or `restoring` listener returns stops the remaining listeners for that event. Avoid arrow functions that return a value, such as `fn($post) => Cache::forget(...)`.
- Returning `false` from a `stored`, `created`, `updated`, `removed` or `restored` listener has no effect. The operation has already completed, and every listener runs.
- When you pass an iterable to `store()`, `remove()` or `restore()`, an entity whose operation is cancelled is skipped and the call still returns `true`.
- Throwing an exception from a listener also stops the operation.

## Soft Delete Events

Mappers using `SoftDeletes` fire `restoring` and `restored` around calls to `restore()`. You can cancel a restore by returning `false` from a `restoring` listener (see [Preventing Operations](#preventing-operations)).

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

## Notes

- Listeners run synchronously inside the `store()`, `remove()` or `restore()` call, so keep them fast and queue anything slow.
- An exception thrown from a listener propagates out of the mapper call.
