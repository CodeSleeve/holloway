# Mapper API Reference

A concise summary of the methods you’ll call most often when working with Holloway mappers and their query builders. Pair this with the full guides in `docs/mappers/` when you need more context.

## Mapper lifecycle

| Method | Description | Notes |
| --- | --- | --- |
| `register(array $mappers)` | Register mapper classes with the Holloway registry. | Usually done in a service provider. |
| `getMapper(string $entityClass)` | Resolve a mapper by entity class name. | Safe to cache within a request; Holloway keeps a singleton instance. |
| `store($entity)` | Persist an entity (or iterable of entities). | INSERT if identifier is empty, UPDATE otherwise; wraps collections in a transaction. |
| `remove($entity)` | Delete an entity (or iterable). | Respects soft deletes if the mapper uses `SoftDeletes`. |
| `instantiateEntity(array $attributes)` | Create an empty entity instance. | Overridable if you need custom factories. |
| `hydrate(stdClass $record, Collection $relations)` | Convert a DB record to an entity. | Implemented by your mapper subclass. |
| `dehydrate($entity)` | Convert an entity to an array of database attributes. | Implemented by your mapper subclass. |
| `flushEntityCache()` | Clear the mapper’s identity map. | Useful in long-running processes or tests. |

## Query builder shortcuts

Every mapper proxies unknown method calls to an internal `Builder` instance (see `CodeSleeve\Holloway\Builder`). You can start queries with `$mapper->query()` or just call fluent methods on the mapper itself.

| Builder method | Purpose | Example |
| --- | --- | --- |
| `find($id)` | Retrieve a single entity by primary key. | `$users->find(5);` |
| `findOrFail($id)` | Same as `find`, but throws if missing. | `$users->findOrFail($id);` |
| `first()` / `firstOrFail()` | Retrieve the first matching entity. | `$users->where('email', $email)->first();` |
| `get()` | Execute the query and return a `Collection` of entities. | `$users->where('active', true)->get();` |
| `paginate($perPage)` | Paginator with Holloway entities. | `$users->paginate(25);` |
| `chunk($count, $callback)` | Stream entities in chunks. | `$users->chunk(100, fn($users) => ...);` |
| `with($relations)` | Eager load relationships. | `$users->with(['tenants', 'role'])->find($id);` |
| `where($column, $operator = null, $value = null)` | Add a WHERE clause. | `$users->where('name', 'like', '%Jean%');` |
| `orderBy($column, $direction = 'asc')` | Apply sorting. | `$users->orderBy('created_at', 'desc')->get();` |
| `limit($value)` / `take($value)` | Restrict number of rows. | `$users->limit(10)->get();` |
| `exists()` | Determine whether any matching entity exists. | `$users->where('email', $email)->exists();` |

## Relationship helpers

Call these inside `defineRelations()` to declare how an entity links to others.

| Method | Use for | Signature highlights |
| --- | --- | --- |
| `hasOne($name, $relatedClass, $foreignKey = null, $localKey = null)` | One-to-one relationships owned by the parent. | `$this->hasOne('profile', Profile::class);` |
| `hasMany($name, $relatedClass, $foreignKey = null, $localKey = null)` | One-to-many relationships owned by the parent. | `$this->hasMany('jobs', ServiceJob::class, 'tenant_id');` |
| `belongsTo($name, $relatedClass, $foreignKey = null, $ownerKey = null)` | Inverse relationships that store the FK locally. | `$this->belongsTo('tenant', Tenant::class);` |
| `belongsToMany($name, $relatedClass, $pivotTable = null, $foreignKey = null, $relatedKey = null)` | Many-to-many relationships with a pivot table. | `$this->belongsToMany('tenants', Tenant::class);` |
| `customOne($name, callable $loader, callable $matcher, $relatedClass)` | One-off relationship logic backed by custom queries. | Perfect for polymorphic or scoped lookups. |
| `customMany($name, callable $loader, callable $matcher, $relatedClass)` | Same idea as `customOne`, but returns collections. | Use when your SQL can’t be expressed with the built-in helpers. |

## Scope conventions

Define scopes as public methods on the mapper; they receive the builder instance as the first argument.

```php
public function scopeActive($query)
{
    return $query->where('is_active', true);
}

public function scopeSearch($query, array $filters)
{
    return $query
        ->when($filters['term'] ?? null, fn($q, $term) => $q->where('name', 'ilike', "%{$term}%"))
        ->when($filters['role'] ?? null, fn($q, $role) => $q->where('role', $role));
}
```

Call scopes fluently through the mapper or builder: `$users->active()->get();` or `$users->search($filters)->paginate();`.