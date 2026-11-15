<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Mappers;

use CodeSleeve\Holloway\SoftDeletes;
use CodeSleeve\Holloway\Tests\Fixtures\Entities\Category;

/**
 * A mapper whose relationships all point back at its own table.
 */
class CategoryMapper extends Mapper
{
    use SoftDeletes;

    protected string $table = 'categories';
    protected string $entityClassName = Category::class;
    protected bool $hasTimestamps = false;

    /**
     * @return  void
     */
    public function defineRelations() : void
    {
        $this->belongsTo('parent', Category::class, 'parent_id', 'id');                                                  // A category belongs to a parent category.
        $this->hasMany('children', Category::class, 'parent_id', 'id');                                                  // A category has many child categories.
        $this->belongsToMany('related', Category::class, 'categories_related', 'category_id', 'related_category_id');    // A category is related to many other categories.
    }
}
