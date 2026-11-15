# Custom Relationships

While Holloway's standard relationships (HasOne, HasMany, BelongsTo, BelongsToMany) cover most use cases, complex business requirements often demand more flexibility. Custom relationships provide unlimited power to define sophisticated data loading patterns.

## Table of Contents

- [Understanding Custom Relationships](#understanding-custom-relationships)
- [Custom Relationship Types](#custom-relationship-types)
- [Advanced Custom Relationship Examples](#advanced-custom-relationship-examples)

## Understanding Custom Relationships

Custom relationships allow you to define arbitrary loading logic using three key components:

1. **Load Function** - Executes the database query
2. **For Function** - Matches loaded data to parent entities  
3. **Map Function/Entity Class** - Converts data to entities

### Basic Custom Relationship Structure

```php
$this->custom(
    'relationshipName',
    $loadFunction,      // function($query, $parentRecords, $constraints)
    $forFunction,       // function($parentRecord, $loadedRecord)
    $mapFunction,       // function($record) OR EntityClass::class
    $limitOne = false   // true for single result, false for collection
);
```

The records passed to the load and for functions are the raw `stdClass` rows from the database. Entities aren't built until every relationship has been loaded.

### Constraints

When a custom relationship is loaded with constraints, `with(['collars' => fn($query) => $query->where('collars.color', 'blue')])`, they are passed to your load function as a third argument. You decide where they apply, since you wrote the query. Call the constraints on your query (a plain query builder, not a Holloway builder) before getting the results:

```php
$this->customMany('collars', function($query, $packs, $constraints) {
    $query->from('collars')
        ->select('collars.*', 'pups.pack_id')
        ->join('pups', 'collars.pup_id', '=', 'pups.id')
        ->whereIn('pups.pack_id', $packs->pluck('id'));

    $constraints($query);

    return $query->get();
}, function($pack, $collar) {
    return $pack->id == $collar->pack_id;
}, Collar::class);
```

`$constraints` is always callable, an empty closure when none were given. A load function that doesn't take a third argument ignores any constraints it is given, so qualify column names in constraints (`collars.color`) when your query has joins.

## Custom Relationship Types

### CustomMany (Collection Results)

Returns a collection of related entities:

```php
class PackMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Load all collars for pups in this pack
        $this->customMany('collars', function($query, $packs) {
            return $query->from('collars')
                ->select('collars.*', 'pups.pack_id')
                ->join('pups', 'collars.pup_id', '=', 'pups.id')
                ->whereIn('pups.pack_id', $packs->pluck('id'))
                ->get();
        }, function($pack, $collar) {
            return $pack->id == $collar->pack_id;
        }, Collar::class);
    }
}
```

### CustomOne (Single Result)

Returns a single related entity:

```php
class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Load user's most recent post
        $this->customOne('latestPost', function($query, $users) {
            return $query->from('posts')
                ->select('posts.*')
                ->whereIn('user_id', $users->pluck('id'))
                ->where('published', true)
                ->orderBy('created_at', 'desc')
                ->get();
        }, function($user, $post) {
            return $user->id == $post->user_id;
        }, Post::class);
    }
}
```

## Advanced Custom Relationship Examples

### Aggregated Data Relationships

```php
class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Load user statistics as a value object
        $this->customOne('stats', function($query, $users) {
            return $query->from('posts')
                ->select([
                    'user_id',
                    DB::raw('COUNT(*) as post_count'),
                    DB::raw('SUM(view_count) as total_views'),
                    DB::raw('AVG(rating) as avg_rating'),
                    DB::raw('MAX(created_at) as last_post_date')
                ])
                ->whereIn('user_id', $users->pluck('id'))
                ->where('published', true)
                ->groupBy('user_id')
                ->get();
        }, function($user, $statsRecord) {
            return $user->id == $statsRecord->user_id;
        }, function($record) {
            // Map to value object
            return new UserStats(
                $record->post_count,
                $record->total_views,
                $record->avg_rating,
                new DateTime($record->last_post_date)
            );
        });
    }
}
```

### Multi-Step Relationships

```php
class CompanyMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Load all skills of employees in this company
        $this->customMany('employeeSkills', function($query, $companies) {
            return $query->from('skills')
                ->select('skills.*', 'employees.company_id')
                ->join('employee_skills', 'skills.id', '=', 'employee_skills.skill_id')
                ->join('employees', 'employee_skills.employee_id', '=', 'employees.id')
                ->whereIn('employees.company_id', $companies->pluck('id'))
                ->where('skills.active', true)
                ->distinct()
                ->get();
        }, function($company, $skill) {
            return $company->id == $skill->company_id;
        }, Skill::class);
    }
}
```

## Next Steps

- **[Eager Loading](./eager-loading.md)** - Loading relationships
- **[Nested Relationships](./nested.md)** - Combine custom relationships with standard ones
