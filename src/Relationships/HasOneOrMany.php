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
     */
    public function load(Collection $records, ?Closure $constraints = null) : void
    {
        $constraints = $constraints ?: function() {};

        $this->data = ($this->query)()
            ->where($constraints)   // Allow for constraints to be applied to the to a Holloway\Builder $query
            ->toBase()
            ->from($this->table)
            ->whereIn("{$this->table}.{$this->foreignKeyName}", $records->pluck($this->localKeyName)->values()->all())
            ->get();
    }
}