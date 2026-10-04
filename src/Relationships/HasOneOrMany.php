<?php

namespace CodeSleeve\Holloway\Relationships;

use Illuminate\Support\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
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

        $query = ($this->query)();

        $constraints($query);    // Allow for constraints to be applied to the Holloway\Builder $query

        $this->data = $query->whereIn("{$this->table}.{$this->foreignKeyName}", $records->pluck($this->localKeyName)->values()->all())
            ->toBase()
            ->get();
    }

    /**
     * Build a count subquery for HasOne/HasMany relationships.
     *
     * The parent column is the relationship's own local key (as in load()), which is not
     * necessarily the mapper's primary key ($parentKey).
     *
     * @param  string        $parentTable
     * @param  string        $parentKey
     * @param  Closure|null  $constraints
     * @return \Illuminate\Database\Query\Builder
     */
    public function toCountQuery(string $parentTable, string $parentKey, ?Closure $constraints = null) : QueryBuilder
    {
        $query = $this->newCountQuery($parentTable);

        $query->selectRaw('count(*)')
            ->whereColumn(
                $query->getMapper()->getTable() . '.' . $this->foreignKeyName,
                '=',
                $parentTable . '.' . $this->localKeyName
            );

        return $this->finishCountQuery($query, $constraints);
    }
}