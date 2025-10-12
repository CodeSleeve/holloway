# Relationship Syntax Cheatsheet

Quick reference for defining and consuming relationships with Holloway mappers.

## Table of Contents

- [Defining relationships in `defineRelations()`](#defining-relationships-in-definerelations)
- [Eager loading syntax](#eager-loading-syntax)
- [Handling pivot data with `belongsToMany`](#handling-pivot-data-with-belongstomany)
- [Default eager loads](#default-eager-loads)
- [Troubleshooting](#troubleshooting)

## Defining relationships in `defineRelations()`

| Relationship | Example | Notes |
| --- | --- | --- |
| `hasOne` | `$this->hasOne('profile', Profile::class);` | Parent owns FK on related table. Optionally pass foreign/local keys. |
| `hasMany` | `$this->hasMany('serviceJobs', ServiceJob::class, 'tenant_id');` | Returns a `Collection` of related entities. |
| `belongsTo` | `$this->belongsTo('tenant', Tenant::class, 'tenant_id');` | Current entity holds the foreign key. |
| `belongsToMany` | `$this->belongsToMany('tenants', Tenant::class, 'tenants_users');` | Provide pivot table when the default name doesn’t match. |
| `customOne` | `$this->customOne('role', $loader, $matcher, Role::class);` | `loader` returns raw records, `matcher` decides which belongs to the parent. |
| `customMany` | `$this->customMany('notifications', $loader, $matcher, Notification::class);` | Use for polymorphic or computed relations. |

### Writing loaders and matchers

```php
$this->customMany('notifications', function ($query, Collection $users) {
    return $query->from('notifications')
        ->where('notifiable_type', User::class)
        ->whereIn('notifications.notifiable_id', $users->pluck('id'))
        ->get();
}, function (stdClass $user, stdClass $notification) {
    return $user->id === $notification->notifiable_id;
}, Notification::class);
```

- Loader receives the base query builder and the collection of parent `stdClass` records.
- Matcher receives individual parent/child records to decide if they belong together.

## Eager loading syntax

```php
$users = $userMapper
    ->with(['tenants', 'role', 'serviceJobs.activities'])
    ->where('is_active', true)
    ->get();
```

- Use dot notation for nested relationships (`serviceJobs.activities`).
- Combine with `customOne`/`customMany` names exactly as defined in the mapper.

## Handling pivot data with `belongsToMany`

```php
$this->belongsToMany('tools', Tool::class, 'service_jobs_tools')
    ->withPivot(['assigned_at', 'notes']);
```

- Call `withPivot()` inside `defineRelations()` when you need pivot attributes hydrated onto the related entity.
- To persist pivot data, call the relationship helper on the mapper’s query builder before executing (`$mapper->with('tools')->get();`).

## Default eager loads

```php
protected array $with = ['tenant', 'role'];
```

- Place on the mapper class to automatically eager load relationships for every query.
- Use sparingly—default eager loads apply to *all* queries initiated via this mapper.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Relationship returns `null` | Ensure the relationship name in `with()` matches the name defined in `defineRelations()`. |
| Custom relationship loads wrong data | Verify the matcher compares the correct keys (e.g. `$user->id === $role->user_id`). |
| Circular eager loading | Load only the direction you need; Holloway doesn’t guard against infinite recursion. |
| Pivot attributes missing | Confirm you called `withPivot()` and are using the relationship via `with()` when querying. |

Need more detail? Dive into `docs/relationships/overview.md` and the specialised pages in `docs/relationships/`.
