# Getting Started with Holloway

This quick tour shows how to install Holloway in a fresh Laravel project, register your first mapper, and perform real CRUD using rich domain entities. Follow the steps in order—each one builds on the last.

## Prerequisites

- Laravel 10 or 11 project with a working database connection.
- PHP 8.1 or higher (Holloway itself supports 8.1+, though some advanced patterns assume 8.2+).
- Familiarity with basic Laravel concepts (service providers, artisan commands, configuration).

## 1. Install the package

```bash
composer require codesleeve/holloway
```

If your application does not use package auto-discovery, add the service provider to `config/app.php`:

```php
'providers' => [
    // ...
    CodeSleeve\Holloway\HollowayServiceProvider::class,
],
```

## 2. Create a Holloway service provider

You need one place to register your mappers and any shared configuration.

```bash
php artisan make:provider DataMapperServiceProvider
```

Update the generated provider:

```php
<?php

namespace App\Providers;

use App\Mappers;
use App\Entities;
use Illuminate\Support\ServiceProvider;
use CodeSleeve\Holloway\Holloway;
use Doctrine\Instantiator\Instantiator;

class DataMapperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance(Holloway::class, Holloway::instance());
        $this->app->singleton(Instantiator::class);

        $this->registerEntities();
    }

    protected function registerEntities(): void
    {
        $mappers = [
            Entities\User::class => Mappers\UserMapper::class,
        ];

        $holloway = Holloway::instance();
        $holloway->register(array_values($mappers));

        foreach ($mappers as $entity => $mapper) {
            $this->app->instance($mapper, $holloway->getMapper($entity));
        }
    }
}
```

Finally, register the new provider in `config/app.php` (or rely on Laravel package discovery if you publish it as a package):

```php
'providers' => [
    // ...
    App\Providers\DataMapperServiceProvider::class,
],
```

> **Alternative: Inline registration**
> Don't want (or need) a dedicated provider? You can always just register mappers directly in `AppServiceProvider` or another existing provider:
>
> ```php
> // app/Providers/AppServiceProvider.php
> 
> use App\Entities\User;
> use App\Mappers\UserMapper;
> use CodeSleeve\Holloway\Holloway;
> 
> public function register(): void
> {
>     $holloway = Holloway::instance();
>     $holloway->register([UserMapper::class]);
> 
>     $this->app->instance(UserMapper::class, $holloway->getMapper(User::class));
> }
> ```

## 3. Add base classes for entities and mappers

Holloway gives you complete control over hydration. Most applications start with reusable base classes to keep mappers small.

`app/Entities/Entity.php`

```php
<?php

namespace App\Entities;

abstract class Entity
{
    protected int|string|null $id = null;

    public function mapperFill(array $properties): static
    {
        foreach ($properties as $name => $value) {
            $this->$name = $value;
        }

        return $this;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public function setId(int|string $id): void
    {
        $this->id = $id;
    }

    public function getId(): int|string|null
    {
        return $this->id;
    }
}
```

`app/Mappers/Mapper.php`

```php
<?php

namespace App\Mappers;

use stdClass;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use CodeSleeve\Holloway\Mapper as BaseMapper;

abstract class Mapper extends BaseMapper
{
    protected string $entityClassName = '';

    public function getEntityClassName(): string
    {
        return $this->entityClassName;
    }

    public function getIdentifier($entity)
    {
        return $entity->getId();
    }

    public function setIdentifier($entity, $value): void
    {
        $entity->setId($value);
    }

    public function hydrate(stdClass $record, Collection $relations)
    {
        $entity = $this->instantiator->instantiate($this->entityClassName);

        return $entity->mapperFill(array_merge((array) $record, $relations->all()));
    }

    public function dehydrate($entity): array
    {
        $attributes = Arr::except(
            $entity->toArray(),
            array_map(fn ($relationship) => $relationship->getName(), $this->relationships)
        );

        if (empty($attributes['id'])) {
            unset($attributes['id']);
        }

        return $attributes;
    }
}
```

