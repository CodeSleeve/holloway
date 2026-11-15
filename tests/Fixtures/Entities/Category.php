<?php

namespace CodeSleeve\Holloway\Tests\Fixtures\Entities;

use Illuminate\Support\Collection;

/**
 * A self-referential entity: a category can have a parent and children (all categories), and be
 * related to other categories.
 */
class Category extends Entity
{
    protected string $name;
    protected ?int $parent_id = null;
    protected ?string $deleted_at = null;
    protected ?Category $parent;
    protected ?Collection $children;
    protected ?Collection $related;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
