# Service Provider

Holloway includes a Laravel service provider that handles integration with Laravel's service container and database connections.

## Table of Contents

- [What the Service Provider Does](#what-the-service-provider-does)
- [Registration](#registration)
- [Database Integration](#database-integration)
- [Event Integration](#event-integration)
- [Extending the Service Provider](#extending-the-service-provider)
- [Next Steps](#next-steps)

## What the Service Provider Does

The `HollowayServiceProvider` performs two key functions:

1. **Sets up database connections** - Configures Holloway to use Laravel's database connection resolver
2. **Sets the event dispatcher** - Points mappers at the application's event dispatcher for persistence events

```php
// From HollowayServiceProvider::boot()
Mapper::setConnectionResolver($this->app['db']);
Mapper::setEventManager($this->app['events']);
```

## Registration

### Automatic Registration
The service provider is registered through Laravel's package auto-discovery (`extra.laravel.providers` in Holloway's `composer.json`), so no setup is needed.

### Manual Registration
If you've disabled package auto-discovery for Holloway, add the provider yourself. In `config/app.php`:

```php
'providers' => [
    // Other providers...
    CodeSleeve\Holloway\HollowayServiceProvider::class,
],
```

## Database Integration

The service provider configures Holloway to use your existing Laravel database connections:

```php
// Uses the same connections defined in config/database.php
$userMapper = new UserMapper();
// Will use your default database connection

// Or specify a different connection
class AnalyticsMapper extends Mapper 
{
    protected string $connection = 'analytics'; // Must exist in config/database.php
}
```

## Event Integration

Mapper events are dispatched through the application's event dispatcher, so they work like Eloquent's model events. Listen with `registerPersistenceEvent()` on a mapper, or with the `Event` facade or a subscriber. The event name is `"holloway.eventName: FullEntityClassName"`, so `Event::listen('holloway.*', ...)` catches all mapper events:

```php
// On the mapper
$postMapper->registerPersistenceEvent('created', function (Post $post) {
    Log::info("Post created: {$post->getId()}");
});

// Or from anywhere in the application
Event::listen('holloway.created: ' . Post::class, function (Post $post) {
    //
});
```

See [Events](../advanced/events.md) for the event names.

## Extending the Service Provider

If you need custom configuration, create your own service provider:

```php
<?php

namespace App\Providers;

use CodeSleeve\Holloway\HollowayServiceProvider as BaseProvider;

class CustomHollowayProvider extends BaseProvider
{
    public function boot()
    {
        parent::boot();
        
        // Your custom initialization
        $this->configureCustomBehavior();
    }
    
    protected function configureCustomBehavior()
    {
        // Custom setup logic here
    }
}
```

## Next Steps

- **[Getting Started Guide](../getting-started.md)** - Complete setup and usage guide
