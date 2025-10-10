# Custom Relationships

While Holloway's standard relationships (HasOne, HasMany, BelongsTo, BelongsToMany) cover most use cases, complex business requirements often demand more flexibility. Custom relationships provide unlimited power to define sophisticated data loading patterns.

## Table of Contents

- [Understanding Custom Relationships](#understanding-custom-relationships)
- [Custom Relationship Types](#custom-relationship-types)
- [Advanced Custom Relationship Examples](#advanced-custom-relationship-examples)
- [Geographic and Spatial Relationships](#geographic-and-spatial-relationships)
- [Complex Business Logic Relationships](#complex-business-logic-relationships)
- [Custom Relationships with Constraints](#custom-relationships-with-constraints)
- [Error Handling in Custom Relationships](#error-handling-in-custom-relationships)
- [Performance Optimization](#performance-optimization-for-custom-relationships)
- [Testing Custom Relationships](#testing-custom-relationships)
- [Best Practices](#best-practices)
- [Polymorphic Relationships from Production](#polymorphic-relationships-from-production-application)
  - [Polymorphic MorphMany Pattern](#polymorphic-morphmany-pattern)
  - [Multi-Condition Matching](#multi-condition-matching-pattern)
  - [Polymorphic Variations](#polymorphic-relationship-variations)
  - [Complex Pivot Queries](#complex-pivot-queries)
  - [Testing Polymorphic Relationships](#polymorphic-relationship-testing)

## Understanding Custom Relationships

Custom relationships allow you to define arbitrary loading logic using three key components:

1. **Load Function** - Executes the database query
2. **For Function** - Matches loaded data to parent entities  
3. **Map Function/Entity Class** - Converts data to entities

### Basic Custom Relationship Structure

```php
$this->custom(
    'relationshipName',
    $loadFunction,      // function($query, $parentEntities) 
    $forFunction,       // function($parentEntity, $loadedRecord)
    $mapFunction,       // function($record) OR EntityClass::class
    $limitOne = false   // true for single result, false for collection
);
```

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

### Time-Based Relationships

```php
class ProjectMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Load tasks due in the next 7 days
        $this->customMany('upcomingTasks', function($query, $projects) {
            $nextWeek = now()->addDays(7);
            
            return $query->from('tasks')
                ->whereIn('project_id', $projects->pluck('id'))
                ->where('due_date', '>=', now())
                ->where('due_date', '<=', $nextWeek)
                ->where('status', '!=', 'completed')
                ->orderBy('due_date')
                ->get();
        }, function($project, $task) {
            return $project->id == $task->project_id;
        }, Task::class);
        
        // Load overdue tasks
        $this->customMany('overdueTasks', function($query, $projects) {
            return $query->from('tasks')
                ->whereIn('project_id', $projects->pluck('id'))
                ->where('due_date', '<', now())
                ->where('status', '!=', 'completed')
                ->orderBy('due_date')
                ->get();
        }, function($project, $task) {
            return $project->id == $task->project_id;
        }, Task::class);
    }
}
```

### Conditional Relationships

```php
class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Load different content based on user role
        $this->customMany('accessibleDocuments', function($query, $users) {
            return $query->from('documents')
                ->select('documents.*', 'user_roles.user_id')
                ->join('document_permissions', 'documents.id', '=', 'document_permissions.document_id')
                ->join('user_roles', 'document_permissions.role_id', '=', 'user_roles.role_id')
                ->whereIn('user_roles.user_id', $users->pluck('id'))
                ->where('documents.active', true)
                ->where('document_permissions.can_read', true)
                ->get();
        }, function($user, $document) {
            return $user->id == $document->user_id;
        }, Document::class);
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

## Geographic and Spatial Relationships

```php
class StoreMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Load nearby stores within radius
        $this->customMany('nearbyStores', function($query, $stores) {
            return $query->from('stores as nearby')
                ->select([
                    'nearby.*',
                    'origin.id as origin_store_id',
                    DB::raw('ST_Distance_Sphere(
                        POINT(nearby.longitude, nearby.latitude),
                        POINT(origin.longitude, origin.latitude)
                    ) as distance_meters')
                ])
                ->crossJoin('stores as origin')
                ->whereIn('origin.id', $stores->pluck('id'))
                ->whereRaw('nearby.id != origin.id')
                ->whereRaw('ST_Distance_Sphere(
                    POINT(nearby.longitude, nearby.latitude),
                    POINT(origin.longitude, origin.latitude)
                ) <= 5000') // 5km radius
                ->orderBy('distance_meters')
                ->get();
        }, function($store, $nearbyStore) {
            return $store->id == $nearbyStore->origin_store_id;
        }, function($record) {
            $store = new Store($record->name, $record->latitude, $record->longitude);
            $store->setId($record->id);
            $store->setDistance($record->distance_meters);
            return $store;
        });
    }
}
```

## Complex Business Logic Relationships

### Financial Calculations

```php
class AccountMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Load account balance history with running totals
        $this->customMany('balanceHistory', function($query, $accounts) {
            return $query->from('transactions')
                ->select([
                    'account_id',
                    'amount',
                    'transaction_date',
                    'type',
                    DB::raw('@balance := @balance + 
                        CASE WHEN type = "credit" THEN amount ELSE -amount END as running_balance')
                ])
                ->crossJoin(DB::raw('(SELECT @balance := 0) as init'))
                ->whereIn('account_id', $accounts->pluck('id'))
                ->orderBy('account_id')
                ->orderBy('transaction_date')
                ->get();
        }, function($account, $transaction) {
            return $account->id == $transaction->account_id;
        }, function($record) {
            return new BalanceSnapshot(
                new Money($record->running_balance),
                new DateTime($record->transaction_date),
                TransactionType::from($record->type)
            );
        });
    }
}
```

### Content Recommendation System

```php
class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Load recommended content based on user behavior
        $this->customMany('recommendations', function($query, $users) {
            return $query->from('content')
                ->select([
                    'content.*',
                    'user_preferences.user_id',
                    DB::raw('(
                        (CASE WHEN content.category_id IN (
                            SELECT category_id FROM user_interactions 
                            WHERE user_id = user_preferences.user_id 
                            GROUP BY category_id 
                            ORDER BY COUNT(*) DESC LIMIT 3
                        ) THEN 3 ELSE 0 END) +
                        (CASE WHEN content.author_id IN (
                            SELECT author_id FROM user_interactions 
                            WHERE user_id = user_preferences.user_id AND rating >= 4
                        ) THEN 2 ELSE 0 END) +
                        (CASE WHEN content.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                        THEN 1 ELSE 0 END)
                    ) as relevance_score')
                ])
                ->crossJoin('user_preferences')
                ->whereIn('user_preferences.user_id', $users->pluck('id'))
                ->where('content.published', true)
                ->whereNotExists(function($subquery) {
                    $subquery->select(DB::raw(1))
                        ->from('user_interactions')
                        ->whereColumn('user_interactions.content_id', 'content.id')
                        ->whereColumn('user_interactions.user_id', 'user_preferences.user_id');
                })
                ->having('relevance_score', '>', 0)
                ->orderBy('relevance_score', 'desc')
                ->limit(10)
                ->get();
        }, function($user, $content) {
            return $user->id == $content->user_id;
        }, function($record) {
            $content = new Content($record->title, $record->body);
            $content->setId($record->id);
            $content->setRelevanceScore($record->relevance_score);
            return $content;
        });
    }
}
```

## Custom Relationships with Constraints

### Dynamic Constraints

```php
class PostMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Custom relationship that accepts runtime parameters
        $this->customMany('commentsByType', function($query, $posts) {
            // Access constraint parameters through closure scope
            $commentType = $this->getConstraintParameter('type', 'public');
            $limit = $this->getConstraintParameter('limit', 10);
            
            return $query->from('comments')
                ->whereIn('post_id', $posts->pluck('id'))
                ->where('type', $commentType)
                ->where('approved', true)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        }, function($post, $comment) {
            return $post->id == $comment->post_id;
        }, Comment::class);
    }
}

// Usage with constraints
$posts = $postMapper->with([
    'commentsByType' => function($query) {
        $query->setConstraintParameter('type', 'featured');
        $query->setConstraintParameter('limit', 5);
    }
])->get();
```

### Conditional Loading

```php
class OrderMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Load different shipping options based on order value
        $this->customMany('shippingOptions', function($query, $orders) {
            return $query->from('shipping_methods')
                ->select([
                    'shipping_methods.*',
                    'order_shipping.order_id',
                    DB::raw('CASE 
                        WHEN orders.total_amount >= 100 THEN shipping_methods.price * 0.5
                        WHEN orders.total_amount >= 50 THEN shipping_methods.price * 0.8
                        ELSE shipping_methods.price
                    END as discounted_price')
                ])
                ->crossJoin('orders')
                ->leftJoin('order_shipping', function($join) {
                    $join->on('shipping_methods.id', '=', 'order_shipping.shipping_method_id')
                         ->on('orders.id', '=', 'order_shipping.order_id');
                })
                ->whereIn('orders.id', $orders->pluck('id'))
                ->where('shipping_methods.active', true)
                ->where(function($subquery) {
                    $subquery->where('shipping_methods.min_order_value', '<=', DB::raw('orders.total_amount'))
                            ->orWhereNull('shipping_methods.min_order_value');
                })
                ->get();
        }, function($order, $shippingMethod) {
            return $order->id == $shippingMethod->order_id;
        }, function($record) {
            $method = new ShippingMethod($record->name, $record->price);
            $method->setDiscountedPrice($record->discounted_price);
            return $method;
        });
    }
}
```

## Error Handling in Custom Relationships

### Graceful Degradation

```php
class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        $this->customMany('recommendations', function($query, $users) {
            try {
                // Complex recommendation algorithm
                return $this->getAdvancedRecommendations($query, $users);
            } catch (Exception $e) {
                // Fallback to simple recommendations
                Log::warning('Advanced recommendations failed, using fallback', [
                    'error' => $e->getMessage()
                ]);
                
                return $this->getSimpleRecommendations($query, $users);
            }
        }, function($user, $recommendation) {
            return $user->id == $recommendation->user_id;
        }, Recommendation::class);
    }
    
    private function getAdvancedRecommendations($query, $users)
    {
        // Complex ML-based recommendations
        return $query->from('ml_recommendations')
            ->whereIn('user_id', $users->pluck('id'))
            ->where('generated_at', '>', now()->subHours(24))
            ->get();
    }
    
    private function getSimpleRecommendations($query, $users)
    {
        // Simple popularity-based recommendations
        return $query->from('content')
            ->select('content.*', DB::raw("'{$user->id}' as user_id"))
            ->where('featured', true)
            ->where('created_at', '>', now()->subDays(30))
            ->orderBy('view_count', 'desc')
            ->limit(5)
            ->get();
    }
}
```

### Validation and Data Integrity

```php
class ProjectMapper extends Mapper
{
    public function defineRelations(): void
    {
        $this->customMany('validatedTasks', function($query, $projects) {
            $results = $query->from('tasks')
                ->whereIn('project_id', $projects->pluck('id'))
                ->get();
            
            // Validate loaded data
            return $results->filter(function($task) {
                return $this->validateTask($task);
            });
        }, function($project, $task) {
            return $project->id == $task->project_id;
        }, Task::class);
    }
    
    private function validateTask($task): bool
    {
        // Ensure data integrity
        if (empty($task->title) || empty($task->status)) {
            Log::warning('Invalid task data detected', ['task_id' => $task->id]);
            return false;
        }
        
        if (!in_array($task->status, ['pending', 'in_progress', 'completed'])) {
            Log::warning('Invalid task status', [
                'task_id' => $task->id,
                'status' => $task->status
            ]);
            return false;
        }
        
        return true;
    }
}
```

## Performance Optimization for Custom Relationships

### Query Optimization

```php
class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        $this->customMany('optimizedPosts', function($query, $users) {
            // Use indexes and limit data transfer
            return $query->from('posts')
                ->select([
                    'id', 'user_id', 'title', 'status',
                    'created_at', 'view_count'
                ]) // Only needed columns
                ->whereIn('user_id', $users->pluck('id'))
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->orderBy('created_at', 'desc')
                ->limit(50) // Prevent excessive data loading
                ->get();
        }, function($user, $post) {
            return $user->id == $post->user_id;
        }, Post::class);
    }
}
```

### Caching Integration

```php
class CategoryMapper extends Mapper
{
    public function defineRelations(): void
    {
        $this->customMany('cachedProducts', function($query, $categories) {
            $cacheKey = 'category_products_' . implode('_', $categories->pluck('id')->sort()->toArray());
            
            return Cache::remember($cacheKey, 3600, function() use ($query, $categories) {
                return $query->from('products')
                    ->whereIn('category_id', $categories->pluck('id'))
                    ->where('active', true)
                    ->orderBy('featured', 'desc')
                    ->orderBy('name')
                    ->get();
            });
        }, function($category, $product) {
            return $category->id == $product->category_id;
        }, Product::class);
    }
}
```

## Testing Custom Relationships

### Unit Testing

```php
class UserMapperTest extends TestCase
{
    public function testCustomRecommendationRelationship()
    {
        // Setup test data
        $user = $this->createUser();
        $posts = $this->createPosts($user, 5);
        
        // Create interactions to influence recommendations
        $this->createUserInteractions($user, $posts);
        
        // Load user with recommendations
        $userMapper = Holloway::instance()->getMapper(User::class);
        $userWithRecommendations = $userMapper->with('recommendations')->find($user->id);
        
        // Assert recommendations are loaded correctly
        $this->assertNotEmpty($userWithRecommendations->recommendations);
        $this->assertInstanceOf(Collection::class, $userWithRecommendations->recommendations);
        
        // Assert recommendation quality
        foreach ($userWithRecommendations->recommendations as $recommendation) {
            $this->assertInstanceOf(Content::class, $recommendation);
            $this->assertGreaterThan(0, $recommendation->getRelevanceScore());
        }
    }
}
```

### Integration Testing

```php
class CustomRelationshipIntegrationTest extends TestCase
{
    public function testComplexMultiStepRelationship()
    {
        // Create test scenario
        $company = $this->createCompany();
        $employees = $this->createEmployees($company, 10);
        $skills = $this->createSkills(20);
        $this->assignSkillsToEmployees($employees, $skills);
        
        // Test the custom relationship
        $companyMapper = Holloway::instance()->getMapper(Company::class);
        $companyWithSkills = $companyMapper->with('employeeSkills')->find($company->id);
        
        // Verify relationship works correctly
        $this->assertNotEmpty($companyWithSkills->employeeSkills);
        
        // Verify no duplicate skills
        $skillIds = $companyWithSkills->employeeSkills->pluck('id')->toArray();
        $this->assertEquals(count($skillIds), count(array_unique($skillIds)));
        
        // Verify only active skills are loaded
        foreach ($companyWithSkills->employeeSkills as $skill) {
            $this->assertTrue($skill->isActive());
        }
    }
}
```

## Best Practices

### 1. Keep Load Functions Focused

```php
// Good: Single responsibility
$this->customMany('recentPosts', function($query, $users) {
    return $query->from('posts')
        ->whereIn('user_id', $users->pluck('id'))
        ->where('created_at', '>', now()->subDays(30))
        ->orderBy('created_at', 'desc')
        ->get();
}, $forFunction, Post::class);

// Avoid: Complex multi-purpose queries
$this->customMany('everything', function($query, $users) {
    // Don't try to load multiple different types of data
    // in a single custom relationship
}, $forFunction, $mapFunction);
```

### 2. Use Efficient For Functions

```php
// Good: Simple equality check
function($user, $post) {
    return $user->id == $post->user_id;
}

// Avoid: Complex logic in for function
function($user, $post) {
    // Complex calculations should be done in load function
    return $this->complexCalculation($user, $post);
}
```

### 3. Handle Edge Cases

```php
$this->customMany('safeRelationship', function($query, $entities) {
    // Handle empty entity collections
    if ($entities->isEmpty()) {
        return collect();
    }
    
    // Handle potential query errors
    try {
        return $query->from('related_table')
            ->whereIn('entity_id', $entities->pluck('id'))
            ->get();
    } catch (QueryException $e) {
        Log::error('Custom relationship query failed', [
            'error' => $e->getMessage(),
            'entity_count' => $entities->count()
        ]);
        return collect();
    }
}, function($entity, $related) {
    return $entity->id == $related->entity_id;
}, RelatedEntity::class);
```

Custom relationships provide unlimited flexibility for complex data loading scenarios while maintaining Holloway's performance characteristics and entity caching benefits.
## Polymorphic Relationships from Production Application

This example demonstrates real-world polymorphic relationship patterns using Laravel's notification system. These examples show how to handle `notifiable_type` and `notifiable_id` columns effectively.

### Polymorphic MorphMany Pattern

Both `ClientMapper` and `UserMapper` implement polymorphic notification relationships:

```php
class ClientMapper extends Mapper
{
    public function defineRelations(): void
    {
        // All notifications (polymorphic morphMany)
        $this->customMany('notifications', function ($query, Collection $clients) {
            return $query->from('notifications')
                ->where('notifiable_type', Entities\Client::class)
                ->whereIn('notifications.notifiable_id', $clients->pluck('id'))
                ->get();
        }, fn(stdClass $client, stdClass $notification) => 
            $client->id === $notification->notifiable_id, 
        Entities\Notification::class);

        // Read notifications only (polymorphic with constraint)
        $this->customMany('readNotifications', function ($query, Collection $clients) {
            return $query->from('notifications')
                ->where('notifiable_type', Entities\Client::class)
                ->whereIn('notifications.notifiable_id', $clients->pluck('id'))
                ->whereNotNull('notifications.read_at')
                ->get();
        }, fn(stdClass $client, stdClass $notification) => 
            $client->id === $notification->notifiable_id, 
        Entities\Notification::class);

        // Unread notifications only (polymorphic with constraint)
        $this->customMany('unreadNotifications', function ($query, Collection $clients) {
            return $query->from('notifications')
                ->where('notifiable_type', Entities\Client::class)
                ->whereIn('notifications.notifiable_id', $clients->pluck('id'))
                ->whereNull('notifications.read_at')
                ->get();
        }, fn(stdClass $client, stdClass $notification) => 
            $client->id === $notification->notifiable_id, 
        Entities\Notification::class);
    }
}

class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Same pattern for User entities
        $this->customMany('notifications', function ($query, Collection $users) {
            return $query->from('notifications')
                ->where('notifiable_type', Entities\User::class)
                ->whereIn('notifications.notifiable_id', $users->pluck('id'))
                ->get();
        }, fn(stdClass $user, stdClass $notification) => 
            $user->id === $notification->notifiable_id, 
        Entities\Notification::class);

        $this->customMany('readNotifications', function ($query, Collection $users) {
            return $query->from('notifications')
                ->where('notifiable_type', Entities\User::class)
                ->whereIn('notifications.notifiable_id', $users->pluck('id'))
                ->whereNotNull('notifications.read_at')
                ->get();
        }, fn(stdClass $user, stdClass $notification) => 
            $user->id === $notification->notifiable_id, 
        Entities\Notification::class);

        $this->customMany('unreadNotifications', function ($query, Collection $users) {
            return $query->from('notifications')
                ->where('notifiable_type', Entities\User::class)
                ->whereIn('notifications.notifiable_id', $users->pluck('id'))
                ->whereNull('notifications.read_at')
                ->get();
        }, fn(stdClass $user, stdClass $notification) => 
            $user->id === $notification->notifiable_id, 
        Entities\Notification::class);
    }
}
```

**Key patterns:**
- **Type checking**: `where('notifiable_type', EntityClass::class)` filters by fully-qualified class name
- **ID matching**: `whereIn('notifiable_id', $entities->pluck('id'))` for N+1 prevention
- **Additional constraints**: `whereNotNull('read_at')` or `whereNull('read_at')` for filtered relationships
- **Arrow functions**: Short, readable `for` functions using `fn()` syntax
- **Table qualification**: `notifications.notifiable_id` prevents ambiguity

### Multi-Condition Matching Pattern

This pattern uses complex pivot relationships with multiple conditions:

```php
class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Custom relationship with multi-column matching
        $this->customOne('role', function($query, Collection $users) {
            return $query->from('roles')
                ->select('roles.*', 'tenants_users.user_id', 'tenants_users.tenant_id')
                ->join('tenants_users', 'roles.id', '=', 'tenants_users.role_id')
                ->whereIn('tenants_users.user_id', $users->pluck('id'))
                ->whereIn('tenants_users.tenant_id', $users->pluck('current_tenant_id'))
                ->get();
        }, fn(stdClass $user, stdClass $role) => 
            $user->id === $role->user_id && $user->current_tenant_id === $role->tenant_id, 
        Entities\Role::class);
    }
}
```

**Key patterns:**
- **Multi-table joins**: Select pivot columns for matching logic
- **Multiple whereIn clauses**: Filter by multiple parent entity properties
- **Complex for function**: Match on TWO conditions (user_id AND tenant_id)
- **Context-aware loading**: Uses `current_tenant_id` for multi-tenant isolation

### Polymorphic Relationship Variations

Here are common polymorphic patterns from Production Application:

#### Pattern 1: Basic Polymorphic (All Records)

```php
$this->customMany('notifications', function ($query, Collection $entities) {
    return $query->from('notifications')
        ->where('notifiable_type', static::$entityClass)
        ->whereIn('notifiable_id', $entities->pluck('id'))
        ->get();
}, fn($entity, $notification) => $entity->id === $notification->notifiable_id, Notification::class);
```

#### Pattern 2: Filtered Polymorphic (With Constraint)

```php
$this->customMany('unreadNotifications', function ($query, Collection $entities) {
    return $query->from('notifications')
        ->where('notifiable_type', static::$entityClass)
        ->whereIn('notifiable_id', $entities->pluck('id'))
        ->whereNull('read_at')              // Additional constraint
        ->orderBy('created_at', 'desc')      // Optional ordering
        ->get();
}, fn($entity, $notification) => $entity->id === $notification->notifiable_id, Notification::class);
```

#### Pattern 3: Polymorphic with Pivot Data

```php
$this->customMany('activities', function ($query, Collection $entities) {
    return $query->from('activities')
        ->select('activities.*', 'activity_log.properties')
        ->join('activity_log', 'activities.id', '=', 'activity_log.activity_id')
        ->where('activity_log.subject_type', static::$entityClass)
        ->whereIn('activity_log.subject_id', $entities->pluck('id'))
        ->get();
}, fn($entity, $activity) => $entity->id === $activity->subject_id, Activity::class);
```

### Complex Pivot Queries

This example demonstrates sophisticated many-to-many relationships:

```php
class ClientMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Complex pivot with DB facade
        $this->customMany('locations', function($query, Collection $clients) {
            $locationIds = DB::table('clients_locations')
                ->whereIn('client_id', $clients->pluck('id'))
                ->pluck('location_id')
                ->unique();
            
            return $query->from('locations')
                ->whereIn('id', $locationIds)
                ->get();
        }, function(stdClass $client, stdClass $location) {
            // Check pivot table for relationship
            return DB::table('clients_locations')
                ->where('client_id', $client->id)
                ->where('location_id', $location->id)
                ->exists();
        }, Entities\Location::class);
    }
}
```

**Trade-offs:**
- ✅ Full control over pivot logic
- ✅ Can handle complex pivot conditions
- ❌ For function hits database (N+1 concern)
- ⚠️ Consider using `belongsToMany` for simple pivots

**Better approach** for simple pivots:

```php
// Use built-in belongsToMany when possible
$this->belongsToMany('locations', Entities\Location::class, 'clients_locations');
```

**Use customMany for pivots when:**
- Pivot has complex constraints (status, dates, etc.)
- Need to include pivot data in results
- Relationship involves more than two tables

### Polymorphic Relationship Testing

```php
class NotificationRelationshipTest extends TestCase
{
    public function testClientNotifications(): void
    {
        // Arrange
        $client = ClientFactory::new()->create();
        $user = UserFactory::new()->create();
        
        // Create notifications for both
        NotificationFactory::new()->for($client)->count(3)->create();
        NotificationFactory::new()->for($user)->count(2)->create();
        
        // Act
        $clientMapper = app(ClientMapper::class);
        $clientWithNotifications = $clientMapper->with('notifications')->find($client->id);
        
        // Assert - only client notifications loaded
        $this->assertCount(3, $clientWithNotifications->notifications);
        foreach ($clientWithNotifications->notifications as $notification) {
            $this->assertEquals(Client::class, $notification->notifiable_type);
            $this->assertEquals($client->id, $notification->notifiable_id);
        }
    }
    
