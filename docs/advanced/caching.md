# Entity Caching

Each mapper keeps a small in-memory cache of the **raw attributes of every row it has hydrated**, keyed by primary key. `store()` uses it to tell whether an entity already exists and whether it has changed.

> **This is not an identity map.** Querying the same row twice builds two separate entity objects and runs two queries. The cache never returns entities and never prevents a query. It only remembers the attribute arrays the entities were built from.

## Table of Contents

- [How the Entity Cache Works](#how-the-entity-cache-works)
- [What `store()` Does With It](#what-store-does-with-it)
- [Cache Management](#cache-management)
- [Lifetime and Memory](#lifetime-and-memory)
- [Relationships](#relationships)
- [Limitations](#limitations)
- [Next Steps](#next-steps)

## How the Entity Cache Works

Every mapper owns an `EntityCache` (a map of `identifier => array of attributes`). It is maintained by the mapper:

- **Hydration** - `makeEntity()` stores the raw record (as an array) under its primary key each time a row is turned into an entity.
- **Store** - after an insert or update, `storeEntity()` stores the entity's dehydrated attributes.
- **Removal** - `removeEntity()` removes the entry.

```php
$user1 = $userMapper->find(1);   // query 1: builds a User, caches the row's attributes
$user2 = $userMapper->find(1);   // query 2: builds a second User

var_dump($user1 === $user2);     // false - two separate objects
echo $userMapper->getNumberOfCachedEntities();   // 1 - one cache entry per primary key
```

## What `store()` Does With It

When you call `store()` with an entity that has an identifier, the mapper looks for the entity's cached attributes (falling back to a lookup by primary key) to decide whether this is an update or an insert. For an update it then compares the entity's `dehydrate()` output with the cached attributes:

- If the two arrays are identical (a strict `!==` comparison: same keys, order and types), no `UPDATE` is issued.
- Otherwise, **all** of the dehydrated attributes are written in a single `UPDATE` (`created_at` is left out and `updated_at` is refreshed when timestamps are enabled). Holloway does not diff individual columns.

> **Current behavior:** the cached copy of a loaded row also carries a `relations` key, and `dehydrate()` output typically differs from the raw row in key order or value types (for example, timestamps that are objects on the entity). As a result the comparison generally reports the entity as changed, so `store()` normally issues an `UPDATE` even when nothing changed. Don't rely on the cache to skip no-op updates.

## Cache Management

```php
$userMapper = Holloway::instance()->getMapper(User::class);

// How many rows are cached on this mapper
$count = $userMapper->getNumberOfCachedEntities();

// Clear this mapper's cache
$userMapper->clearEntityCache();     // flushEntityCache() does the same thing

// Clear every registered mapper's cache
Holloway::instance()->flushEntityCache();
```

`Builder::chunk()` and `Builder::chunkById()` clear the mapper's cache after each chunk, so processing a large table in chunks does not grow memory without bound.

```php
$userMapper->chunk(1000, function ($users) {
    foreach ($users as $user) {
        // ...
    }
    // The cache is cleared automatically after each chunk.
});
```

## Lifetime and Memory

- The cache lives as long as the mapper instance. Holloway keeps one instance per entity in its registry, so in a typical web request the cache starts empty and is discarded when the request ends.
- In long-running processes (queue workers, Octane, daemons) the registry persists between jobs, so the cache keeps growing. Call `Holloway::instance()->flushEntityCache()` between jobs.
- Every row returned by `get()`, `all()`, `find()` and so on is cached, so loading a very large result set caches every row. Use `chunk()` / `chunkById()` for big tables.

```php
// Caches every row at once
$users = $userMapper->all();

// Better for large tables: the cache is cleared after each chunk
$userMapper->chunk(1000, function ($users) {
    $this->processChunk($users);
});
```

## Relationships

Eager-loaded related records are built through their own mapper's `makeEntity()`, so they are cached on **that** mapper:

```php
$user = $userMapper->with('posts')->find(1);

$postMapper = Holloway::instance()->getMapper(Post::class);
echo $postMapper->getNumberOfCachedEntities();   // one entry per loaded post
```

Related entities are still separate objects. Loading a post by itself later does not return the instance that was attached to `$user->posts`.

## Limitations

- **Not shared across requests or processes.** Each request builds its own cache, and the cache is not persisted.
- **No awareness of other writers.** If another process changes a row after you loaded it, the cached attributes are stale until the entity is loaded again or the cache is cleared.
- **No query avoidance.** The cache never answers a `find()` or `get()`; every read goes to the database.

## Next Steps

- **[Soft Deletes](./soft-deletes.md)** - Implement soft deletion
- **[Events & Hooks](./events.md)** - Use lifecycle events
