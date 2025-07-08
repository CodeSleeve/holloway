# Custom Relationships

While Holloway's standard relationships (HasOne, HasMany, BelongsTo, BelongsToMany) cover most use cases, complex business requirements often demand more flexibility. Custom relationships provide unlimited power to define sophisticated data loading patterns.

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
## Polymorphic Relationships (Custom)

Polymorphic relationships allow a model to belong to more than one other model on a single association. For example, an `Image` might belong to either a `Post` or a `User`. Holloway's custom relationships make it possible to implement polymorphic associations with full flexibility.

### Example: Images Belonging to Multiple Entity Types

Suppose you have `posts`, `users`, and `images` tables. Each image can belong to either a post or a user, using `imageable_id` and `imageable_type` columns:

```php
// Migration example (for reference)
Schema::create('images', function (Blueprint $table) {
    $table->id();
    $table->string('url');
    $table->unsignedBigInteger('imageable_id');
    $table->string('imageable_type');
    $table->timestamps();
});
```

#### Defining the Polymorphic Relationship in a Mapper

```php
class PostMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Load images for posts (polymorphic)
        $this->customMany('images', function($query, $posts) {
            return $query->from('images')
                ->where('imageable_type', Post::class)
                ->whereIn('imageable_id', $posts->pluck('id'))
                ->get();
        }, function($post, $image) {
            return $post->id == $image->imageable_id && $image->imageable_type === Post::class;
        }, Image::class);
    }
}

class UserMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Load images for users (polymorphic)
        $this->customMany('images', function($query, $users) {
            return $query->from('images')
                ->where('imageable_type', User::class)
                ->whereIn('imageable_id', $users->pluck('id'))
                ->get();
        }, function($user, $image) {
            return $user->id == $image->imageable_id && $image->imageable_type === User::class;
        }, Image::class);
    }
}
```

#### Defining the Inverse (MorphTo) Relationship

You can also define a custom relationship on the `ImageMapper` to resolve the parent entity:

```php
class ImageMapper extends Mapper
{
    public function defineRelations(): void
    {
        // Polymorphic parent (morphTo)
        $this->customOne('imageable', function($query, $images) {
            // Group images by type
            $byType = $images->groupBy('imageable_type');
            $results = collect();
            foreach ($byType as $type => $group) {
                $ids = $group->pluck('imageable_id');
                $entities = Holloway::instance()->getMapper($type)->findMany($ids);
                $results = $results->merge($entities);
            }
            return $results;
        }, function($image, $parent) {
            return get_class($parent) === $image->imageable_type && $parent->id == $image->imageable_id;
        });
    }
}
```

This approach gives you full control over how polymorphic relationships are loaded and matched, and can be extended to support additional constraints, eager loading, or custom mapping logic.

## Next Steps

- **[Eager Loading](./eager-loading.md)** - Optimize relationship loading performance
- **[Nested Relationships](./nested.md)** - Combine custom relationships with standard ones
- **[Performance Best Practices](../examples/best-practices.md)** - Advanced optimization techniques
