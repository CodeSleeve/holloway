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
2. **Registers event dispatcher** - Enables Holloway to work with Laravel's event system

```php
// From HollowayServiceProvider::boot()
Mapper::setConnectionResolver($this->app['db']);
Mapper::setEventManager(new Dispatcher);
```

## Registration

### Automatic Registration (Laravel 5.5+)
If you're using Laravel 5.5 or later with package auto-discovery, the service provider registers automatically.

### Manual Registration
For older Laravel versions, add to `config/app.php`:

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
    protected $connection = 'analytics'; // Must exist in config/database.php
}
```

## Event Integration

The service provider configures Holloway to use Laravel's event dispatcher, enabling you to listen for mapper events:

```php
// In a service provider or EventServiceProvider
Event::listen('mapper.*', function ($eventName, $data) {
    // Handle mapper events
    Log::info("Mapper event: {$eventName}");
});
```

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
