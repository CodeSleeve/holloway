<?php

namespace CodeSleeve\Holloway\Relationships;

use Illuminate\Support\Collection;
use Closure;

abstract class HasOneOrMany extends BaseRelationship
{
    /**
     * Load the data for a has one or has many relationship:
     *
     * 1. Use the information on the relationship to fetch the correct table,
     * using the local and foreign key names supplied in the relationship definition.
     *
     * 2. We'll constrian the results using the collection of records that the relationship
     * is being loaded onto.
     *
     * 3. Finally, we'll apply any contraints (if any) that were defined on the load and
     * return the fetched records.
     *
     * @param  Collection    $records
     * @param  Closure|null  $constraints
     * @return Relationship
     */
    public function load(Collection $records, ?Closure $constraints = null)
    {
        $constraints = $constraints ?: function() {};

        $this->data = ($this->query)()
            ->from($this->tableName)
            ->whereIn("{$this->tableName}.{$this->foreignKeyName}", $records->pluck($this->localKeyName)->values()->all())
            ->where($constraints)
            ->get();
    }
}