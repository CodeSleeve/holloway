# Query Builder API

A mapper forwards the query methods it doesn't define to a Holloway builder, which forwards the ones it doesn't define to Laravel's query builder. Everything you know about `where()`, `orderBy()`, `join()`, aggregates and pagination works the same way. This page covers the places where a data mapper differs from Eloquent, so none of them are a surprise.

## Table of Contents

- [How a Query Runs](#how-a-query-runs)
- [Constraint Closures](#constraint-closures)
- [Select](#select)
- [Default Loads](#default-loads)
- [Custom Relationships](#custom-relationships)

## How a Query Runs

A query runs in three steps, and the rest of this page follows from them:

1. **Build the tree.** Every relationship you asked for (and every default load) becomes a node in a tree, before any query is run.
2. **Load the records.** Each node is queried once, in order, and returns raw `stdClass` rows.
3. **Hydrate.** Your mapper's `hydrate($record, $relations)` builds the entities, children first, so it receives the related entities it needs.

Holloway never builds an entity itself, which is why you decide what an entity looks like.

## Constraint Closures

A closure given to `with()` is called with the query that loads that relationship. For standard relationships it is a builder for the related mapper, and for [custom relationships](#custom-relationships) it is whatever your load function does with it.

| In a constraint closure | Result |
|---|---|
| `where()`, `orWhere()`, `whereIn()`, `orderBy()`, `join()` | Works |
| `withoutGlobalScope()`, `withTrashed()` and other scope changes | Works |
| `with()`, `without()`, `withoutDefaultLoads()` | Throws a `BadMethodCallException` |
| `limit()`, `offset()` | Works, but applies to the whole load, not to each parent |
| `select()` | Works, see [Select](#select) |

Loads can't be changed in a closure because they were decided in step 1, before the closure is called. Put nested loads on the outer query, and give each level its own entry with dot notation:

```php
$posts = $postMapper
    ->with([
        'comments' => fn($query) => $query->where('status', 'approved'),
        'comments.author' => fn($query) => $query->where('is_verified', true),
    ])
    ->get();
```

The constraints of an entry apply to the last relationship in its name: `comments.author` constrains `author`, and `comments` keeps its own entry.

Because a relationship is loaded with one query for all of its parents, a `limit(2)` loads two records in total, not two per parent.

## Select

`select()` works as it does in Laravel, and it is the way to run a query that joins tables: select `table.*` so columns aren't ambiguous.

Holloway hands your `hydrate()` exactly the columns that were selected, and adds none. So if you select a subset of columns:

- **Include the primary key.** The entity cache is keyed on it.
- **Include the keys a relationship matches on.** To eager load `pups` onto a pack, the pack rows need the key `pups` is matched from, and the pup rows need their `pack_id`. Leaving one out gives you PHP warnings about undefined properties and empty relationships.
- **Make `hydrate()` cope with the rest.** Holloway can't know which properties your entities require, so an entity built from fewer columns than it expects is your mapper's responsibility.

For a lightweight read, such as a list that only needs a name, consider a second mapper and entity for the same table that only knows about those columns, instead of a half-filled version of the full entity.

`get()` takes no arguments, so a column list such as `get(['id', 'name'])` is ignored. Call `select()` first.

## Default Loads

A mapper's `$with` property is loaded whenever that mapper is queried, and whenever it is reached through another mapper's relationship:

| You want | Call |
|---|---|
| Load something in addition to the defaults | `with('relationship')` |
| Drop one of the queried mapper's own defaults | `without('relationship')` |
| Load only what you ask for, no defaults at all | `withoutDefaultLoads()` |

`without()` only removes loads that are in the query's own list: the ones you asked for, and the defaults of the mapper being queried. The defaults of related mappers are added while the tree is built, so use `withoutDefaultLoads()` to skip them. It has to be called on the outer query, and it covers every level beneath it.

A default is never added if it leads back to a mapper that is already on the path to it, so mappers whose defaults point at each other can't loop. A load you ask for is never cut off. See [Default Loads](../relationships/overview.md#default-loads) for the details.

## Custom Relationships

The records of a custom relationship come from your own query, so Holloway can't apply a constraint for you. A constraint given to a custom relationship is passed to your load function as a third argument, and it is yours to apply. A load function that doesn't take a third argument ignores constraints. See [Custom Relationships](../relationships/custom.md#constraints).
