# Static Analysis (PHPStan)

Holloway ships a PHPStan extension that gives mapper and builder calls concrete entity types, with no annotations required on your mappers or entities.

## Installation

If you use [`phpstan/extension-installer`](https://github.com/phpstan/phpstan-extension-installer), the extension is registered automatically. Otherwise, include it in your `phpstan.neon`:

```neon
includes:
    - vendor/codesleeve/holloway/phpstan/extension.neon
```

## Requirements

The extension is built and tested against PHPStan 2.x (2.2 at the time of writing). Scope support uses PHPStan's method reflection API, which isn't covered by PHPStan's backward compatibility promise, so a future PHPStan minor release may need an update.

## What you get

The extension reads the `$entityClassName` default from each concrete mapper:

```php
class PupMapper extends Mapper
{
    protected string $entityClassName = Pup::class;
    // ...
}

$mapper->find(1);                        // Pup|null
$mapper->find([1, 2]);                   // Collection<int, Pup>
$mapper->findOrFail(1);                  // Pup
$mapper->get();                          // Collection<int, Pup>
$mapper->where('name', 'Rex')->first();  // Pup|null
$mapper->paginate();                     // LengthAwarePaginator<int, Pup>
```

`find()` returns a collection when given an array, and `Pup|null` otherwise. If PHPStan cannot tell which (for example a `mixed` argument), the result is the union of both.

Going through the registry keeps the types too:

```php
Holloway::instance()->getMapper(Pup::class)->find(1);   // Pup|null
```

`getMapper()` accepts a class constant, an entity instance, or a `class-string<Entity>`. Anything PHPStan can't narrow to a class (such as a plain `string`) returns an untyped `Mapper`.

## Scopes and soft-delete macros

Local scopes are resolved from the mapper's `scopeXxx()` methods, so calling them without the prefix is understood, on the mapper or anywhere in a chain, with the arguments checked against the scope's own signature:

```php
class UserMapper extends Mapper
{
    public function scopeSearch(Builder $query, string $term): Builder { /* ... */ }
}

$userMapper->search('rex');                        // Builder<User, UserMapper>
$userMapper->where('active', true)->search('rex')  // Builder<User, UserMapper>
    ->get();                                       // Collection<int, User>
$userMapper->search();                             // error: missing argument $term
```

On mappers that use the `SoftDeletes` trait, `withTrashed()`, `withoutTrashed()` and `onlyTrashed()` are understood the same way. Calling them on a mapper that doesn't use the trait is still an error.

This works because query builders returned from a concrete mapper carry the mapper's class as a second type, `Builder<User, UserMapper>`.

## Higher rule levels

Nothing needs to be added to your mappers at any level. `Mapper` and `Builder` declare their entity type with a default (`@template TEntity = mixed`), so referring to them without a type, such as `class PupMapper extends Mapper` or a `Builder $query` parameter in a scope, doesn't raise `missingType.generics`. You can still write `@extends Mapper<Pup>` to give a mapper an explicit entity type.

At level 8, the typed results surface nullability that was previously hidden: `find()` and `first()` return `Pup|null`, so code that uses the result without a null check will be reported, as it would be with Eloquent.

## Limitations

- The entity type comes from the `$entityClassName` default property. A mapper that only implements `getEntityClassName()` without setting the property is not resolved (use `@extends Mapper<Entity>` for those).
- Methods forwarded to the underlying query builder through `__call` are typed from the `@method` annotations on `Mapper` and `Builder`; their arguments are not checked, so anything Laravel accepts is accepted here. Methods not listed there are untyped.
- Scopes and soft-delete macros are resolved only when the mapper's class is known. They are not resolved on a mapper obtained from `Holloway::getMapper()` (its class isn't known statically), or on a bare `Builder $query` parameter inside a scope method (declare `@param Builder<User, UserMapper> $query` to call other scopes from there).
- A mapper that overrides `find()`, `get()` and similar keeps its own declared return types.