> **Optional:** If you want automatic casting of value objects (email, money, enums, etc.), plug in the mapping registry pattern described in [Type Transformations](./core-concepts/type-transformations.md). Skip it for now—you can layer it in later.

## 4. Build your first entity and mapper

Create a rich domain entity that owns business rules and let the mapper handle persistence.

`app/Entities/User.php`

```php
<?php

namespace App\Entities;

use InvalidArgumentException;

class User extends Entity
{
    protected string $name;
    protected string $email;

    public function __construct(string $name, string $email)
    {
        if ($name === '') {
            throw new InvalidArgumentException('Name is required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email address is invalid.');
        }

        $this->name = $name;
        $this->email = $email;
    }

    public function rename(string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Name is required.');
        }

        $this->name = $name;
    }

    public function changeEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email address is invalid.');
        }

        $this->email = $email;
    }
}
```

`app/Mappers/UserMapper.php`

```php
<?php

namespace App\Mappers;

use App\Entities\User;

class UserMapper extends Mapper
{
    protected string $table = 'users';
    protected string $entityClassName = User::class;

    public function defineRelations(): void
    {
        // Add relationships later (hasMany, belongsTo, custom relationships, etc.).
    }
}
```

## 5. Wire it into Laravel

Run the database migration that creates a `users` table (any schema that has `id`, `name`, and `email` columns will work). Then resolve the mapper anywhere in your application:

```php
use App\Entities\User;
use App\Mappers\UserMapper;

Route::get('/users-demo', function (UserMapper $users) {
    $user = new User('Jean-Luc Picard', 'captain@enterprise.test');
    $users->store($user); // INSERT

    $loaded = $users->find($user->getId());
    $loaded->rename('Jean-Luc Picard Sr.');
    $users->store($loaded); // UPDATE

    return $users->all()->map(fn (User $u) => $u->toArray());
});
```

Laravel automatically injects the mapper because the service provider registered it in the container.

You can also resolve mappers directly through Holloway's registry:

```php
use CodeSleeve\Holloway\Holloway;
use App\Entities\User;

$users = Holloway::instance()
    ->getMapper(User::class)
    ->where('active', true)
    ->get();
```

## 6. Smoke-test the setup

Open Tinker or run a feature test to confirm everything is wired correctly.

```bash
php artisan tinker
```

```php
>>> $users = app(\App\Mappers\UserMapper::class);
>>> $entity = new \App\Entities\User('Beverly Crusher', 'doctor@example.com');
>>> $users->store($entity);
>>> $users->find($entity->getId())->toArray();
```

You should see the hydrated entity array containing the new record.

## Troubleshooting

| Symptom | Likely Cause | Fix |
| --- | --- | --- |
| `Call to a member function getMapper()` on null | Service provider not registered or not running in the current environment. | Confirm `DataMapperServiceProvider` is listed in `config/app.php` and clear config cache. |
| `ArgumentCountError` from `Mapper::hydrate()` | Your mapper overrides `hydrate` with the wrong signature. | Ensure it accepts `(stdClass $record, Collection $relations)` exactly. |
| Entities keep losing relationships | Remember to remove relationship names from `dehydrate()` and use `$this->relationships` to avoid persisting loaded relations. | Double-check that your entity’s `toArray()` is not re-introducing relationship properties. |
| Value objects aren’t being converted | The base mapper only copies attributes. Configure mappings as shown in [Type Transformations](./core-concepts/type-transformations.md) or handle conversions manually. | Register hydrate/dehydrate callbacks in your service provider or override `hydrate`/`dehydrate` per mapper. |

## Where next?

- Read the [Laravel Integration Guide](./laravel/integration.md) for queues, events, pagination, and testing.
- Explore [Mapper Query Building](./mappers/query-building.md) and [Relationships Overview](./relationships/overview.md) to load related aggregates.
- When you want to understand Holloway internals, jump to the [Architecture Overview](./architecture.md).