    public function testUnreadNotificationsFilter(): void
    {
        // Arrange
        $client = ClientFactory::new()->create();
        NotificationFactory::new()->for($client)->read()->count(2)->create();
        NotificationFactory::new()->for($client)->unread()->count(3)->create();
        
        // Act
        $clientMapper = app(ClientMapper::class);
        $clientWithUnread = $clientMapper->with('unreadNotifications')->find($client->id);
        
        // Assert
        $this->assertCount(3, $clientWithUnread->unreadNotifications);
        foreach ($clientWithUnread->unreadNotifications as $notification) {
            $this->assertNull($notification->read_at);
        }
    }
}
```

### Best Practices for Polymorphic Relationships

1. **Use Fully-Qualified Class Names**
   ```php
   // Good
   ->where('notifiable_type', Entities\Client::class)
   
   // Bad - fragile to refactoring
   ->where('notifiable_type', 'Client')
   ```

2. **Qualify Column Names in Joins**
   ```php
   // Good
   ->whereIn('notifications.notifiable_id', $clients->pluck('id'))
   
   // Can be ambiguous
   ->whereIn('notifiable_id', $clients->pluck('id'))
   ```

3. **Create Separate Relationships for Filtered Views**
   ```php
   // Don't do this
   $client->notifications->filter(fn($n) => $n->read_at === null)
   
   // Do this
   $client->unreadNotifications
   ```

4. **Keep For Functions Simple**
   ```php
   // Good - simple equality check
   fn($entity, $related) => $entity->id === $related->entity_id
   
   // Avoid - database queries
   fn($entity, $related) => DB::table('pivot')->where(...)->exists()
   ```

5. **Handle Empty Collections Gracefully**
   ```php
   $this->customMany('notifications', function($query, Collection $entities) {
       if ($entities->isEmpty()) {
           return collect();
       }
       
       return $query->from('notifications')
           ->where('notifiable_type', static::$entityClass)
           ->whereIn('notifiable_id', $entities->pluck('id'))
           ->get();
   }, $forFunction, Notification::class);
   ```

## Next Steps

- **[Eager Loading](./eager-loading.md)** - Optimize relationship loading performance
- **[Nested Relationships](./nested.md)** - Combine custom relationships with standard ones
- **[Performance Best Practices](../examples/best-practices.md)** - Advanced optimization techniques
